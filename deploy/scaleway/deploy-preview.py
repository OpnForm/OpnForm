#!/usr/bin/env python3
"""Promote one immutable release to the explicitly allowlisted preview resources."""
import ipaddress
import json
import os
import re
import time
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

# Concrete infrastructure identifiers live only in the private controller env.
ENVIRONMENT = os.environ.get('OPNFORM_PREVIEW_ENVIRONMENT', '')
SERVER = os.environ.get('OPNFORM_ALLOWED_SERVER', '')
TEAM_ID = int(os.environ.get('OPNFORM_ALLOWED_TEAM_ID', '-1'))
APPS = {role: os.environ.get('OPNFORM_PREVIEW_' + role.upper() + '_UUID', '')
        for role in ('api', 'frontend', 'jobs', 'scheduler', 'migrate')}
LEGACY_SERVICE = os.environ.get('OPNFORM_LEGACY_SERVICE_UUID') or None
LEGACY_ENVIRONMENT_ID = int(os.environ.get('OPNFORM_LEGACY_ENVIRONMENT_ID', '0'))
LEGACY_SERVER_ID = int(os.environ.get('OPNFORM_LEGACY_SERVER_ID', '0'))
DOMAINS = {'api': 'https://api.preview.opnform.com', 'frontend': 'https://preview.opnform.com'}
TERMINAL_DEPLOYMENTS = {'finished', 'failed', 'cancelled', 'canceled', 'cancelled-by-user'}


class Coolify:
    def __init__(self):
        self.url = os.environ['COOLIFY_URL'].rstrip('/')
        # Plain HTTP is encrypted in transit by the explicitly pinned Tailscale tunnel.
        if self.url != os.environ.get('COOLIFY_ALLOWED_URL', '').rstrip('/'):
            raise RuntimeError('Only the private Coolify Tailscale endpoint is allowed')
        endpoint = urllib.parse.urlsplit(self.url)
        try:
            private_address = ipaddress.ip_address(endpoint.hostname) in ipaddress.ip_network('100.64.0.0/10')
        except ValueError:
            private_address = False
        if (endpoint.scheme != 'http' or endpoint.username or endpoint.password
                or endpoint.path or endpoint.query or endpoint.fragment
                or not private_address):
            raise RuntimeError('Coolify must use the privately pinned Tailscale address')
        self.token = os.environ['COOLIFY_TOKEN']

    def request(self, method, path, data=None):
        if method != 'GET' and os.environ.get('OPNFORM_DEPLOY_ENABLED') != 'true':
            raise RuntimeError('OpnForm deployment is disarmed')
        request = urllib.request.Request(self.url + '/api/v1/' + path,
            data=None if data is None else json.dumps(data).encode(), method=method,
            headers={'Authorization': 'Bearer ' + self.token, 'Content-Type': 'application/json'})
        try:
            with urllib.request.urlopen(request, timeout=45) as response:
                return json.load(response)
        except urllib.error.HTTPError as error:
            # Bodies can contain logs or application env; never emit them in CI.
            raise RuntimeError(f'Coolify {method} {path}: HTTP {error.code}') from None


def validate_target_configuration():
    identifiers = [ENVIRONMENT, SERVER, *APPS.values()]
    if (any(not re.fullmatch(r'[a-z0-9]{20,32}', value) for value in identifiers)
            or len(set(APPS.values())) != 5 or TEAM_ID < 0):
        raise RuntimeError('Missing or invalid private deployment allowlist')
    if LEGACY_SERVICE and (not re.fullmatch(r'[a-z0-9]{20,32}', LEGACY_SERVICE)
                           or LEGACY_ENVIRONMENT_ID <= 0 or LEGACY_SERVER_ID <= 0):
        raise RuntimeError('Incomplete private legacy service allowlist')


def validate_app(role, app):
    if app['uuid'] != APPS[role] or app['environment']['uuid'] != ENVIRONMENT:
        raise RuntimeError('Refusing resource outside the preview allowlist')
    if app['destination']['server']['uuid'] != SERVER:
        raise RuntimeError('Refusing non-Scaleway target')
    if app['environment']['project']['team_id'] != TEAM_ID:
        raise RuntimeError('Wrong Coolify team')
    if (app.get('fqdn') or '') != DOMAINS.get(role, ''):
        raise RuntimeError(f'Unexpected domain on {role}')
    if app['build_pack'] != 'dockerimage' or app['settings']['is_auto_deploy_enabled']:
        raise RuntimeError('All resources must use prebuilt images with auto-deploy disabled')
    if role in ('jobs', 'scheduler', 'migrate'):
        if not app['settings']['is_consistent_container_name_enabled']:
            raise RuntimeError('Background roles must use stop-before-start deployment')
        minimum = 3700 if role in ('jobs', 'scheduler') else 150
        if app['settings'].get('stop_grace_period', 0) < minimum:
            raise RuntimeError('Insufficient graceful stop timeout')


def wait_for(check, description, timeout=1500):
    end = time.monotonic() + timeout
    while time.monotonic() < end:
        if check():
            return
        time.sleep(10)
    raise RuntimeError('Timed out: ' + description)


def assert_no_active_deployments(client, previous=None):
    # A local process/lock ending does not stop Coolify's remote deployment job.
    # A POST with no response is ambiguous: require an operator to reconcile it.
    if previous and previous.get('starting_role'):
        raise RuntimeError('A previous start request has an unknown outcome; inspect it before retrying')
    for uuid in (previous or {}).get('deployments', {}).values():
        status = client.request('GET', 'deployments/' + uuid)['status']
        if status not in TERMINAL_DEPLOYMENTS:
            raise RuntimeError('A previous Coolify deployment is still active')

    # The application endpoint hides numeric IDs. Query each UUID's history;
    # inspect every page so an older still-running job cannot be overlooked.
    for uuid in APPS.values():
        offset = 0
        while True:
            history = client.request('GET', f'deployments/applications/{uuid}?skip={offset}&take=100')
            rows = history.get('deployments') if isinstance(history, dict) else None
            count = history.get('count') if isinstance(history, dict) else None
            if not isinstance(rows, list) or not isinstance(count, int) or count < 0:
                raise RuntimeError('Invalid application deployment inventory')
            if any(not isinstance(item, dict) or item.get('status') not in TERMINAL_DEPLOYMENTS for item in rows):
                raise RuntimeError('A target application already has an active or unknown Coolify deployment')
            offset += len(rows)
            if offset >= count:
                break
            if not rows:
                raise RuntimeError('Incomplete application deployment inventory')


def deploy(client, role, result):
    result['starting_role'] = role
    save(result)
    response = client.request('POST', f'applications/{APPS[role]}/start', {})
    deployment_id = response.get('deployment_uuid')
    if not deployment_id:
        raise RuntimeError('No new deployment UUID for ' + role)
    result['deployments'][role] = deployment_id
    result.pop('starting_role', None)
    save(result)

    def finished():
        deployment = client.request('GET', 'deployments/' + deployment_id)
        status = deployment['status']
        if status in TERMINAL_DEPLOYMENTS - {'finished'}:
            raise RuntimeError(f'{role} deployment {status}; inspect private Coolify logs')
        return status == 'finished'

    wait_for(finished, role + ' deployment', timeout=4200 if role in ('jobs', 'scheduler') else 1500)
    wait_for(lambda: client.request('GET', 'applications/' + APPS[role])['status'] == 'running:healthy',
             role + ' health', timeout=180)
    result['completed'].append(role)
    save(result)
    print(role + ': deployment finished and healthy', flush=True)


def save(result):
    temporary = Path('release-result.json.tmp')
    with temporary.open('w') as stream:
        json.dump(result, stream, indent=2)
        stream.flush()
        os.fsync(stream.fileno())
    temporary.replace('release-result.json')


def prepare_images(client, backend, frontend):
    """Pull both immutable images on the pinned target before any API mutation."""
    if os.environ.get('OPNFORM_DEPLOY_ENABLED') != 'true':
        raise RuntimeError('OpnForm deployment is disarmed')
    host = os.environ.get('OPNFORM_DOCKER_HOST', '')
    parsed = urllib.parse.urlsplit(host)
    try:
        private = ipaddress.ip_address(parsed.hostname) in ipaddress.ip_network('100.64.0.0/10')
    except ValueError:
        private = False
    if (parsed.scheme != 'ssh' or not private or parsed.password or parsed.path
            or parsed.query or parsed.fragment or parsed.port):
        raise RuntimeError('Image preparation requires a pinned private SSH Docker endpoint')
    server = client.request('GET', 'servers/' + SERVER)
    if server.get('ip') != parsed.hostname or server.get('uuid') != SERVER:
        raise RuntimeError('Docker image preparation target does not match Coolify server')
    for component, digest in [('backend', backend), ('frontend', frontend)]:
        subprocess.run(['docker', '--host', host, 'pull',
                        'ghcr.io/opnform/hosted-' + component + '@' + digest],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=900)


def run(client, backend, frontend, sha, result):
    validate_target_configuration()
    if not re.fullmatch(r'[0-9a-f]{40}', sha):
        raise RuntimeError('Invalid release SHA')
    for digest in (backend, frontend):
        if not re.fullmatch(r'sha256:[0-9a-f]{64}', digest):
            raise RuntimeError('An exact SHA256 digest is required')
    assert_no_active_deployments(client)
    # Validate EVERYTHING before the first mutation, preserving rollback references.
    for role, uuid in APPS.items():
        app = client.request('GET', 'applications/' + uuid)
        validate_app(role, app)
        result['previous'][role] = {key: app.get(key) for key in
            ('docker_registry_image_name', 'docker_registry_image_tag', 'status')}
    if LEGACY_SERVICE:
        legacy = client.request('GET', 'services/' + LEGACY_SERVICE)
        if legacy['environment_id'] != LEGACY_ENVIRONMENT_ID or legacy['server_id'] != LEGACY_SERVER_ID:
            raise RuntimeError('Wrong legacy preview worker service')
        if not legacy['status'].startswith('exited'):
            raise RuntimeError('Drain the legacy preview service with docker stop --time 1000 before the first release; Coolify service stop uses only 30 seconds')
    save(result)
    prepare_images(client, backend, frontend)

    def select_image(role):
        component = 'frontend' if role == 'frontend' else 'backend'
        digest = frontend if role == 'frontend' else backend
        # Coolify resolves the sha256- tag syntax into image@sha256:<digest>.
        patch = {'docker_registry_image_name': 'ghcr.io/opnform/hosted-' + component,
                 'docker_registry_image_tag': digest.replace(':', '-', 1)}
        client.request('PATCH', 'applications/' + APPS[role], patch)
        app = client.request('GET', 'applications/' + APPS[role])
        if any(app.get(key) != value for key, value in patch.items()):
            raise RuntimeError('Image configuration did not persist for ' + role)

    # Old applications stay live if the single migration stage fails.
    select_image('migrate')
    deploy(client, 'migrate', result)
    result['migration_succeeded'] = True
    save(result)
    for role in ('api', 'jobs', 'scheduler', 'frontend'):
        select_image(role)
    for role in ('jobs', 'scheduler', 'api', 'frontend'):
        deploy(client, role, result)
    with urllib.request.urlopen(DOMAINS['api'] + '/healthz', timeout=15) as response:
        health = json.load(response)
    if health.get('status') != 'ok' or health.get('revision') != sha:
        raise RuntimeError('API does not serve the expected healthy release')
    with urllib.request.urlopen(DOMAINS['frontend'] + '/release.json', timeout=15) as response:
        if json.load(response).get('revision') != sha:
            raise RuntimeError('Frontend does not serve the expected release')
    result['status'] = 'success'
    save(result)


if __name__ == '__main__':
    if os.environ.get('OPNFORM_DEPLOY_ENABLED') != 'true':
        raise SystemExit('OpnForm deployment is disarmed; no remote request made')
    result = {'status': 'running', 'sha': os.environ['RELEASE_SHA'], 'previous': {},
              'deployments': {}, 'completed': [], 'migration_succeeded': False,
              'backend_digest': os.environ['BACKEND_DIGEST'], 'frontend_digest': os.environ['FRONTEND_DIGEST']}
    try:
        run(Coolify(), result['backend_digest'], result['frontend_digest'], result['sha'], result)
    except Exception:
        result['status'] = 'failed'
        save(result)
        # Never roll a database back automatically. Preserve successful steps and
        # the prior image references so the operator can make a compatible rollback.
        raise
