#!/usr/bin/env python3
"""Manifest/delta rehearsal for explicitly marked synthetic local object trees.

No cloud adapter or production execution mode exists. SHA256 covers full bytes;
the metadata sidecar models object headers, not provider version/ACL semantics.
"""
import argparse
from contextlib import ExitStack
import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import stat
import tempfile
import uuid

MARKER = '.opnform-transfer-root.json'
METADATA = '.opnform-object-metadata.json'
INTERNAL = {MARKER, METADATA, '.opnform-transfer.lock',
            '.opnform-transfer-staging', '.opnform-transfer-quarantine'}


def canonical(value):
    return json.dumps(value, sort_keys=True, ensure_ascii=False,
                      separators=(',', ':')).encode()


def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()


def file_hash(path):
    h = hashlib.sha256()
    with path.open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(block)
    return h.hexdigest()


def key(value):
    if (not isinstance(value, str) or not value or '\\' in value or '\x00' in value
            or any(ord(c) < 32 for c in value) or value.startswith('/')
            or any(p in ('', '.', '..') for p in value.split('/'))
            or PurePosixPath(value).parts[0] in INTERNAL):
        raise ValueError('Unsafe or reserved object key')
    return value


def atomic_json(path, value):
    fd, name = tempfile.mkstemp(prefix='.opnform-json-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(canonical(value) + b'\n')
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(name, path)
    finally:
        Path(name).unlink(missing_ok=True)


def regular(path):
    s = path.lstat()
    if not stat.S_ISREG(s.st_mode) or s.st_nlink != 1:
        raise ValueError('Only regular non-hardlinked files are supported')
    return s


def root(path):
    path = Path(path).absolute()
    # Reject symlink components rather than resolving them into another tree.
    for part in [path, *path.parents]:
        if part.is_symlink():
            raise ValueError('Symlink root component refused')
    if not path.is_dir():
        raise ValueError('Existing synthetic root required')
    regular(path / MARKER)
    marker = json.loads((path / MARKER).read_text())
    if set(marker) != {'dataset', 'root_id'} or marker['dataset'] != 'synthetic-only':
        raise ValueError('Only synthetic-only roots are authorized')
    if str(uuid.UUID(marker['root_id'])) != marker['root_id']:
        raise ValueError('Invalid root identity')
    return path, marker


def initialize(path):
    path = Path(path).absolute()
    path = path.parent.resolve(strict=True) / path.name
    # Never label an existing directory: callers must supply a fresh path.
    path.mkdir(mode=0o700, parents=False, exist_ok=False)
    atomic_json(path / MARKER, {'dataset': 'synthetic-only', 'root_id': str(uuid.uuid4())})
    atomic_json(path / METADATA, {})
    return path


def metadata(path):
    regular(path / METADATA)
    data = json.loads((path / METADATA).read_text())
    if not isinstance(data, dict):
        raise ValueError('Invalid metadata map')
    for name, headers in data.items():
        key(name)
        if (not isinstance(headers, dict)
                or any(not isinstance(k, str) or not isinstance(v, str)
                       for k, v in headers.items())):
            raise ValueError('Metadata keys and values must be strings')
    return data


def manifest(path):
    path, identity = root(path)
    headers = metadata(path)
    files = []
    for directory, dirs, names in os.walk(path, followlinks=False):
        for name in dirs[:]:
            d = Path(directory) / name
            if d.is_symlink():
                raise ValueError('Symlink directory refused')
            if d.parent == path and name in INTERNAL:
                dirs.remove(name)
        for name in names:
            f = Path(directory) / name
            if f.parent == path and name in INTERNAL:
                regular(f)
                continue
            relative = key(f.relative_to(path).as_posix())
            before = regular(f)
            checksum = file_hash(f)
            after = regular(f)
            if (before.st_ino, before.st_size, before.st_mtime_ns) != (
                    after.st_ino, after.st_size, after.st_mtime_ns):
                raise ValueError('File changed during hashing; freeze writers and retry')
            files.append({'key': relative, 'bytes': after.st_size,
                          'sha256': checksum, 'metadata': headers.get(relative, {})})
    if set(headers) - {item['key'] for item in files}:
        raise ValueError('Metadata refers to missing objects')
    return {'schema': 1, 'root_id': identity['root_id'], 'dataset': 'synthetic-only',
            'files': sorted(files, key=lambda x: x['key'])}


def same_objects(left, right):
    return left['files'] == right['files']


def plan(source, target):
    src, dst = manifest(source), manifest(target)
    sp, _ = root(source)
    dp, _ = root(target)
    if src['root_id'] == dst['root_id'] or sp == dp or sp in dp.parents or dp in sp.parents:
        raise ValueError('Source and target must be distinct non-nested roots')
    old = {i['key']: i for i in dst['files']}
    new = {i['key']: i for i in src['files']}
    # New file / existing directory prefix collisions cannot be committed safely.
    combined = set(old) | set(new)
    if any(str(parent) in combined for k in combined for parent in PurePosixPath(k).parents
           if str(parent) != '.'):
        raise ValueError('Object/file prefix collision requires a fresh target tree')
    return {'schema': 1, 'dataset': 'synthetic-only', 'source': src, 'target': dst,
            'source_sha256': digest(src), 'target_sha256': digest(dst),
            'copy': sorted(k for k in new if old.get(k) != new[k]),
            'removed': sorted(set(old) - set(new)),
            'unchanged': sum(old.get(k) == new[k] for k in new)}


def apply(source, target, proposal, quarantine=False):
    sp, _ = root(source)
    dp, _ = root(target)
    # Locks protect cooperating runs; external writers must also be frozen.
    with ExitStack() as locks:
        for folder in sorted([sp, dp]):
            lockpath = folder / '.opnform-transfer.lock'
            fd = os.open(lockpath, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
            stream = locks.enter_context(os.fdopen(fd, 'a+'))
            regular(lockpath)
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
        current = plan(sp, dp)
        if proposal != current:
            raise ValueError('Stale or altered plan; regenerate after freezing writers')
        stage = dp / '.opnform-transfer-staging'
        stage.mkdir(mode=0o700, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=stage) as directory:
            staging = Path(directory)
            expected = {i['key']: i for i in proposal['source']['files']}
            for name in proposal['copy']:
                f = staging / name
                f.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(sp / name, f)
                actual = file_hash(f)
                if actual != expected[name]['sha256']:
                    raise ValueError('Staged content checksum mismatch')
            if proposal != plan(sp, dp):
                raise ValueError('Concurrent change during staging; no objects committed')
            # A process crash can leave a partial commit. Verification/next plan must
            # reconcile it; this is intentionally not advertised as a transaction.
            for name in proposal['copy']:
                f = dp / name
                f.parent.mkdir(parents=True, exist_ok=True)
                os.replace(staging / name, f)
            quarantined = []
            if quarantine and proposal['removed']:
                trash = dp / '.opnform-transfer-quarantine' / str(uuid.uuid4())
                trash.mkdir(mode=0o700, parents=True)
                for name in proposal['removed']:
                    f = trash / name
                    f.parent.mkdir(parents=True, exist_ok=True)
                    os.replace(dp / name, f)
                    quarantined.append(name)
                atomic_json(trash / METADATA, {
                    'removed': [x for x in proposal['target']['files'] if x['key'] in quarantined]})
            target_headers = metadata(dp)
            for name in proposal['copy']:
                target_headers[name] = expected[name]['metadata']
            for name in quarantined:
                target_headers.pop(name, None)
            atomic_json(dp / METADATA, target_headers)
        after = manifest(dp)
        expected = {i['key']: i for i in proposal['source']['files']}
        actual = {i['key']: i for i in after['files']}
        if any(actual.get(k) != v for k, v in expected.items()):
            raise ValueError('Post-copy integrity check failed')
        if quarantine and not same_objects(proposal['source'], after):
            raise ValueError('Final delta mismatch')
        return {'status': 'verified-synthetic-copy', 'copied': len(proposal['copy']),
                'quarantined': len(quarantined), 'retained_extras': len(set(actual) - set(expected)),
                'exact_match': same_objects(proposal['source'], after),
                'manifest_sha256': digest(after), 'cloud_semantics_verified': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='action', required=True)
    for action in ['init', 'manifest']:
        command = sub.add_parser(action)
        command.add_argument('root', type=Path)
    for action in ['plan', 'apply']:
        command = sub.add_parser(action)
        command.add_argument('source', type=Path)
        command.add_argument('target', type=Path)
        if action == 'apply':
            command.add_argument('plan', type=Path)
            command.add_argument('--quarantine-removed', action='store_true')
    args = parser.parse_args()
    try:
        if args.action == 'init':
            print(json.dumps({'created': str(initialize(args.root)), 'dataset': 'synthetic-only'}))
        elif args.action == 'manifest':
            print(json.dumps(manifest(args.root), ensure_ascii=False, indent=2))
        elif args.action == 'plan':
            print(json.dumps(plan(args.source, args.target), ensure_ascii=False, indent=2))
        else:
            print(json.dumps(apply(args.source, args.target,
                json.loads(args.plan.read_text()), args.quarantine_removed), indent=2))
    except (ValueError, OSError, KeyError) as error:
        parser.exit(2, f'Transfer refused: {error}\n')


if __name__ == '__main__':
    main()
