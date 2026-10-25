import copy
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
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
                           ('automatic_deployment', 'true'), ('sha', 'main'), ('run_attempt', True)]:
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
        with patch.object(self.release, 'github', side_effect=[good, {'sha': 'c' * 40}]):
            self.release.verify_ci(self.candidate)

        for key, value in [('conclusion', 'failure'), ('status', 'in_progress'),
                           ('head_sha', 'd' * 40), ('event', 'pull_request'), ('run_attempt', 2)]:
            run = dict(good); run[key] = value
            with self.subTest(key=key), patch.object(self.release, 'github', return_value=run), self.assertRaises(ValueError):
                self.release.verify_ci(self.candidate)
        with patch.object(self.release, 'github', side_effect=[good, {'sha': 'd' * 40}]), self.assertRaises(ValueError):
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

    def test_automatic_release_requires_a_real_boolean_and_the_same_main_ci_gate(self):
        candidate = copy.deepcopy(self.candidate)
        candidate['automatic_deployment'] = True
        self.release.validate_release(candidate)
        candidate['automatic_deployment'] = 'true'
        with self.assertRaises(ValueError):
            self.release.validate_release(candidate)

    def test_local_controller_uses_only_the_installed_coolify_container(self):
        target = {'controller_local': True}
        with patch.object(self.release, 'execute', return_value='{}') as execute, \
                patch.object(self.release, 'ssh') as ssh:
            self.release.controller(target, self.candidate)
        self.assertEqual(execute.call_args.args[0], ['docker', 'exec', '-i', 'coolify', 'php'])
        ssh.assert_not_called()

    def run_controller_fixture(self, directory, candidate, **options):
        target = {'controller_local': True, 'service_uuid': 's' * 24,
                  'server_id': 5, 'project_id': 4, 'team_id': 0, 'native_address': '100.65.151.96'}
        with patch.object(self.release, 'execute', return_value='{}') as execute:
            self.release.controller(target, candidate, **options)
        # Execute the exact generated PHP against a model double. Only its
        # bootstrap and private history root are substituted for this fixture.
        program = '$x=json_decode' + execute.call_args.kwargs['input'].split('$x=json_decode', 1)[1]
        program = program.replace('/var/www/html/storage/app/opnform-release-history', directory)
        fixture = Path(__file__).with_name('coolify-model-fixture.php').read_text()
        return subprocess.run(['php'], input=fixture + '\nnamespace {\n' + program + '\n}\n',
                              capture_output=True, text=True, timeout=10)

    @unittest.skipUnless(shutil.which('php'), 'PHP is required for the generated controller fixture')
    def test_successful_same_sha_reruns_have_independent_history_and_release_their_lease(self):
        with tempfile.TemporaryDirectory() as directory:
            for attempt in (1, 2):
                candidate = {**self.candidate, 'run_attempt': attempt}
                applied = self.run_controller_fixture(directory, candidate, apply=True)
                self.assertEqual(applied.returncode, 0, applied.stderr)
                finished = self.run_controller_fixture(directory, candidate, finish=True)
                self.assertEqual(finished.returncode, 0, finished.stderr)
            root = Path(directory) / ('s' * 24)
            self.assertEqual(len(list(root.glob('*.json'))), 2)
            self.assertFalse((root / '.promotion-active').exists())

    @unittest.skipUnless(shutil.which('php'), 'PHP is required for the generated controller fixture')
    def test_another_attempt_of_the_same_sha_cannot_release_an_active_promotion(self):
        with tempfile.TemporaryDirectory() as directory:
            applied = self.run_controller_fixture(directory, self.candidate, apply=True)
            self.assertEqual(applied.returncode, 0, applied.stderr)
            other = {**self.candidate, 'run_attempt': 2}
            finished = self.run_controller_fixture(directory, other, finish=True)
            self.assertNotEqual(finished.returncode, 0)
            self.assertTrue((Path(directory) / ('s' * 24) / '.promotion-active').exists())
            finished = self.run_controller_fixture(directory, self.candidate, finish=True)
            self.assertEqual(finished.returncode, 0, finished.stderr)

    def test_new_image_pins_are_persisted_only_after_successful_runtime_verification(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'target.json'
            target = {'product': 'opnform', 'service_uuid': 's' * 24, 'expected_current_images': {'backend': 'old'}}
            path.write_text(json.dumps(target))
            with patch.object(self.release, 'native', side_effect=RuntimeError('not healthy')):
                with self.assertRaises(RuntimeError):
                    self.release.remember_current_images(target, self.candidate, path)
            self.assertEqual(json.loads(path.read_text()), target)
            images = {'backend': 'sha256:' + 'a' * 64, 'frontend': 'sha256:' + 'b' * 64}
            with patch.object(self.release, 'native', return_value=images):
                self.release.remember_current_images(target, self.candidate, path)
            self.assertEqual(json.loads(path.read_text())['expected_current_images'], images)
            self.assertEqual(path.stat().st_mode & 0o777, 0o600)

    def test_old_main_without_a_release_artifact_cannot_trigger_the_controller(self):
        automatic = module('auto-release.py')
        with patch.object(automatic.promotion, 'github', side_effect=[{'sha': 'c' * 40},
                {'workflow_runs': [{'id': 42, 'head_sha': 'c' * 40, 'name': 'CI/CD Pipeline'}]},
                {'artifacts': []}]):
            self.assertIsNone(automatic.eligible_run())

    def test_automatic_controller_check_only_never_applies_even_an_eligible_release(self):
        automatic = module('auto-release.py')
        candidate = copy.deepcopy(self.candidate)
        candidate['automatic_deployment'] = True
        run = {'id': 42, 'run_attempt': 1, 'head_sha': 'c' * 40}
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); target = root / 'target.json'; target.write_text('{}')
            descriptor = root / 'release.json'; descriptor.write_text(json.dumps(candidate))
            with patch('sys.argv', ['auto', '--target', str(target), '--state-dir', str(root), '--check-only']), \
                    patch.object(automatic.promotion, 'validate_target'), \
                    patch.object(automatic, 'eligible_run', return_value=run), \
                    patch.object(automatic, 'fetch_release', return_value=(candidate, descriptor)), \
                    patch.object(automatic.promotion, 'verify_ci'), \
                    patch.object(automatic.promotion, 'controller'), \
                    patch.object(automatic.promotion, 'check_current'), \
                    patch.object(automatic.subprocess, 'run') as apply:
                automatic.main()
            apply.assert_not_called()
            self.assertFalse((root / 'state.json').exists())

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
        for legacy in ['vapor deploy', 'AMPLIFY_DEPLOY_URL', 'VAPOR_API_TOKEN', 'production-deploy:', 'build:amplify', 'name: amplify-output']:
            self.assertNotIn(legacy, workflow)
        self.assertIn('scaleway-runtime]', workflow)
        self.assertIn('private OpnForm controller deploys after this CI run completes successfully', workflow)


if __name__ == '__main__':
    unittest.main()
