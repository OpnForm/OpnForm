import importlib.util
import os
import json
import hashlib
from pathlib import Path
import unittest
from unittest.mock import Mock, patch
from target_fixtures import configure_test_target

ROOT = Path(__file__).parents[1]
spec = importlib.util.spec_from_file_location('deployment', ROOT / 'deploy-preview.py')
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


class OpnFormControllerTests(unittest.TestCase):
    def setUp(self):
        configure_test_target(deploy)

    def test_root_team_zero_is_allowed_but_missing_team_is_rejected(self):
        deploy.TEAM_ID = 0
        deploy.validate_target_configuration()
        deploy.TEAM_ID = -1
        with self.assertRaises(RuntimeError):
            deploy.validate_target_configuration()

    def test_disarmed_client_cannot_mutate_even_with_valid_private_endpoint(self):
        with patch.dict(os.environ, {'COOLIFY_URL': 'http://100.64.0.1:8081',
                                    'COOLIFY_ALLOWED_URL': 'http://100.64.0.1:8081',
                                    'COOLIFY_TOKEN': 'fictional'}, clear=True), \
             patch.object(deploy.urllib.request, 'urlopen') as network:
            with self.assertRaises(RuntimeError):
                deploy.Coolify().request('PATCH', 'applications/fictional', {})
            network.assert_not_called()

    def test_both_digests_are_pulled_on_matching_private_server(self):
        client = Mock()
        client.request.return_value = {'uuid': deploy.SERVER, 'ip': '100.64.0.2'}
        with patch.dict(os.environ, {'OPNFORM_DEPLOY_ENABLED': 'true',
                                    'OPNFORM_DOCKER_HOST': 'ssh://root@100.64.0.2'}), \
             patch.object(deploy.subprocess, 'run') as execute:
            deploy.prepare_images(client, 'sha256:' + 'a' * 64, 'sha256:' + 'b' * 64)
            self.assertEqual(execute.call_count, 2)
            self.assertEqual(execute.call_args_list[0].args[0][-1],
                             'ghcr.io/opnform/hosted-backend@sha256:' + 'a' * 64)
            self.assertEqual(execute.call_args_list[1].args[0][-1],
                             'ghcr.io/opnform/hosted-frontend@sha256:' + 'b' * 64)

    def test_wrong_or_public_pull_target_never_executes_docker(self):
        client = Mock()
        client.request.return_value = {'uuid': deploy.SERVER, 'ip': '100.64.0.3'}
        for host in ['ssh://root@100.64.0.2', 'ssh://root@192.0.2.1']:
            with patch.dict(os.environ, {'OPNFORM_DEPLOY_ENABLED': 'true', 'OPNFORM_DOCKER_HOST': host}), \
                 patch.object(deploy.subprocess, 'run') as execute:
                with self.assertRaises(RuntimeError):
                    deploy.prepare_images(client, 'sha256:' + 'a' * 64, 'sha256:' + 'b' * 64)
                execute.assert_not_called()

    def test_puller_failure_stops_before_frontend_pull(self):
        client = Mock()
        client.request.return_value = {'uuid': deploy.SERVER, 'ip': '100.64.0.2'}
        with patch.dict(os.environ, {'OPNFORM_DEPLOY_ENABLED': 'true', 'OPNFORM_DOCKER_HOST': 'ssh://root@100.64.0.2'}), \
             patch.object(deploy.subprocess, 'run', side_effect=RuntimeError('synthetic pull failure')) as execute:
            with self.assertRaises(RuntimeError):
                deploy.prepare_images(client, 'sha256:' + 'a' * 64, 'sha256:' + 'b' * 64)
            self.assertEqual(execute.call_count, 1)

    def test_disarmed_poller_never_contacts_registry(self):
        spec = importlib.util.spec_from_file_location('poller', ROOT / 'release-poller.py')
        poller = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(poller)
        with patch.dict(os.environ, {}, clear=True), patch.object(poller, 'fetch_release') as fetch:
            poller.main()
            fetch.assert_not_called()

    def test_failed_prepull_prevents_every_coolify_mutation(self):
        from test_deployment import FakeClient
        client = FakeClient()
        result = {'previous': {}, 'completed': [], 'deployments': {}}
        with patch.object(deploy, 'save'), patch.object(deploy, 'prepare_images', side_effect=RuntimeError('pull failed')):
            with self.assertRaises(RuntimeError):
                deploy.run(client, 'sha256:'+'a'*64, 'sha256:'+'b'*64, 'c'*40, result)
        self.assertFalse(any(method != 'GET' for method, _, _ in client.calls))


class RegistryIntakeTests(unittest.TestCase):
    def test_ready_blob_checks_exact_images_before_intake(self):
        spec = importlib.util.spec_from_file_location('poller', ROOT / 'release-poller.py')
        poller = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(poller)
        sha = 'a' * 40
        images = {'backend': 'ghcr.io/opnform/hosted-backend@sha256:' + 'b' * 64,
                  'frontend': 'ghcr.io/opnform/hosted-frontend@sha256:' + 'c' * 64}
        checks = {name: {'commit': sha, 'status': 'success'} for name in
                  ('backend', 'frontend', 'lint', 'runtime-integration', 'private-packages')}
        checks['runtime-integration']['images'] = images
        release = {'repository': 'OpnForm/OpnForm', 'ref': poller.ALLOWED_REFS[0],
                   'environment': poller.RELEASE_ENVIRONMENT, 'sha': sha,
                   'backend_digest': 'sha256:' + 'b' * 64, 'frontend_digest': 'sha256:' + 'c' * 64,
                   'checks': checks, 'run_id': 1, 'run_attempt': 1}
        for change, valid in [(None, True), ('digest', False), ('ref', False)]:
            candidate = json.loads(json.dumps(release))
            if change == 'digest': candidate['backend_digest'] = 'sha256:' + 'd' * 64
            if change == 'ref': candidate['ref'] = 'refs/heads/untrusted'
            blob = json.dumps(candidate).encode()
            manifest = {'artifactType': poller.TYPE,
                        'layers': [{'digest': 'sha256:' + hashlib.sha256(blob).hexdigest(), 'mediaType': 'application/json'}]}
            with patch.dict(os.environ, {'GHCR_USERNAME': 'fixture', 'GHCR_TOKEN': 'fictional'}), \
                 patch.object(poller, 'get', side_effect=[b'{"token":"fixture"}', json.dumps(manifest).encode(), blob]):
                if valid:
                    self.assertEqual(poller.fetch_release()[0]['sha'], sha)
                else:
                    with self.assertRaises((RuntimeError, ValueError)):
                        poller.fetch_release()


if __name__ == '__main__':
    unittest.main()
