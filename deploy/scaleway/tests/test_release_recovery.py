import importlib.util
from target_fixtures import configure_test_target
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch


class ReleaseRecoveryTest(unittest.TestCase):
    def setUp(self):
        spec = importlib.util.spec_from_file_location('poller', Path(__file__).parents[1] / 'release-poller.py')
        self.poller = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(self.poller)
        configure_test_target(self.poller.deploy)
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.cwd = Path.cwd()
        self.state = {'sequence': [10, 1], 'status': 'failed', 'started_at': 1}
        self.result = {'status': 'failed', 'deployments': {'migrate': 'old-migration'}, 'previous': {'api': 'old-image'}}
        (self.root / 'poller-state.json').write_text(json.dumps(self.state))
        (self.root / 'release-result.json').write_text(json.dumps(self.result))
        self.release = {'run_id': 11, 'run_attempt': 1, 'sha': 'c' * 40,
                        'backend_digest': 'sha256:' + 'a' * 64, 'frontend_digest': 'sha256:' + 'b' * 64}

    def tearDown(self):
        os.chdir(self.cwd)
        self.temp.cleanup()

    def run_poller(self, remote_status):
        def request(method, path, data=None):
            self.assertEqual(method, 'GET')
            if path == 'deployments/old-migration': return {'status': remote_status}
            if path.startswith('deployments/applications/'): return {'count': 0, 'deployments': []}
            raise AssertionError(path)

        with patch.dict(os.environ, {'STATE_DIRECTORY': str(self.root), 'OPNFORM_RELEASE_ENVIRONMENT': 'preview', 'OPNFORM_DEPLOY_ENABLED': 'true'}), \
             patch.object(self.poller, 'fetch_release', return_value=(self.release, 'manifest-digest')), \
             patch.object(self.poller.deploy, 'Coolify') as client, \
             patch.object(self.poller.deploy, 'run') as run:
            client.return_value.request.side_effect = request
            if remote_status != 'finished':
                with self.assertRaises(SystemExit): self.poller.main()
                run.assert_not_called()
            else:
                self.poller.main()
                run.assert_called_once()

    def test_new_manifest_cannot_overlap_a_remote_job_after_network_failure(self):
        self.run_poller('in_progress')
        state = json.loads((self.root / 'poller-state.json').read_text())
        self.assertEqual(state['sequence'], [10, 1])
        self.assertEqual(json.loads((self.root / 'release-result.json').read_text()), self.result)
        self.run_poller('finished')
        self.assertEqual(json.loads((self.root / 'poller-state.json').read_text())['sequence'], [11, 1])
        self.assertEqual(json.loads((self.root / 'history/10-1.json').read_text())['result'], self.result)

    def test_interrupted_controller_preserves_its_journal_while_remote_job_is_queued(self):
        self.state['status'] = 'deploying'
        (self.root / 'poller-state.json').write_text(json.dumps(self.state))
        self.run_poller('queued')
        self.assertEqual(json.loads((self.root / 'poller-state.json').read_text())['status'], 'deploying')


if __name__ == '__main__': unittest.main()
