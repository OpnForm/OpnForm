#!/usr/bin/env python3
"""Actual Redis/Laravel delayed and killed-worker transport; generated jobs only."""
import base64
import datetime
import json
from pathlib import Path
import socket
import subprocess
import time
import uuid


def main():
    if socket.gethostname().split('.')[0] != 'opnform-scaleway':
        raise ValueError('Only preparation host supported')
    token = uuid.uuid4().hex[:12]
    network = 'opnform-queue-fixture-' + token
    redis = network + '-redis'
    worker = network + '-worker'
    table = 'opnform_queue_fixture_' + token
    queue = 'opnform-fixture-' + token
    checks = []
    owned = []
    network_created = guard_created = False

    def run(args, data=None, check=True, timeout=25):
        result = subprocess.run(args, input=data, capture_output=True, text=True, timeout=timeout)
        if check and result.returncode:
            raise RuntimeError('Fixture operation failed: ' + args[0] + ' ' + result.stderr[-500:])
        return result

    def docker(*args, **kwargs):
        return run(['docker', *args], **kwargs)

    def cli(*args):
        return docker('exec', redis, 'redis-cli', '--raw', *args).stdout.strip()

    def equal(label, actual, expected):
        if actual != expected:
            raise AssertionError(label + ': ' + str(actual))
        checks.append(label)

    try:
        api_image = json.loads(docker('image', 'inspect', 'opnform-preparation-api:amd64').stdout)[0]['Id']
        redis_image = docker('inspect', 'tjcusqz9unmfgqtpr8uesx2h', '--format', '{{.Image}}').stdout.strip()
        docker('network', 'create', '--internal', '--label', 'opnform.preparation=queue-transport-fixture', network)
        network_created = True
        network_id = json.loads(docker('network', 'inspect', network).stdout)[0]['Id']
        bridge = 'br-' + network_id[:12]
        rules = f'''add table inet {table}
add chain inet {table} input {{ type filter hook input priority -30; policy accept; }}
add chain inet {table} forward {{ type filter hook forward priority -30; policy accept; }}
add rule inet {table} input iifname "{bridge}" ct state new drop
add rule inet {table} forward iifname "{bridge}" oifname != "{bridge}" ct state new drop
'''
        run(['nft', '-f', '-'], data=rules)
        guard_created = True
        docker('run', '-d', '--pull=never', '--name', redis, '--network', network,
               '--label', 'opnform.preparation=queue-transport-fixture', '--read-only',
               '--user', 'redis', '--tmpfs', '/data:mode=1777', '--cap-drop=ALL', '--security-opt', 'no-new-privileges',
               redis_image, 'redis-server', '--save', '', '--appendonly', 'no')
        owned.append(redis)
        end = time.monotonic() + 15
        while docker('exec', redis, 'redis-cli', 'ping', check=False).returncode:
            if time.monotonic() > end:
                raise RuntimeError('Fixture Redis startup timeout')
            time.sleep(.1)
        ip = next(iter(json.loads(docker('inspect', redis).stdout)[0]['NetworkSettings']['Networks'].values()))['IPAddress']
        php = Path(__file__).with_suffix('.php').read_text()
        environment = {
            'OPNFORM_QUEUE_FIXTURE': 'synthetic-only', 'OPNFORM_FIXTURE_QUEUE': queue,
            'APP_ENV': 'testing', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:',
            'APP_KEY': 'base64:' + base64.b64encode(b'fake' * 8).decode(),
            'JWT_SECRET': 'fictional-queue-fixture-secret-only-32',
            'CACHE_DRIVER': 'array', 'SESSION_DRIVER': 'array', 'MAIL_MAILER': 'log',
            'QUEUE_CONNECTION': 'redis', 'REDIS_CLIENT': 'phpredis', 'REDIS_HOST': ip,
            'REDIS_PORT': '6379', 'REDIS_QUEUE_CONNECTION': 'default',
            'REDIS_QUEUE_RETRY_AFTER': '4', 'REDIS_PREFIX': 'opnform_fixture_',
            'AWS_EC2_METADATA_DISABLED': 'true',
        }

        def process(mode):
            args = ['run', '--rm', '--pull=never', '-i', '--name', worker, '--network', network,
                    '--dns', '127.0.0.1', '--read-only', '--tmpfs', '/tmp', '--cap-drop=ALL',
                    '--security-opt', 'no-new-privileges', '--label', 'opnform.preparation=queue-transport-fixture']
            for name, value in {**environment, 'OPNFORM_FIXTURE_MODE': mode}.items():
                args.extend(['-e', name + '=' + value])
            args += ['--entrypoint', '/usr/bin/tini', api_image, '--', 'php']
            result = docker(*args, data=php, check=False)
            if mode != 'worker' and result.returncode == 0:
                equal(mode + '_dispatch_ack', json.loads(result.stdout)['dispatched'], True)
            return result

        def events():
            keys = cli('KEYS', '*fixture-events').splitlines()
            return [] if not keys else [json.loads(x) for x in cli('LRANGE', keys[0], '0', '-1').splitlines()]

        equal('delayed_dispatch_exit', process('delay').returncode, 0)
        equal('early_worker_exit', process('worker').returncode, 0)
        equal('delayed_job_not_early', events(), [])
        time.sleep(3.2)
        equal('due_worker_exit', process('worker').returncode, 0)
        delayed = events()
        equal('delayed_job_once', len(delayed), 1)
        equal('delayed_first_attempt', delayed[0]['attempt'], 1)

        equal('crash_dispatch_exit', process('crash').returncode, 0)
        equal('worker_really_killed_before_ack', process('worker').returncode, 137)
        first = events()[-1]
        equal('crashed_first_attempt', first['attempt'], 1)
        reserved = cli('KEYS', '*:reserved').splitlines()
        equal('crashed_payload_still_reserved', sum(int(cli('ZCARD', k)) for k in reserved), 1)
        time.sleep(4.2)
        equal('retry_worker_exit', process('worker').returncode, 0)
        retry = events()[-1]
        equal('retried_second_attempt', retry['attempt'], 2)
        equal('transport_uuid_preserved_across_crash', retry['uuid'], first['uuid'])
        equal('transport_uuid_nonempty', isinstance(first['uuid'], str) and len(first['uuid']) == 36, True)
        equal('reserved_empty_after_success', sum(int(cli('ZCARD', k)) for k in reserved), 0)
        proof = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                 'dataset': 'synthetic-only', 'passed': len(checks), 'checks': checks,
                 'api_image': api_image, 'redis_image': redis_image, 'events': events(),
                 'network_internal': True, 'host_egress_guard': True, 'published_ports': False,
                 'retry_after_fixture_seconds': 4, 'production_retry_after_unchanged': 3700,
                 'application_submission_job_changed': False, 'real_queue_client_messages': False,
                 'external_integrations_executed': False,
                 'limit': 'Actual Laravel/Redis transport with synthetic job; not live SQS, source DLQ drain or application deduplication'}
    finally:
        docker('rm', '-f', '-v', worker, check=False)
        for item in reversed(owned):
            docker('rm', '-f', '-v', item)
        if guard_created:
            run(['nft', 'delete', 'table', 'inet', table])
        if network_created:
            docker('network', 'rm', network)
    proof['fixtures_removed'] = True
    path = Path(__file__).with_name('queue-transport-proof-' + token + '.json')
    path.write_text(json.dumps(proof, indent=2) + '\n')
    print(json.dumps(proof, indent=2))


if __name__ == '__main__':
    main()
