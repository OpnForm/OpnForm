import copy
from target_fixtures import configure_test_target
import importlib.util
import os
from pathlib import Path
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('deploy', Path(__file__).parents[1] / 'deploy-preview.py')
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


def application(role):
    return {'uuid': deploy.APPS[role], 'environment': {'uuid': deploy.ENVIRONMENT, 'project': {'team_id': deploy.TEAM_ID}},
        'destination': {'server': {'uuid': deploy.SERVER}}, 'fqdn': deploy.DOMAINS.get(role),
        'build_pack': 'dockerimage', 'docker_registry_image_name': 'old', 'docker_registry_image_tag': 'old',
        'status': 'running:healthy', 'settings': {'is_auto_deploy_enabled': False,
            'is_consistent_container_name_enabled': role not in ('api','frontend'), 'stop_grace_period': 4000}}


class FakeClient:
    def __init__(self):
        self.apps = {value: application(role) for role, value in deploy.APPS.items()}
        self.calls = []
        self.histories = {}

    def request(self, method, path, data=None):
        self.calls.append((method, path, data))
        if path.startswith('deployments/applications/'):
            uuid, query = path.split('/')[-1].split('?')
            offset = int(query.split('&')[0].split('=')[1])
            rows = self.histories.get(uuid, [])
            return {'count': len(rows), 'deployments': rows[offset:offset+100]}
        if path.startswith('services/'):
            return {'environment_id': deploy.LEGACY_ENVIRONMENT_ID, 'server_id': deploy.LEGACY_SERVER_ID, 'status': 'exited:unhealthy'}
        if method == 'PATCH':
            self.apps[path.split('/')[1]].update(data)
        return copy.deepcopy(self.apps[path.split('/')[1]])


class DeploymentSafetyTest(unittest.TestCase):
    def setUp(self):
        configure_test_target(deploy)
        prepare = patch.object(deploy, 'prepare_images')
        self.addCleanup(prepare.stop)
        prepare.start()
        self.client = FakeClient()
        self.result = {'previous': {}, 'completed': [], 'deployments': {}}

    def run_release(self):
        deploy.run(self.client, 'sha256:'+'a'*64, 'sha256:'+'b'*64, 'c'*40, self.result)

    @patch.object(deploy, 'save')
    def test_migration_failure_never_promotes_other_resources(self, _):
        with patch.object(deploy, 'deploy', side_effect=RuntimeError('migration failed')):
            with self.assertRaises(RuntimeError): self.run_release()
        mutations = [call for call in self.client.calls if call[0] != 'GET']
        self.assertEqual([call[1] for call in mutations], ['applications/'+deploy.APPS['migrate']])
        self.assertNotIn('migration_succeeded', self.result)

    @patch.object(deploy, 'save')
    def test_production_domain_aborts_before_any_write(self, _):
        self.client.apps[deploy.APPS['api']]['fqdn'] = 'https://api.opnform.com'
        with self.assertRaises(RuntimeError): self.run_release()
        self.assertFalse(any(call[0] != 'GET' for call in self.client.calls))

    @patch.object(deploy, 'save')
    def test_foreign_server_aborts_before_any_write(self, _):
        self.client.apps[deploy.APPS['frontend']]['destination']['server']['uuid'] = 'production'
        with self.assertRaises(RuntimeError): self.run_release()
        self.assertFalse(any(call[0] != 'GET' for call in self.client.calls))

    @patch.object(deploy, 'save')
    def test_legacy_workers_require_a_graceful_manual_handoff(self, _):
        original = self.client.request
        def request(method, path, data=None):
            if path.startswith('services/'):
                return {'environment_id':deploy.LEGACY_ENVIRONMENT_ID,'server_id':deploy.LEGACY_SERVER_ID,'status':'running:unknown'}
            return original(method, path, data)
        self.client.request = request
        with self.assertRaises(RuntimeError): self.run_release()
        self.assertFalse(any(call[0] != 'GET' for call in self.client.calls))

    def test_missing_allowlist_aborts_before_network_access(self):
        with patch.object(deploy, 'ENVIRONMENT', ''):
            with self.assertRaises(RuntimeError): self.run_release()
        self.assertEqual(self.client.calls, [])

    def test_incomplete_legacy_allowlist_aborts_before_network_access(self):
        with patch.object(deploy, 'LEGACY_SERVER_ID', 0):
            with self.assertRaises(RuntimeError): self.run_release()
        self.assertEqual(self.client.calls, [])

    def test_mutable_tags_are_refused(self):
        with self.assertRaises(RuntimeError):
            deploy.run(self.client, 'latest', 'sha256:'+'b'*64, 'c'*40, self.result)
        self.assertEqual(self.client.calls, [])

    def test_singleton_resources_require_long_grace(self):
        app = application('jobs'); app['settings']['stop_grace_period'] = 10
        with self.assertRaises(RuntimeError): deploy.validate_app('jobs', app)

    def test_early_coolify_auto_deploy_is_refused(self):
        app = application('api'); app['settings']['is_auto_deploy_enabled'] = True
        with self.assertRaises(RuntimeError): deploy.validate_app('api', app)

    @patch.object(deploy, 'save')
    def test_remote_deployment_aborts_before_any_write(self, _):
        self.client.histories[deploy.APPS['jobs']] = [{'status': 'in_progress'}]
        with self.assertRaises(RuntimeError): self.run_release()
        self.assertFalse(any(call[0] != 'GET' for call in self.client.calls))

    def test_an_unrelated_apps_deployment_does_not_block(self):
        self.client.histories['another-app'] = [{'status': 'queued'}]
        deploy.assert_no_active_deployments(self.client)

    def test_active_deployment_on_a_later_page_blocks(self):
        self.client.histories[deploy.APPS['jobs']] = [{'status': 'finished'}] * 100 + [{'status': 'queued'}]
        with self.assertRaises(RuntimeError): deploy.assert_no_active_deployments(self.client)

    def test_cancelled_by_user_is_terminal(self):
        self.client.histories[deploy.APPS['jobs']] = [{'status': 'cancelled-by-user'}]
        deploy.assert_no_active_deployments(self.client)

    def test_incomplete_inventory_fails_closed(self):
        with patch.object(self.client, 'request', return_value={'count': 1, 'deployments': []}):
            with self.assertRaises(RuntimeError): deploy.assert_no_active_deployments(self.client)

    @patch.object(deploy, 'save')
    def test_start_request_is_journaled_before_network_failure(self, save):
        snapshots = []
        save.side_effect = lambda result: snapshots.append(copy.deepcopy(result))
        with patch.object(self.client, 'request', side_effect=TimeoutError):
            with self.assertRaises(TimeoutError): deploy.deploy(self.client, 'migrate', self.result)
        self.assertEqual(snapshots[0]['starting_role'], 'migrate')
        with self.assertRaises(RuntimeError):
            deploy.assert_no_active_deployments(self.client, snapshots[0])


class PrivateEndpointTest(unittest.TestCase):
    def test_exact_private_pin_is_accepted(self):
        with patch.dict(os.environ, {'COOLIFY_URL': 'http://100.64.0.1:8081/',
                                     'COOLIFY_ALLOWED_URL': 'http://100.64.0.1:8081',
                                     'COOLIFY_TOKEN': 'synthetic-test-token'}, clear=True):
            self.assertEqual(deploy.Coolify().url, 'http://100.64.0.1:8081')

    def test_missing_or_different_pin_is_rejected(self):
        for pin in ('', 'http://100.64.0.2:8081'):
            with self.subTest(pin=pin), patch.dict(os.environ, {
                    'COOLIFY_URL': 'http://100.64.0.1:8081', 'COOLIFY_ALLOWED_URL': pin}, clear=True):
                with self.assertRaises(RuntimeError): deploy.Coolify()

    def test_unsafe_pinned_endpoints_are_rejected(self):
        for url in ('http://192.0.2.1', 'http://example.com', 'http://user@100.64.0.1',
                    'http://100.64.0.1/path', 'http://100.64.0.1?query=1'):
            with self.subTest(url=url), patch.dict(os.environ, {
                    'COOLIFY_URL': url, 'COOLIFY_ALLOWED_URL': url}, clear=True):
                with self.assertRaises(RuntimeError): deploy.Coolify()


if __name__ == '__main__': unittest.main()
