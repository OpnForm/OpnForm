#!/usr/bin/env python3
"""Execute the proposed SQL invariant in a NEW network-none fixture, never the app DB."""
import argparse
import concurrent.futures
import datetime
import json
from pathlib import Path
import socket
import subprocess
import time
import uuid


def exercise(output):
    if socket.gethostname().split('.')[0] != 'opnform-scaleway':
        raise ValueError('Only the authorized preparation host is supported')
    name = 'opnform-idempotency-fixture-' + uuid.uuid4().hex[:12]
    created = False
    checks = []

    def docker(*args, data=None, check=True):
        result = subprocess.run(['docker', *args], input=data, capture_output=True, text=True, timeout=25)
        if check and result.returncode:
            raise RuntimeError('Fixture operation failed: ' + args[0])
        return result

    def sql(query, check=True):
        return docker('exec', name, 'psql', '-X', '-U', 'postgres', '-d', 'postgres',
                      '-qAt', '-v', 'ON_ERROR_STOP=1', '-c', query, check=check)

    def ingest(key, body='{"synthetic":true}', fail=False, check=True):
        if not key.replace('-', '').isalnum() or "'" in body:
            raise ValueError('Invalid fixture input')
        return sql(f"SELECT fixture_ingest(1,'{key}','{body}'::jsonb,{str(fail).lower()})", check)

    def equal(label, actual, expected):
        if actual != expected:
            raise AssertionError(label)
        checks.append(label)

    try:
        image = json.loads(docker('image', 'inspect', 'postgres:16-bookworm').stdout)[0]['Id']
        docker('run', '-d', '--pull=never', '--name', name,
               '--label', 'opnform.preparation=idempotency-fixture', '--network', 'none',
               '--read-only', '--user', 'postgres', '--cap-drop=ALL', '--security-opt', 'no-new-privileges',
               '--tmpfs', '/var/lib/postgresql/data:rw,uid=999,gid=999,mode=0700',
               '--tmpfs', '/var/run/postgresql:rw,uid=999,gid=999', '--tmpfs', '/tmp',
               '-e', 'POSTGRES_HOST_AUTH_METHOD=trust', image)
        created = True
        end = time.monotonic() + 30
        while docker('exec', name, 'pg_isready', '-U', 'postgres', check=False).returncode:
            if time.monotonic() > end:
                raise RuntimeError('Fixture startup timeout')
            time.sleep(.2)
        schema = Path(__file__).with_name('tests') / 'submission-idempotency-prototype.sql'
        docker('exec', '-i', name, 'psql', '-X', '-U', 'postgres', '-d', 'postgres',
               '-v', 'ON_ERROR_STOP=1', data=schema.read_text())

        equal('injected_precommit_failure', ingest('crash-before-commit', fail=True, check=False).returncode != 0, True)
        equal('no_partial_submission_or_outbox', sql("SELECT (SELECT count(*) FROM fixture_submissions)+(SELECT count(*) FROM fixture_outbox)+(SELECT count(*) FROM fixture_operations)").stdout.strip(), '0')
        first = ingest('replay-after-commit').stdout.strip()
        equal('committed_unknown_ack_reuses_submission', ingest('replay-after-commit').stdout.strip(), first)
        equal('one_submission_one_outbox_for_replay', sql("SELECT (SELECT count(*) FROM fixture_submissions)::text||':'||(SELECT count(*) FROM fixture_outbox)::text").stdout.strip(), '1:1')

        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            values = list(pool.map(lambda _: ingest('concurrent-eight').stdout.strip(), range(8)))
        equal('eight_concurrent_deliveries_one_id', len(set(values)), 1)
        equal('one_outbox_after_concurrency', sql("SELECT count(*) FROM fixture_outbox WHERE operation_key='concurrent-eight'").stdout.strip(), '1')
        equal('different_payload_same_operation_refused', ingest('concurrent-eight', '{"synthetic":false}', check=False).returncode != 0, True)
        a = ingest('legitimate-new-operation-a').stdout.strip()
        b = ingest('legitimate-new-operation-b').stdout.strip()
        equal('distinct_operations_not_accidentally_deduplicated', a != b, True)

        claim = "WITH candidate AS (SELECT id FROM fixture_outbox WHERE operation_key='replay-after-commit' AND state='pending' FOR UPDATE SKIP LOCKED LIMIT 1) UPDATE fixture_outbox SET state='claimed' WHERE id IN (SELECT id FROM candidate) RETURNING id"
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            claims = list(pool.map(lambda _: sql(claim).stdout.strip(), range(2)))
        ids = [value for value in claims if value]
        equal('two_effect_workers_only_one_claim', len(ids), 1)
        outbox = int(ids[0])
        # Fake external acceptance is deliberately a separate commit from local ACK.
        sql(f'INSERT INTO fixture_external_effects VALUES ({outbox},1)')
        sql(f"UPDATE fixture_outbox SET state='ambiguous' WHERE id={outbox} AND state='claimed'")
        equal('ambiguous_effect_not_blindly_retried', sql(claim).stdout.strip(), '')
        equal('accepted_fake_effect_not_duplicated', sql(f'SELECT calls FROM fixture_external_effects WHERE outbox_id={outbox}').stdout.strip(), '1')
        equal('unknown_effect_outcome_retained', sql(f'SELECT state FROM fixture_outbox WHERE id={outbox}').stdout.strip(), 'ambiguous')
        proof = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                 'dataset': 'synthetic-only', 'image_id': image, 'network': 'none',
                 'passed': len(checks), 'checks': checks, 'concurrent_ingestion_clients': 8,
                 'concurrent_effect_workers': 2, 'application_job_changed': False,
                 'production_schema_changed': False, 'external_requests': 0,
                 'limit': 'SQL design prototype only; actual job/files/integration wiring and transport failures remain unimplemented'}
    finally:
        if created:
            docker('rm', '-f', '-v', name)
    proof['fixture_removed'] = docker('container', 'inspect', name, check=False).returncode != 0
    if not proof['fixture_removed']:
        raise RuntimeError('Fixture cleanup failed')
    output = Path(output)
    output.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if output.exists():
        raise ValueError('Refuse to overwrite existing proof')
    output.write_text(json.dumps(proof, indent=2) + '\n')
    return proof


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    print(json.dumps(exercise(args.output), indent=2))
