#!/usr/bin/env python3
"""Check dated, hashed local evidence; never authorize or execute a cutover."""
import argparse
import datetime
import hashlib
import json
from pathlib import Path

GATES = (
    'server_security_ipfo', 'post_reboot_ipfo', 'runtime_amd64',
    'synthetic_forward_reverse_rehearsal', 'fresh_source_inventory',
    'private_registry_exact_release', 'live_release_serialization',
    'object_storage_versioning_metadata', 'signed_upload_download_cors',
    'real_backup_restore_retention_capacity', 'production_secrets_sessions',
    'representative_business_sandbox_flows', 'customer_domain_tls_plan',
    'source_writer_producer_fence', 'sqs_delayed_inflight_dlq_reconciliation',
    'frozen_final_sql_object_delta', 'human_cutover_authorization',
)


def template():
    return {'schema': 1, 'product': 'opnform',
            'gates': {name: {'status': 'pending', 'checked_at': None, 'evidence': None}
                      for name in GATES}}


def check(document, now=None):
    now = now or datetime.datetime.now(datetime.timezone.utc)
    if (document.get('schema') != 1 or document.get('product') != 'opnform'
            or set(document.get('gates', {})) != set(GATES)):
        raise ValueError('Exact OpnForm gate schema required')
    blockers = []
    verified = []
    for name in GATES:
        entry = document['gates'][name]
        reason = None
        if entry.get('status') != 'verified':
            reason = entry.get('reason') or 'pending or not verified'
        else:
            try:
                stamp = datetime.datetime.fromisoformat(entry['checked_at'])
                if stamp.tzinfo is None:
                    raise ValueError('Timezone required')
                age = (now - stamp).total_seconds()
                if age < -300 or age > 24 * 3600:
                    raise ValueError('Evidence older than 24h or dated in future')
                evidence = entry['evidence']
                path = Path(evidence['path'])
                if not path.is_absolute() or not path.is_file() or path.stat().st_size > 16 * 1024 * 1024:
                    raise ValueError('Absolute evidence file required, max 16MiB')
                h = hashlib.sha256()
                with path.open('rb') as stream:
                    for data in iter(lambda: stream.read(1024 * 1024), b''):
                        h.update(data)
                if h.hexdigest() != evidence['sha256']:
                    raise ValueError('Evidence SHA256 mismatch')
            except (ValueError, TypeError, KeyError, OSError) as error:
                reason = str(error)
        if reason:
            blockers.append({'gate': name, 'reason': reason})
        else:
            verified.append(name)
    return {'status': 'NO-GO' if blockers else 'EVIDENCE-COMPLETE-REVIEW-REQUIRED',
            'checked_at': now.isoformat(), 'verified': verified, 'blockers': blockers,
            'execution_authorized_by_tool': False,
            'limit': 'Hashes and dates check local evidence consistency, not truth or human authorization'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('evidence', type=Path, nargs='?')
    parser.add_argument('--template', action='store_true')
    args = parser.parse_args()
    if args.template:
        print(json.dumps(template(), indent=2))
        return
    if args.evidence is None:
        parser.error('Provide an evidence JSON file or --template')
    try:
        report = check(json.loads(args.evidence.read_text()))
    except (ValueError, OSError, TypeError) as error:
        parser.exit(2, f'Readiness refused: {error}\n')
    print(json.dumps(report, indent=2))
    raise SystemExit(2 if report['blockers'] else 0)


if __name__ == '__main__':
    main()
