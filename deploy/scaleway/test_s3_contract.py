import copy
import datetime
import importlib.util
import json
from pathlib import Path
import unittest
import urllib.parse

root = Path(__file__).parent
spec = importlib.util.spec_from_file_location('s3_contract', root / 's3-contract.py')
contract = importlib.util.module_from_spec(spec)
spec.loader.exec_module(contract)


class StorageContractTest(unittest.TestCase):
    def setUp(self):
        self.vectors = json.loads((root / 'tests/s3-contract-vectors.fixture.json').read_text())

    def test_locked_sdk_fictional_vectors_match_independent_hmac_calculation(self):
        result = contract.inspect(self.vectors)
        self.assertEqual(result['passed'], 23)
        self.assertEqual(result['provider_s3_requests'], 0)
        self.assertFalse(result['provider_acl_cors_lifecycle_verified'])

    def test_noncanonical_header_order_and_cache_control_gap_are_reported(self):
        result = contract.inspect(self.vectors)['observations']
        self.assertEqual(result['noncanonical_signed_header_query_order'], ['metadata_put'])
        self.assertFalse(result['requested_cache_control_returned'])
        self.assertFalse(result['content_type_signed'])

    def test_all_header_or_key_signature_tampering_refused_at_valid_time(self):
        for name, data in self.vectors['vectors'].items():
            if not isinstance(data, dict) or 'url' not in data:
                continue
            query = dict(urllib.parse.parse_qsl(urllib.parse.urlsplit(data['url']).query))
            at = datetime.datetime.strptime(query['X-Amz-Date'], '%Y%m%dT%H%M%SZ').replace(tzinfo=datetime.timezone.utc)
            uri = urllib.parse.urlsplit(data['url'])
            tampered = urllib.parse.urlunsplit(uri._replace(path=uri.path + '/tampered'))
            self.assertTrue(contract.valid_signature(data['url'],
                'GET' if name == 'private_get' else 'PUT', data['headers'], now=at, strict_query_order=False))
            self.assertFalse(contract.valid_signature(tampered,
                'GET' if name == 'private_get' else 'PUT', data['headers'], now=at, strict_query_order=False))

    def test_baseline_cannot_be_reported_as_corrected(self):
        with self.assertRaises(ValueError):
            contract.inspect(self.vectors, require_corrected=True)

    def test_corrected_wrapper_vectors_pass_strict_standard_header_order(self):
        vectors = json.loads((root / 'tests/s3-corrected-vectors.fixture.json').read_text())
        report = contract.inspect(vectors, require_corrected=True)
        self.assertEqual(report['passed'], 25)
        self.assertEqual(report['observations']['noncanonical_signed_header_query_order'], [])
        self.assertTrue(report['observations']['requested_cache_control_returned'])

    def test_swapped_public_private_acl_breaks_contract(self):
        changed = copy.deepcopy(self.vectors)
        changed['vectors']['private_put']['url'] = changed['vectors']['private_put']['url'].replace('x-amz-acl=private', 'x-amz-acl=public-read')
        with self.assertRaises(ValueError):
            contract.inspect(changed)

    def test_cors_fixture_covers_observed_headers_but_only_fictional_origin(self):
        cors = json.loads((root / 'storage-cors.fixture.json').read_text())['CORSRules'][0]
        required = set(contract.inspect(self.vectors)['observations']['required_browser_put_headers'])
        self.assertTrue(required.issubset(cors['AllowedHeaders']))
        self.assertEqual(cors['AllowedOrigins'], ['https://app.fixture.invalid'])
        self.assertEqual(set(cors['AllowedMethods']), {'PUT', 'GET', 'HEAD'})
        self.assertNotIn('*', cors['AllowedOrigins'])

    def test_proposed_lifecycle_cannot_delete_current_or_noncurrent_objects(self):
        rules = json.loads((root / 'storage-lifecycle.proposed.json').read_text())['Rules']
        for rule in rules:
            self.assertEqual(rule['Status'], 'Disabled')
            self.assertNotIn('Expiration', rule)
            self.assertNotIn('NoncurrentVersionExpiration', rule)


if __name__ == '__main__':
    unittest.main()
