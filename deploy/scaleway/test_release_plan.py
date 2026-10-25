import copy
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('release', Path(__file__).with_name('release-plan.py'))
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseGateTests(unittest.TestCase):
    def candidate(self):
        commit = 'a' * 40
        images = {'backend': 'ghcr.io/opnform/hosted-backend@sha256:' + 'b' * 64,
                  'frontend': 'ghcr.io/opnform/hosted-frontend@sha256:' + 'c' * 64}
        checks = {name: {'status': 'success', 'commit': commit} for name in
                  ('backend', 'frontend', 'lint', 'runtime-integration', 'private-packages')}
        checks['runtime-integration']['images'] = copy.deepcopy(images)
        return {'commit': commit, 'environment': 'preview', 'images': images, 'checks': checks}

    def test_migration_blocks_before_all_runtime_roles(self):
        steps = release.plan(self.candidate())
        self.assertEqual([step['role'] for step in steps], ['migrate', 'jobs', 'scheduler', 'api', 'frontend'])
        self.assertEqual(len({step['image'] for step in steps[:4]}), 1)

    def test_mutable_tag_cannot_be_promoted(self):
        data = self.candidate()
        data['images']['backend'] = 'ghcr.io/opnform/hosted-backend:latest'
        with self.assertRaises(ValueError):
            release.plan(data)

    def test_mixed_commits_cannot_be_promoted(self):
        data = self.candidate()
        data['checks']['backend']['commit'] = 'd' * 40
        with self.assertRaises(ValueError):
            release.plan(data)

    def test_untested_rebuilt_image_cannot_be_promoted(self):
        data = self.candidate()
        data['images']['backend'] = 'ghcr.io/opnform/hosted-backend@sha256:' + 'd' * 64
        with self.assertRaises(ValueError):
            release.plan(data)

    def test_public_or_unknown_packages_cannot_be_promoted(self):
        data = self.candidate()
        del data['checks']['private-packages']
        with self.assertRaises(ValueError):
            release.plan(data)


if __name__ == '__main__':
    unittest.main()
