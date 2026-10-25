#!/usr/bin/env python3
"""Bind the embedded-image regression check to committed runtime source files."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', required=True, type=Path)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
    if sha != os.environ['GITHUB_SHA']:
        raise RuntimeError('Checkout does not match the CI revision')
    names = subprocess.check_output(['git', 'ls-files', '-z', 'api/artisan', 'api/composer.json', 'api/composer.lock', 'api/app', 'api/bootstrap',
        'api/config', 'api/database', 'api/public', 'api/resources', 'api/routes',
        'deploy/scaleway'], cwd=root).decode().split('\0')
    hashes = {name: hashlib.sha256((root / name).read_bytes()).hexdigest()
              for name in names if name and (root / name).is_file()}
    args.output_dir.mkdir(parents=True, exist_ok=True)
    (args.output_dir / 'proof.json').write_text(json.dumps({
        'source_hashes': hashes, 'source_revision_marker': sha,
        'real_release_commit': sha, 'source_base_commit': sha,
    }, indent=2) + '\n')


if __name__ == '__main__':
    main()
