#!/usr/bin/env python3
"""Real PostgreSQL + local-file cutover rehearsal, generated fixtures only.

Uses one new, network-disabled, disposable PostgreSQL container. Never loads a
source DSN, cloud credential, production database, queue or routing configuration.
"""
import argparse
import datetime
import hashlib
import importlib.util
import json
from pathlib import Path
import socket
import subprocess
import time
import uuid

spec = importlib.util.spec_from_file_location('transfer', Path(__file__).with_name('object-transfer.py'))
transfer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(transfer)


class Journal:
    """Rehearsal decisions only; declared facts are not production attestations."""
    def __init__(self):
        self.phase = 'source-writable'
        self.target_writes = 0
        self.events = []

    def advance(self, expected, next_phase):
        if self.phase not in expected:
            raise ValueError('Invalid cutover transition from ' + self.phase)
        self.phase = next_phase
        self.events.append(next_phase)

    def freeze_source(self):
        self.advance({'source-writable'}, 'source-frozen')

    def verify_target(self, *, sql, files, queues):
        if not all([sql, files, queues]):
            raise ValueError('Target SQL, files and queue evidence required')
        self.advance({'source-frozen'}, 'target-ready')

    def activate_target(self):
        self.advance({'target-ready'}, 'target-writable')

    def note_target_write(self):
        if self.phase != 'target-writable':
            raise ValueError('Target is not writable')
        self.target_writes += 1

    def rollback_simple(self):
        if self.target_writes:
            raise ValueError('New target writes require frozen reverse SQL/files/queue reconciliation')
        self.advance({'target-ready', 'target-writable'}, 'source-writable')

    def freeze_target(self):
        self.advance({'target-writable'}, 'target-frozen')

    def verify_reverse(self, *, sql, files, queues):
        if not all([sql, files, queues]):
            raise ValueError('Reverse SQL, files and queue evidence required')
        self.advance({'target-frozen'}, 'reverse-ready')

    def resume_source(self):
        self.advance({'reverse-ready'}, 'source-writable')


def exercise(output, expected_host='opnform-scaleway'):
    if socket.gethostname().split('.')[0] != expected_host:
        raise ValueError('Run only on the explicitly selected preparation host')
    output = Path(output).absolute()
    output.mkdir(mode=0o700, parents=False, exist_ok=False)
    token = uuid.uuid4().hex[:12]
    container = 'opnform-cutover-fixture-' + token
    databases = {role: 'fixture_' + role + '_' + token for role in ('source', 'target', 'reverse')}
    durations = {}
    start = time.monotonic()
    journal = Journal()

    def docker(*args, data=None, check=True):
        p = subprocess.run(['docker', *args], input=data, capture_output=True,
                           timeout=90, check=False)
        if check and p.returncode:
            raise RuntimeError('Synthetic Docker operation failed: ' + args[0])
        return p

    def pg(*args, data=None, check=True):
        return docker('exec', *(['-i'] if data is not None else []), container,
                      *args, data=data, check=check)

    def sql(role, query, check=True):
        db = databases[role]
        return pg('psql', '-X', '-U', 'postgres', '-d', db, '-At',
                  '-v', 'ON_ERROR_STOP=1', '-c', query, check=check)

    def fingerprint(role):
        result = {}
        for table in ('submissions', 'jobs'):
            result[table] = sql(role, 'SELECT count(*)::text||\':\'||md5(coalesce('
                'jsonb_agg(to_jsonb(t) ORDER BY id)::text,\'[]\')) FROM ' + table + ' t').stdout.decode().strip()
        result['sequence'] = sql(role, 'SELECT last_value::text||\':\'||is_called::text FROM submissions_id_seq').stdout.decode().strip()
        return result

    def snapshot(source, target):
        clock = time.monotonic()
        blob = pg('pg_dump', '-U', 'postgres', '-d', databases[source],
                  '-Fc', '--no-owner', '--no-privileges').stdout
        if len(blob) > 8 * 1024 * 1024:
            raise ValueError('Synthetic SQL dump size limit')
        pg('pg_restore', '-U', 'postgres', '-d', databases[target], '--exit-on-error',
           '--no-owner', '--no-privileges', data=blob)
        durations[source + '-to-' + target + '-sql-seconds'] = round(time.monotonic() - clock, 3)
        return hashlib.sha256(blob).hexdigest()

    def freeze(role):
        db = databases[role]
        pg('psql', '-X', '-U', 'postgres', '-d', 'postgres', '-v', 'ON_ERROR_STOP=1',
           '-c', f'ALTER DATABASE {db} SET default_transaction_read_only=on')
        # Only this new fixture DB. Existing transactions must not bypass the fence.
        pg('psql', '-X', '-U', 'postgres', '-d', 'postgres', '-v', 'ON_ERROR_STOP=1',
           '-c', f"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='{db}' AND pid<>pg_backend_pid()")
        assert sql(role, 'SHOW transaction_read_only').stdout.strip() == b'on'
        assert sql(role, "INSERT INTO jobs VALUES ('forbidden-write','pending',0)", check=False).returncode != 0

    def unfreeze(role):
        pg('psql', '-X', '-U', 'postgres', '-d', 'postgres', '-v', 'ON_ERROR_STOP=1',
           '-c', f'ALTER DATABASE {databases[role]} RESET default_transaction_read_only')

    source = transfer.initialize(output / 'source-objects')
    target = transfer.initialize(output / 'target-objects')

    def put(folder, name, data, headers=None):
        f = folder / name
        f.parent.mkdir(parents=True, exist_ok=True)
        f.write_bytes(data)
        values = transfer.metadata(folder)
        values[name] = headers or {}
        transfer.atomic_json(folder / transfer.METADATA, values)

    def remove(folder, name):
        (folder / name).unlink()
        values = transfer.metadata(folder)
        values.pop(name, None)
        transfer.atomic_json(folder / transfer.METADATA, values)

    def matching_files():
        return transfer.same_objects(transfer.manifest(source), transfer.manifest(target))

    def references(role, folder):
        rows = sql(role, 'SELECT DISTINCT object_key||\'|\'||object_sha256 FROM submissions').stdout.decode().splitlines()
        objects = {item['key']: item['sha256'] for item in transfer.manifest(folder)['files']}
        return all(objects.get(k) == sha for k, sha in (row.split('|') for row in rows))

    created = False
    try:
        # Pin the cached image ID; --pull=never forbids an accidental registry call.
        image = json.loads(docker('image', 'inspect', 'postgres:16-bookworm').stdout)[0]['Id']
        docker('run', '-d', '--pull=never', '--name', container,
               '--label', 'opnform.preparation=cutover-fixture', '--network', 'none',
               '--read-only', '--user', 'postgres', '--cap-drop=ALL', '--security-opt',
               'no-new-privileges', '--tmpfs', '/var/lib/postgresql/data:rw,uid=999,gid=999,mode=0700',
               '--tmpfs', '/var/run/postgresql:rw,uid=999,gid=999', '--tmpfs', '/tmp',
               '-e', 'POSTGRES_HOST_AUTH_METHOD=trust', image)
        created = True
        until = time.monotonic() + 40
        while pg('pg_isready', '-U', 'postgres', check=False).returncode:
            if time.monotonic() > until:
                raise RuntimeError('Disposable PostgreSQL startup timed out')
            time.sleep(.3)
        for name in databases.values():
            pg('createdb', '-U', 'postgres', name)
        isolation = json.loads(docker('inspect', container).stdout)[0]
        assert isolation['HostConfig']['NetworkMode'] == 'none' and not isolation['NetworkSettings']['Ports']

        content = b'SYNTHETIC ORIGINAL\x00' + bytes(range(256))
        sha = hashlib.sha256(content).hexdigest()
        put(source, 'uploads/original.bin', content, {'content-type': 'application/octet-stream'})
        put(source, 'obsolete.txt', b'obsolete fixture')
        put(source, 'delete-on-target.txt', b'reverse tombstone fixture')
        put(source, 'empty', b'')
        schema = f"""CREATE TABLE submissions(id bigserial PRIMARY KEY, label text NOT NULL,
            payload jsonb NOT NULL, object_key text NOT NULL, object_sha256 text NOT NULL);
            CREATE TABLE jobs(id text PRIMARY KEY,status text NOT NULL CHECK(status IN ('pending','done')),attempts integer NOT NULL);
            INSERT INTO submissions(label,payload,object_key,object_sha256)
            SELECT 'FICTIF é 漢字 '||g,jsonb_build_object('synthetic',true,'n',g),
            'uploads/original.bin','{sha}' FROM generate_series(1,1000) g;
            INSERT INTO jobs VALUES ('source-job-1','pending',0),('source-job-2','pending',0);"""
        sql('source', schema)
        before = transfer.manifest(source)
        transfer.atomic_json(output / 'precopy-source-manifest.json', before)
        precopy_plan = transfer.plan(source, target)
        clock = time.monotonic()
        precopy = transfer.apply(source, target, precopy_plan)
        durations['precopy-files-seconds'] = round(time.monotonic() - clock, 3)
        assert precopy['exact_match']

        # Changes between precopy and final freeze: add, modify, delete, headers.
        put(source, 'uploads/original.bin', b'SYNTHETIC CHANGED\x00' + bytes(range(256)),
            {'content-type': 'application/octet-stream', 'cache-control': 'private,max-age=0'})
        new_sha = transfer.file_hash(source / 'uploads/original.bin')
        sql('source', f"UPDATE submissions SET object_sha256='{new_sha}'")
        put(source, 'signatures/é 漢字.png', b'fictional PNG fixture', {'content-type': 'image/png'})
        sig_sha = transfer.file_hash(source / 'signatures/é 漢字.png')
        sql('source', f"INSERT INTO submissions(label,payload,object_key,object_sha256) VALUES ('delta', '{{\"synthetic\":true}}','signatures/é 漢字.png','{sig_sha}')")
        remove(source, 'obsolete.txt')
        # A fixture ledger models drain/reconciliation, NOT actual SQS delivery.
        sql('source', "UPDATE jobs SET status='done',attempts=attempts+1 WHERE status='pending'")
        assert sql('source', "SELECT count(*) FROM jobs WHERE status='pending'").stdout.strip() == b'0'
        frozen_started = time.monotonic()
        freeze('source')
        journal.freeze_source()
        final_plan = transfer.plan(source, target)
        transfer.atomic_json(output / 'final-delta-plan.json', final_plan)
        final_delta = transfer.apply(source, target, final_plan, quarantine=True)
        initial_dump_sha = snapshot('source', 'target')
        sql_equal = fingerprint('source') == fingerprint('target')
        assert references('target', target)
        journal.verify_target(sql=sql_equal, files=matching_files(), queues=True)
        durations['source-freeze-to-target-verified-seconds'] = round(time.monotonic() - frozen_started, 3)
        journal.rollback_simple()
        unfreeze('source')
        # Re-enter the frozen checkpoint to rehearse the post-write path as well.
        freeze('source')
        journal.freeze_source()
        journal.verify_target(sql=sql_equal, files=matching_files(), queues=True)
        journal.activate_target()
        put(target, 'answers/target-only.txt', 'FICTIF écrit après bascule'.encode(), {'content-type': 'text/plain'})
        target_sha = transfer.file_hash(target / 'answers/target-only.txt')
        written_id = sql('target', f"INSERT INTO submissions(label,payload,object_key,object_sha256) VALUES ('target-write','{{\"synthetic\":true}}','answers/target-only.txt','{target_sha}') RETURNING id").stdout.decode().splitlines()[0]
        assert written_id == '1002'
        sql('target', "INSERT INTO jobs VALUES ('target-job','pending',0)")
        remove(target, 'delete-on-target.txt')
        journal.note_target_write()
        try:
            journal.rollback_simple()
        except ValueError:
            unsafe_rollback_refused = True
        else:
            raise AssertionError('Post-write routing-only rollback was allowed')
        reverse_started = time.monotonic()
        freeze('target')
        journal.freeze_target()
        reverse_plan = transfer.plan(target, source)
        transfer.atomic_json(output / 'reverse-delta-plan.json', reverse_plan)
        reverse_files = transfer.apply(target, source, reverse_plan, quarantine=True)
        reverse_dump_sha = snapshot('target', 'reverse')
        reverse_equal = fingerprint('target') == fingerprint('reverse')
        assert references('reverse', source)
        pending = sql('reverse', "SELECT id FROM jobs WHERE status='pending'").stdout.strip()
        assert pending == b'target-job'
        journal.verify_reverse(sql=reverse_equal, files=matching_files(), queues=pending == b'target-job')
        durations['target-freeze-to-reverse-verified-seconds'] = round(time.monotonic() - reverse_started, 3)
        journal.resume_source()
        # The resumed source is the restored shadow DB, not the stale original.
        next_id = sql('reverse', f"INSERT INTO submissions(label,payload,object_key,object_sha256) VALUES ('after-rollback','{{\"synthetic\":true}}','answers/target-only.txt','{target_sha}') RETURNING id").stdout.decode().splitlines()[0]
        assert next_id == '1003'
        sql('reverse', "UPDATE jobs SET status='done',attempts=attempts+1 WHERE id='target-job' AND status='pending'")
        sql('reverse', "UPDATE jobs SET status='done',attempts=attempts+1 WHERE id='target-job' AND status='pending'")
        assert sql('reverse', "SELECT attempts FROM jobs WHERE id='target-job'").stdout.strip() == b'1'
        assert sql('reverse', "SELECT count(*) FROM submissions").stdout.strip() == b'1003'
        proof = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                 'dataset': 'synthetic-only', 'client_data': False, 'image_id': image,
                 'postgres_version': sql('reverse', 'SHOW server_version').stdout.decode().strip(),
                 'database_isolation': 'new network-none tmpfs container, three fixture databases',
                 'precopy': precopy, 'final_delta': final_delta, 'reverse_files': reverse_files,
                 'initial_sql_dump_sha256': initial_dump_sha, 'reverse_sql_dump_sha256': reverse_dump_sha,
                 'initial_sql_fingerprint_match': sql_equal, 'reverse_sql_fingerprint_match': reverse_equal,
                 'target_write_id': written_id, 'resumed_source_next_id': next_id,
                 'resumed_source_rows': 1003, 'restored_target_job_attempts': 1,
                 'frozen_db_writes_rejected': True, 'simple_postwrite_rollback_rejected': unsafe_rollback_refused,
                 'events': journal.events, 'durations': durations,
                 'total_seconds': round(time.monotonic() - start, 3),
                 'production_readiness_proven': False,
                 'limits': ['SQL/files modeled as separate roots/databases on one host; no real traffic switch',
                           'Object headers modeled in local sidecar; no cloud versioning/CORS/signed URL proof',
                           'Fixture SQL job ledger is not SQS/Redis exactly-once proof',
                           'Synthetic timing does not establish production RPO/RTO']}
        transfer.atomic_json(output / 'proof.json', proof)
        return proof
    finally:
        if created:
            docker('rm', '-f', '-v', container)
        if docker('container', 'inspect', container, check=False).returncode == 0:
            raise RuntimeError('Disposable fixture container cleanup failed')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--expected-host', default='opnform-scaleway')
    args = parser.parse_args()
    proof = exercise(args.output, args.expected_host)
    proof['fixture_container_removed'] = True
    transfer.atomic_json(args.output / 'proof.json', proof)
    print(json.dumps(proof, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
