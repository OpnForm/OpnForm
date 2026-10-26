#!/usr/bin/env python3
"""Publish only the tested local image pair; never contact Coolify or AWS."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile


def command(*args):
    result = subprocess.run(args, capture_output=True, text=True, timeout=900)
    if result.returncode:
        raise RuntimeError(args[0] + ' failed; inspect the scoped CI step')
    return result.stdout.strip()


def package_is_private(name, allow_missing=False):
    response = subprocess.run(['gh', 'api', '/orgs/opnform/packages/container/' + name],
                              capture_output=True, text=True, timeout=30)
    if response.returncode:
        if allow_missing and '(HTTP 404)' in response.stderr:
            return
        raise RuntimeError('Cannot verify the private registry package')
    if json.loads(response.stdout).get('visibility') != 'private':
        raise RuntimeError('Hosted images must remain private')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    sha = os.environ['GITHUB_SHA']
    if (os.environ.get('GITHUB_REPOSITORY') != 'OpnForm/OpnForm'
            or os.environ.get('GITHUB_REF') != 'refs/heads/main'
            or os.environ.get('GITHUB_EVENT_NAME') != 'push'
            or not re.fullmatch('[0-9a-f]{40}', sha)):
        raise RuntimeError('Only a tested main push can publish a hosted release')
    images = {}
    for role, local, package in [('backend', 'opnform-scaleway-api:local', 'hosted-backend'),
                                 ('frontend', 'opnform-scaleway-client:local', 'hosted-frontend')]:
        image = json.loads(command('docker', 'image', 'inspect', local))[0]
        if (image['Config']['Labels'].get('org.opencontainers.image.revision') != sha
                or image['Architecture'] != 'amd64' or image['Os'] != 'linux'):
            raise RuntimeError('The tested image does not match the release revision/platform')
        package_is_private(package, allow_missing=True)
        tag = 'ghcr.io/opnform/' + package + ':' + sha
        command('docker', 'tag', image['Id'], tag)
        command('docker', 'push', tag)
        package_is_private(package)
        refs = json.loads(command('docker', 'image', 'inspect', tag))[0]['RepoDigests']
        expected = 'ghcr.io/opnform/' + package + '@sha256:'
        matches = [ref for ref in refs if ref.startswith(expected)]
        if len(matches) != 1 or not re.fullmatch(re.escape(expected) + '[0-9a-f]{64}', matches[0]):
            raise RuntimeError('The published image has no unambiguous immutable digest')
        images[role] = matches[0]
    args.output.write_text(json.dumps({
        'schema': 'opnform-coolify-service-release/v1', 'repository': 'OpnForm/OpnForm',
        'sha': sha, 'ref': 'refs/heads/main', 'run_id': int(os.environ['GITHUB_RUN_ID']),
        'run_attempt': int(os.environ['GITHUB_RUN_ATTEMPT']), 'images': images,
        'automatic_deployment': True,
    }, indent=2) + '\n')
    # The controller reads this tiny image without starting it. Its credentials
    # remain private on the controller; CI never receives Coolify/SSH access.
    package_is_private('hosted-release', allow_missing=True)
    with tempfile.TemporaryDirectory(prefix='opnform-release-') as directory:
        context = Path(directory)
        (context / 'release.json').write_bytes(args.output.read_bytes())
        (context / 'Dockerfile').write_text('FROM scratch\n'
            'LABEL org.opencontainers.image.source="https://github.com/OpnForm/OpnForm" '
            'org.opencontainers.image.revision="' + sha + '"\n'
            'COPY release.json /release.json\n')
        immutable = 'ghcr.io/opnform/hosted-release:' + sha + '-' + os.environ['GITHUB_RUN_ID'] + '-' + os.environ['GITHUB_RUN_ATTEMPT']
        command('docker', 'build', '-t', immutable, str(context))
        command('docker', 'push', immutable)
        package_is_private('hosted-release')
        if json.loads(command('gh', 'api', '/repos/OpnForm/OpnForm/commits/main'))['sha'] != sha:
            raise RuntimeError('A newer main exists; do not publish a stale deployment channel')
        command('docker', 'tag', immutable, 'ghcr.io/opnform/hosted-release:production')
        command('docker', 'push', 'ghcr.io/opnform/hosted-release:production')
    print('Validated private release published; the controller deploys after this main CI succeeds.')


if __name__ == '__main__':
    main()
