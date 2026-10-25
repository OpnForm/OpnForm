import copy
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2] / 'deploy/scaleway'


def module(file):
    spec = importlib.util.spec_from_file_location(file, ROOT / file)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


class ServiceReleaseTests(unittest.TestCase):
    def setUp(self):
        self.release = module('coolify-service-release.py')
        self.candidate = {'schema': 'opnform-coolify-service-release/v1',
            'repository': 'OpnForm/OpnForm', 'ref': 'refs/heads/main', 'sha': 'c' * 40,
            'run_id': 42, 'run_attempt': 1, 'automatic_deployment': False,
            'images': {r: 'ghcr.io/opnform/hosted-' + r + '@sha256:' + 'a' * 64
                       for r in ('backend', 'frontend')}}

    def test_only_an_explicit_complete_immutable_opnform_pair_is_accepted(self):
        self.release.validate_release(self.candidate)
        for key, value in [('repository', 'other/repo'), ('ref', 'refs/pull/1/merge'),
                           ('automatic_deployment', True), ('sha', 'main'), ('run_attempt', True)]:
            candidate = copy.deepcopy(self.candidate); candidate[key] = value
            with self.subTest(key=key), self.assertRaises(ValueError):
                self.release.validate_release(candidate)
        for image in ['ghcr.io/opnform/hosted-backend:latest',
                      'ghcrXio/opnform/hosted-backend@sha256:' + 'a' * 64,
                      'ghcr.io/other/hosted-backend@sha256:' + 'a' * 64]:
            candidate = copy.deepcopy(self.candidate); candidate['images']['backend'] = image
            with self.subTest(image=image), self.assertRaises(ValueError):
                self.release.validate_release(candidate)

    def test_ci_must_be_complete_and_green_for_the_current_main_commit_and_attempt(self):
        good = {'name': 'CI/CD Pipeline', 'head_sha': 'c' * 40, 'head_branch': 'main',
                'event': 'push', 'status': 'completed', 'conclusion': 'success', 'run_attempt': 1}
        with patch.object(self.release, 'execute', side_effect=[json.dumps(good), json.dumps({'sha': 'c' * 40})]):
            self.release.verify_ci(self.candidate)

        for key, value in [('conclusion', 'failure'), ('status', 'in_progress'),
                           ('head_sha', 'd' * 40), ('event', 'pull_request'), ('run_attempt', 2)]:
            run = dict(good); run[key] = value
            with self.subTest(key=key), patch.object(self.release, 'execute', return_value=json.dumps(run)), self.assertRaises(ValueError):
                self.release.verify_ci(self.candidate)
        with patch.object(self.release, 'execute', side_effect=[json.dumps(good), json.dumps({'sha': 'd' * 40})]), self.assertRaises(ValueError):
            self.release.verify_ci(self.candidate)

    def test_native_archive_reference_is_bound_to_the_immutable_digest(self):
        image = self.candidate['images']['backend']
        self.assertEqual(self.release.native_image_name(image), 'opnform-hosted-backend:sha256-' + 'a' * 64)
        meta = {'Architecture': 'amd64', 'Os': 'linux', 'Config': {'Labels': {'revision': 'c' * 40}},
                'RootFS': {'Type': 'layers', 'Layers': ['sha256:' + 'a' * 64]}, 'Id': 'engine-dependent'}
        original = self.release.image_fingerprint(meta)
        meta['Id'] = 'another-engine-identity'
        meta['Config']['AttachStdin'] = False
        meta['Config']['Cmd'] = None
        self.assertEqual(self.release.image_fingerprint(meta), original)
        meta['RootFS']['Layers'].append('sha256:' + 'b' * 64)
        self.assertNotEqual(self.release.image_fingerprint(meta), original)
    def test_no_apply_means_no_registry_pull_config_mutation_or_role_restart(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); descriptor = root / 'release.json'; descriptor.write_text(json.dumps(self.candidate))
            target = root / 'target.json'; target.write_text(json.dumps({'service_uuid': 's' * 24}))
            with patch('sys.argv', ['promote', str(descriptor), '--target', str(target)]), \
                    patch.object(self.release, 'validate_target'), patch.object(self.release, 'verify_ci'), \
                    patch.object(self.release, 'controller') as controller, patch.object(self.release, 'check_current'), \
                    patch.object(self.release, 'stage_images') as stage, patch.object(self.release, 'promote') as promote:
                self.release.main()
            controller.assert_called_once()
            self.assertNotIn('apply', controller.call_args.kwargs)
            stage.assert_not_called(); promote.assert_not_called()

    def test_private_registry_visibility_is_checked_and_public_packages_are_rejected(self):
        publisher = module('publish-release.py')
        response = type('Response', (), {'returncode': 0, 'stdout': '{"visibility":"public"}'})()
        with patch.object(publisher.subprocess, 'run', return_value=response), self.assertRaises(RuntimeError):
            publisher.package_is_private('hosted-backend')

    def test_publishing_cannot_be_invoked_from_a_pr(self):
        publisher = module('publish-release.py')
        env = {'GITHUB_SHA': 'c' * 40, 'GITHUB_REPOSITORY': 'OpnForm/OpnForm',
               'GITHUB_REF': 'refs/pull/42/merge', 'GITHUB_EVENT_NAME': 'pull_request'}
        with patch.dict(os.environ, env), patch('sys.argv', ['publish', '--output', '/tmp/not-created']), \
                patch.object(publisher, 'command') as command, self.assertRaises(RuntimeError):
            publisher.main()
        command.assert_not_called()

    def test_main_workflow_cannot_call_legacy_deployments(self):
        workflow = (ROOT.parents[1] / '.github/workflows/ci-cd.yml').read_text()
        for legacy in ['vapor deploy', 'AMPLIFY_DEPLOY_URL', 'VAPOR_API_TOKEN', 'production-deploy:']:
            self.assertNotIn(legacy, workflow)
        self.assertIn('scaleway-runtime]', workflow)
        self.assertIn('No production restart or AWS deployment', workflow)


if __name__ == '__main__':
    unittest.main()
