"""Safety contracts; no AWS access or credentials are used by these tests."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('audit', Path(__file__).with_name('audit-aws.py'))
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


class AuditSafetyTests(unittest.TestCase):
    def args(self, directory):
        return ['audit', '--profile', 'fixture', '--expected-account', '123456789012',
                '--regions', 'eu-west-3', '--output-dir', str(directory)]

    def test_wrong_account_stops_before_any_inventory(self):
        with tempfile.TemporaryDirectory() as directory:
            with patch.object(sys, 'argv', self.args(directory)), patch.object(
                audit, 'aws', return_value={'ok': True, 'data': {'Account': '999999999999'}}
            ) as call:
                with self.assertRaises(SystemExit):
                    audit.main()
                call.assert_called_once_with('fixture', 'eu-west-3', 'sts', 'get-caller-identity')
                self.assertEqual(list(Path(directory).iterdir()), [])

    def test_repo_output_rejected_before_network(self):
        repo = Path(__file__).resolve().parents[2]
        with patch.object(sys, 'argv', self.args(repo / 'output')), patch.object(audit, 'aws') as call:
            with self.assertRaises(SystemExit):
                audit.main()
            call.assert_not_called()

    def test_signed_diagnostics_are_never_returned(self):
        fixture = subprocess.CompletedProcess([], 255, '', 'Authorization: SECRET_FIXTURE')
        with patch.object(audit.subprocess, 'run', return_value=fixture):
            result = audit.aws('fixture', 'eu-west-3', 'sts', 'get-caller-identity')
        self.assertEqual(result, {'ok': False, 'exit_code': 255})

    def test_network_failure_is_reported_without_payload(self):
        with patch.object(audit.subprocess, 'run', side_effect=subprocess.TimeoutExpired('aws', 45)):
            self.assertFalse(audit.aws('fixture', 'eu-west-3', 'sqs', 'list-queues')['ok'])

    def test_empty_successful_sqs_listing_is_not_a_permission_failure(self):
        with patch.object(audit.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, '', '')):
            self.assertEqual(audit.aws('fixture', 'eu-west-1', 'sqs', 'list-queues'), {'ok': True, 'data': {}})

    def test_failed_queue_attributes_fail_inventory_and_report_is_private(self):
        def fixture(profile, region, *command):
            if command[:2] == ('sts', 'get-caller-identity'):
                return {'ok': True, 'data': {'Account': '123456789012'}}
            if command[:2] == ('sqs', 'list-queues'):
                return {'ok': True, 'data': {'QueueUrls': ['https://sqs.invalid/fixture']}}
            if command[:2] == ('sqs', 'get-queue-attributes'):
                return {'ok': False, 'exit_code': 255}
            return {'ok': True, 'data': {}}

        with tempfile.TemporaryDirectory() as directory:
            with patch.object(sys, 'argv', self.args(directory)), patch.object(audit, 'aws', side_effect=fixture):
                self.assertTrue(audit.main())
            report = Path(directory) / 'aws-inventory.json'
            self.assertEqual(report.stat().st_mode & 0o777, 0o600)
            self.assertFalse(json.loads(report.read_text())['checks']['eu-west-3/queue-attributes'][0]['ok'])


if __name__ == '__main__':
    unittest.main()
