#!/usr/bin/env python3
"""Validate an immutable release candidate and print promotion order; never deploys."""
import argparse
import json
import re
from pathlib import Path


def plan(candidate):
    commit = candidate.get('commit', '')
    if not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ValueError('A full source commit is required')
    if candidate.get('environment') not in ('preview', 'production'):
        raise ValueError('Explicit preview or production environment required')
    images = candidate.get('images', {})
    for name in ('backend', 'frontend'):
        image = images.get(name, '')
        if not re.fullmatch(r'ghcr\.io/opnform/[a-z0-9._/-]+@sha256:[0-9a-f]{64}', image):
            raise ValueError('Both images must be explicit OpnForm GHCR digests')
    checks = candidate.get('checks', {})
    for name in ('backend', 'frontend', 'lint', 'runtime-integration', 'private-packages'):
        result = checks.get(name, {})
        if result.get('status') != 'success' or result.get('commit') != commit:
            raise ValueError(f'Missing successful check for the same commit: {name}')
    integration = checks['runtime-integration']
    if integration.get('images') != images:
        raise ValueError('Integration must cover these exact image digests')
    return [
        {'role': role, 'image': images['frontend' if role == 'frontend' else 'backend'],
         'must_be_healthy_before_next': True}
        for role in ('migrate', 'jobs', 'scheduler', 'api', 'frontend')
    ]


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('manifest', type=Path)
    args = parser.parse_args()
    try:
        result = plan(json.loads(args.manifest.read_text()))
    except (ValueError, OSError) as error:
        raise SystemExit(str(error))
    print(json.dumps({'status': 'validated-plan-only', 'steps': result}, indent=2))
