#!/usr/bin/env python3
"""Read-only AWS inventory. Store reports outside the repository, never credentials."""
import argparse
import datetime
import json
import os
from pathlib import Path
import subprocess


def aws(profile, region, *args):
    try:
        result = subprocess.run(
            ['aws', '--profile', profile, '--region', region, '--no-cli-pager',
             '--output', 'json', *args], capture_output=True, text=True, timeout=45,
            env={**os.environ, 'AWS_PAGER': ''},
        )
    except (subprocess.TimeoutExpired, OSError):
        return {'ok': False, 'error': 'AWS CLI unavailable or timed out'}
    if result.returncode:
        # SDK diagnostics can include signed requests; never persist stderr here.
        return {'ok': False, 'exit_code': result.returncode}
    try:
        # AWS CLI renders a successful empty SQS listing as an empty stdout.
        return {'ok': True, 'data': json.loads(result.stdout or '{}')}
    except ValueError:
        return {'ok': False, 'error': 'Unexpected AWS response format'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--profile', required=True)
    parser.add_argument('--expected-account', required=True)
    parser.add_argument('--regions', nargs='+', default=['eu-west-3', 'eu-west-1', 'us-east-1'])
    parser.add_argument('--output-dir', type=Path, required=True)
    args = parser.parse_args()
    target = args.output_dir.resolve()
    repo = Path(__file__).resolve().parents[2]
    if target.is_relative_to(repo):
        raise SystemExit('STOP: output must be outside the repository')
    identity = aws(args.profile, args.regions[0], 'sts', 'get-caller-identity')
    if not identity['ok'] or identity['data'].get('Account') != args.expected_account:
        raise SystemExit('STOP: AWS identity unavailable or different from expected account')
    os.umask(0o077)
    target.mkdir(parents=True, exist_ok=True, mode=0o700)
    report = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'identity': identity['data'], 'checks': {}}
    commands = {
        'rds': ['rds', 'describe-db-instances'],
        'rds-clusters': ['rds', 'describe-db-clusters'],
        'rds-snapshots': ['rds', 'describe-db-snapshots', '--snapshot-type', 'automated'],
        'lambda': ['lambda', 'list-functions', '--query',
                   'Functions[].{Name:FunctionName,Arn:FunctionArn,Runtime:Runtime,Memory:MemorySize,Timeout:Timeout,Modified:LastModified,Vpc:VpcConfig,Role:Role}'],
        'event-mappings': ['lambda', 'list-event-source-mappings'],
        'sqs': ['sqs', 'list-queues'],
        'redis': ['elasticache', 'describe-cache-clusters'],
        'rules': ['events', 'list-rules'],
        'scheduler': ['scheduler', 'list-schedules'],
        'amplify': ['amplify', 'list-apps', '--query',
                    'apps[].{id:appId,name:name,domain:defaultDomain,repository:repository,branch:productionBranch,platform:platform}'],
        'api-domains': ['apigatewayv2', 'get-domain-names'],
        'api-domains-v1': ['apigateway', 'get-domain-names'],
        'certificates': ['acm', 'list-certificates'],
        'ses': ['sesv2', 'get-account'],
        'waf': ['wafv2', 'list-web-acls', '--scope', 'REGIONAL'],
        'log-groups': ['logs', 'describe-log-groups', '--query',
                       'logGroups[].{name:logGroupName,bytes:storedBytes,retention:retentionInDays}'],
    }
    output = target / 'aws-inventory.json'

    def save():
        output.write_text(json.dumps(report, indent=2) + '\n')
        output.chmod(0o600)

    for region in args.regions:
        for name, command in commands.items():
            key = f'{region}/{name}'
            report['checks'][key] = aws(args.profile, region, *command)
            save()
        queues = report['checks'][f'{region}/sqs'].get('data', {}).get('QueueUrls', [])
        report['checks'][f'{region}/queue-attributes'] = [
            aws(args.profile, region, 'sqs', 'get-queue-attributes',
                '--queue-url', url, '--attribute-names', 'All') for url in queues
        ]
        save()
    for name, command in {
        'buckets': ['s3api', 'list-buckets'],
        'zones': ['route53', 'list-hosted-zones'],
        'cloudfront': ['cloudfront', 'list-distributions', '--query',
                      'DistributionList.Items[].{Id:Id,Arn:ARN,Domain:DomainName,Aliases:Aliases,Enabled:Enabled,Status:Status,Origins:Origins.Items[].{Id:Id,Domain:DomainName,Path:OriginPath}}'],
        'iam-summary': ['iam', 'get-account-summary'],
    }.items():
        report['checks'][f'global/{name}'] = aws(args.profile, args.regions[0], *command)
        save()
    failed = [name for name, value in report['checks'].items() if
              (isinstance(value, dict) and not value.get('ok')) or
              (isinstance(value, list) and any(not row['ok'] for row in value))]
    print(json.dumps({'report': str(output), 'failed_checks': failed}))
    # No claim of complete inventory when permissions/network are missing.
    return bool(failed)


if __name__ == '__main__':
    raise SystemExit(main())
