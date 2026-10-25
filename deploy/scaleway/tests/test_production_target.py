import importlib.util
from target_fixtures import configure_test_target
import json
import os
from pathlib import Path
from types import SimpleNamespace
import unittest
from unittest.mock import patch, Mock

ROOT = Path(__file__).parents[1]


def module(name, file):
    spec = importlib.util.spec_from_file_location(name, ROOT / file)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


class ProductionTargetTest(unittest.TestCase):
    def setUp(self):
        self.profile = module('production', 'production-target.py')
        self.deploy = module('deploy', 'deploy-preview.py')
        configure_test_target(self.deploy)
        self.target = {'environment': 'p' * 24, 'server': self.deploy.SERVER,
                       'apps': {role: chr(97 + i) * 24 for i, role in enumerate(self.deploy.APPS)}}

    def configure(self):
        path = Mock()
        path.read_text.return_value = json.dumps(self.target)
        self.profile.configure(self.deploy, path)

    def test_explicit_production_profile_preserves_server_team_and_role_validation(self):
        self.configure()
        self.assertIsNone(self.deploy.LEGACY_SERVICE)
        self.assertEqual(self.deploy.DOMAINS['api'], 'https://api.opnform.com')
        app = {'uuid': self.target['apps']['api'],
               'environment': {'uuid': self.target['environment'], 'project': {'team_id': self.deploy.TEAM_ID}},
               'destination': {'server': {'uuid': self.target['server']}},
               'fqdn': 'https://api.opnform.com', 'build_pack': 'dockerimage',
               'settings': {'is_auto_deploy_enabled': False}}
        self.deploy.validate_app('api', app)
        app['environment']['project']['team_id'] = 0
        with self.assertRaises(RuntimeError): self.deploy.validate_app('api', app)

    def test_missing_preview_allowlist_blocks_production_profile(self):
        self.deploy.APPS['api'] = ''
        with self.assertRaises(RuntimeError): self.configure()

    def test_preview_resource_reuse_is_rejected(self):
        self.target['apps']['api'] = self.deploy.APPS['api']
        with self.assertRaises(RuntimeError): self.configure()

    def test_preview_environment_reuse_is_rejected(self):
        self.target['environment'] = self.deploy.ENVIRONMENT
        with self.assertRaises(RuntimeError): self.configure()

    def test_duplicate_role_ids_are_rejected(self):
        self.target['apps']['jobs'] = self.target['apps']['scheduler']
        with self.assertRaises(RuntimeError): self.configure()

    def test_foreign_server_is_rejected(self):
        self.target['server'] = 'other'
        with self.assertRaises(RuntimeError): self.configure()

    def test_missing_roles_are_rejected(self):
        del self.target['apps']['scheduler']
        with self.assertRaises(RuntimeError): self.configure()

    def test_unarmed_production_controller_refuses_even_before_network_access(self):
        poller = module('poller', 'release-poller.py')
        with patch.dict(os.environ, {'OPNFORM_RELEASE_ENVIRONMENT': 'production',
                                    'OPNFORM_PRODUCTION_ENABLED': 'false'}):
            with self.assertRaises(RuntimeError): poller.configure_target()
        self.assertEqual(poller.TAG, 'preview-ready')

    def test_preview_remains_default(self):
        poller = module('poller', 'release-poller.py')
        with patch.dict(os.environ, {}, clear=True): poller.configure_target()
        self.assertEqual(poller.RELEASE_ENVIRONMENT, 'opnform-preview')
        self.assertEqual(poller.TAG, 'preview-ready')


if __name__ == '__main__': unittest.main()
