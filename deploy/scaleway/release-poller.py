#!/usr/bin/env python3
"""Outbound-only release intake. No listener, downloaded scripts, or GitHub network access."""
import base64
import fcntl
import hashlib
import importlib.util
import json
import os
import re
import time
import urllib.request
from pathlib import Path

spec = importlib.util.spec_from_file_location('deploy', Path(__file__).with_name('deploy-preview.py'))
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)
PACKAGE = 'opnform/hosted-backend'
TYPE = 'application/vnd.opnform.preview-release.v1+json'
TAG = 'preview-ready'
RELEASE_ENVIRONMENT = 'opnform-preview'
ALLOWED_REFS = ('refs/heads/codex/scaleway-preparation-20261016',)


def configure_target():
    """Production needs a separate service, explicit arming and private resource IDs."""
    global TYPE, TAG, RELEASE_ENVIRONMENT, ALLOWED_REFS
    target = os.environ.get('OPNFORM_RELEASE_ENVIRONMENT', 'preview')
    if target == 'preview':
        return
    if target != 'production' or os.environ.get('OPNFORM_PRODUCTION_ENABLED') != 'true':
        raise RuntimeError('Production release controller is not armed')
    profile_spec = importlib.util.spec_from_file_location('production', Path(__file__).with_name('production-target.py'))
    profile = importlib.util.module_from_spec(profile_spec)
    profile_spec.loader.exec_module(profile)
    profile.configure(deploy, Path(os.environ['OPNFORM_PRODUCTION_TARGETS']))
    TYPE = 'application/vnd.opnform.production-release.v1+json'
    TAG = 'production-ready'
    RELEASE_ENVIRONMENT = 'opnform-production'
    ALLOWED_REFS = ('refs/heads/main',)


def get(url, token, limit=65536):
    request = urllib.request.Request(url, headers={'Authorization': token,
        'Accept': 'application/vnd.oci.image.manifest.v1+json'})
    with urllib.request.urlopen(request, timeout=30) as response:
        data = response.read(limit + 1)
        if len(data) > limit:
            raise RuntimeError('Oversized release metadata')
        return data


def fetch_release():
    credentials = os.environ['GHCR_USERNAME'] + ':' + os.environ['GHCR_TOKEN']
    authorization = 'Basic ' + base64.b64encode(credentials.encode()).decode()
    token = json.loads(get('https://ghcr.io/token?service=ghcr.io&scope=repository:' + PACKAGE + ':pull', authorization))['token']
    authorization = 'Bearer ' + token
    manifest = json.loads(get('https://ghcr.io/v2/' + PACKAGE + '/manifests/' + TAG, authorization))
    if manifest.get('artifactType') != TYPE or len(manifest.get('layers', [])) != 1:
        raise RuntimeError('Unexpected release artifact type')
    layer = manifest['layers'][0]
    if not re.fullmatch(r'sha256:[0-9a-f]{64}', layer['digest']) or layer['mediaType'] != 'application/json':
        raise RuntimeError('Invalid release descriptor')
    data = get('https://ghcr.io/v2/' + PACKAGE + '/blobs/' + layer['digest'], authorization)
    if 'sha256:' + hashlib.sha256(data).hexdigest() != layer['digest']:
        raise RuntimeError('Release blob digest mismatch')
    release = json.loads(data)
    if release.get('environment') != RELEASE_ENVIRONMENT or release.get('repository') != 'OpnForm/OpnForm':
        raise RuntimeError('Refusing release for another environment')
    if release.get('ref') not in ALLOWED_REFS:
        raise RuntimeError('Refusing release from another branch')
    for key in ('run_id', 'run_attempt'):
        if not isinstance(release.get(key), int) or release[key] <= 0:
            raise RuntimeError('Invalid release sequence')
    gate_spec = importlib.util.spec_from_file_location('gate', Path(__file__).with_name('release-plan.py'))
    gate = importlib.util.module_from_spec(gate_spec)
    gate_spec.loader.exec_module(gate)
    gate.plan({'commit': release.get('sha'), 'environment': RELEASE_ENVIRONMENT.removeprefix('opnform-'),
               'images': {'backend': 'ghcr.io/opnform/hosted-backend@' + release.get('backend_digest', ''),
                          'frontend': 'ghcr.io/opnform/hosted-frontend@' + release.get('frontend_digest', '')},
               'checks': release.get('checks', {})})
    return release, layer['digest']


def main():
    if os.environ.get('OPNFORM_DEPLOY_ENABLED') != 'true':
        print('OpnForm release controller is disarmed; no remote request made', flush=True)
        return
    configure_target()
    state_dir = Path(os.environ['STATE_DIRECTORY'])
    os.chdir(state_dir)
    with Path('poller.lock').open('w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        state_path = Path('poller-state.json')
        state = json.loads(state_path.read_text()) if state_path.exists() else {}
        try:
            release, digest = fetch_release()
        except Exception as error:
            state['poll_error'] = type(error).__name__
            state['checked_at'] = time.time()
            state_path.write_text(json.dumps(state, indent=2))
            print('Release registry check failed: ' + type(error).__name__, flush=True)
            raise SystemExit(1)
        state.pop('poll_error', None)
        state['checked_at'] = time.time()
        state_path.write_text(json.dumps(state, indent=2))
        try:
            sequence = [release['run_id'], release['run_attempt']]
            if sequence <= state.get('sequence', [0, 0]):
                # Failed releases are intentionally NOT retried automatically.
                # Interrupted releases require inspection before a rerun as well.
                return
            # Preserve the previous journal and sequence while a remote job is
            # active. This also covers jobs surviving a controller crash.
            client = deploy.Coolify()
            previous_path = Path('release-result.json')
            try:
                previous = None
                if state.get('status') in ('failed', 'deploying'):
                    previous = json.loads(previous_path.read_text())
                deploy.assert_no_active_deployments(client, previous)
            except Exception as error:
                state['poll_error'] = type(error).__name__
                state_path.write_text(json.dumps(state, indent=2))
                print('Release blocked by unresolved Coolify deployment state: ' + type(error).__name__, flush=True)
                raise SystemExit(1)
            if previous_path.exists():
                history = Path('history')
                history.mkdir(exist_ok=True)
                old_sequence = state.get('sequence', [0, 0])
                (history / ('-'.join(map(str, old_sequence)) + '.json')).write_text(
                    json.dumps({'state': state, 'result': json.loads(previous_path.read_text())}, indent=2))
            state = {'sequence': sequence, 'manifest_digest': digest, 'status': 'deploying', 'started_at': time.time()}
            state_path.write_text(json.dumps(state, indent=2))
            result = {'status': 'running', 'sha': release['sha'], 'previous': {},
                      'deployments': {}, 'completed': [], 'migration_succeeded': False,
                      'backend_digest': release['backend_digest'], 'frontend_digest': release['frontend_digest']}
            try:
                deploy.run(client, release['backend_digest'], release['frontend_digest'], release['sha'], result)
            except Exception:
                result['status'] = 'failed'
                deploy.save(result)
                raise
            state['status'] = 'success'
            state['finished_at'] = time.time()
            state_path.write_text(json.dumps(state, indent=2))
        except Exception as error:
            state['status'] = 'failed'
            state['last_error_class'] = type(error).__name__
            state['failed_at'] = time.time()
            state_path.write_text(json.dumps(state, indent=2))
            # Avoid remote response bodies, token-bearing URLs and env in journals.
            print('OpnForm release failed: ' + type(error).__name__, flush=True)
            raise SystemExit(1)


if __name__ == '__main__':
    main()
