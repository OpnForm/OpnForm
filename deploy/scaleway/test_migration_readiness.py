import datetime
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('readiness', Path(__file__).with_name('migration-readiness.py'))
readiness = importlib.util.module_from_spec(spec)
spec.loader.exec_module(readiness)


class ReadinessTest(unittest.TestCase):
    def test_missing_gates_cannot_silently_pass(self):
        doc = readiness.template()
        del doc['gates']['human_cutover_authorization']
        with self.assertRaises(ValueError):
            readiness.check(doc)

    def test_default_is_no_go(self):
        result = readiness.check(readiness.template())
        self.assertEqual(len(result['blockers']), len(readiness.GATES))
        self.assertFalse(result['execution_authorized_by_tool'])

    def test_fresh_evidence_then_tamper_is_detected(self):
        with tempfile.TemporaryDirectory() as folder:
            proof = Path(folder).resolve() / 'proof.json'
            proof.write_text('{"synthetic":true}')
            doc = readiness.template()
            entry = doc['gates']['runtime_amd64']
            entry.update(status='verified', checked_at=datetime.datetime.now(datetime.timezone.utc).isoformat(),
                         evidence={'path': str(proof), 'sha256': hashlib.sha256(proof.read_bytes()).hexdigest()})
            self.assertIn('runtime_amd64', readiness.check(doc)['verified'])
            proof.write_text('modified')
            self.assertNotIn('runtime_amd64', readiness.check(doc)['verified'])

    def test_stale_or_future_evidence_cannot_pass(self):
        doc = readiness.template()
        for delta in [-25, 1]:
            doc['gates']['runtime_amd64'].update(status='verified',
                checked_at=(datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(hours=delta)).isoformat())
            self.assertNotIn('runtime_amd64', readiness.check(doc)['verified'])


if __name__ == '__main__':
    unittest.main()
