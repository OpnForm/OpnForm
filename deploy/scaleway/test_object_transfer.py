import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('object_transfer', Path(__file__).with_name('object-transfer.py'))
transfer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(transfer)


class ObjectTransferTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name).resolve()
        self.source = transfer.initialize(self.root / 'source')
        self.target = transfer.initialize(self.root / 'target')

    def tearDown(self):
        self.temp.cleanup()

    def put(self, folder, key, data=b'data', headers=None):
        path = folder / key
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(data)
        metadata = json.loads((folder / transfer.METADATA).read_text())
        metadata[key] = headers or {}
        transfer.atomic_json(folder / transfer.METADATA, metadata)

    def remove(self, folder, key):
        (folder / key).unlink()
        metadata = json.loads((folder / transfer.METADATA).read_text())
        metadata.pop(key, None)
        transfer.atomic_json(folder / transfer.METADATA, metadata)

    def sync(self, quarantine=False):
        return transfer.apply(self.source, self.target,
                              transfer.plan(self.source, self.target), quarantine)

    def test_unicode_binary_empty_and_headers_roundtrip(self):
        self.put(self.source, 'réponses/漢字 signature %.png', bytes(range(256)),
                 {'content-type': 'image/png', 'content-disposition': 'attachment; filename="é.png"'})
        self.put(self.source, 'empty', b'')
        self.assertTrue(self.sync()['exact_match'])
        self.assertEqual((self.target / 'empty').stat().st_size, 0)

    def test_full_checksum_detects_same_size_corruption_and_metadata_only_change(self):
        self.put(self.source, 'a', b'AAAA', {'content-type': 'text/plain'})
        self.put(self.source, 'b', b'BBBB')
        self.sync()
        (self.target / 'b').write_bytes(b'CCCC')
        self.put(self.source, 'a', b'AAAA', {'content-type': 'application/octet-stream'})
        self.assertEqual(transfer.plan(self.source, self.target)['copy'], ['a', 'b'])
        self.assertTrue(self.sync()['exact_match'])

    def test_precopy_retains_extras_final_delta_quarantines_without_losing_deleted_bytes(self):
        self.put(self.target, 'tombstones.json', b'old object must survive')
        self.put(self.source, 'new')
        self.assertEqual(self.sync()['retained_extras'], 1)
        result = self.sync(quarantine=True)
        self.assertEqual(result['quarantined'], 1)
        self.assertTrue(result['exact_match'])
        quarantined = list((self.target / '.opnform-transfer-quarantine').glob('*/tombstones.json'))
        self.assertEqual(quarantined[0].read_bytes(), b'old object must survive')

    def test_stale_source_or_target_rejected_before_commit(self):
        self.put(self.source, 'a')
        proposal = transfer.plan(self.source, self.target)
        self.put(self.source, 'a', b'new')
        with self.assertRaises(ValueError):
            transfer.apply(self.source, self.target, proposal)
        self.assertFalse((self.target / 'a').exists())
        proposal = transfer.plan(self.source, self.target)
        self.put(self.target, 'writer', b'concurrent')
        with self.assertRaises(ValueError):
            transfer.apply(self.source, self.target, proposal)

    def test_plan_tampering_and_target_identity_swap_refused(self):
        self.put(self.source, 'a')
        proposal = transfer.plan(self.source, self.target)
        proposal['copy'] = ['../outside']
        with self.assertRaises(ValueError):
            transfer.apply(self.source, self.target, proposal)
        other = transfer.initialize(self.root / 'other')
        with self.assertRaises(ValueError):
            transfer.apply(self.source, other, transfer.plan(self.source, self.target))

    def test_source_mutation_during_staging_preserves_target_objects(self):
        self.put(self.source, 'a')
        original = transfer.shutil.copyfile
        def changing_copy(src, dst):
            original(src, dst)
            Path(src).write_bytes(b'concurrent writer')
        with patch.object(transfer.shutil, 'copyfile', side_effect=changing_copy):
            with self.assertRaises(ValueError):
                self.sync()
        self.assertFalse((self.target / 'a').exists())

    def test_symlinks_hardlinks_and_unmarked_real_trees_refused(self):
        (self.source / 'link').symlink_to(self.root / 'outside')
        with self.assertRaises(ValueError):
            transfer.manifest(self.source)
        (self.source / 'link').unlink()
        self.put(self.source, 'a')
        os.link(self.source / 'a', self.source / 'hardlink')
        with self.assertRaises(ValueError):
            transfer.manifest(self.source)
        unmarked = self.root / 'unmarked'
        unmarked.mkdir()
        with self.assertRaises(OSError):
            transfer.manifest(unmarked)
        with self.assertRaises(FileExistsError):
            transfer.initialize(unmarked)

    def test_nested_roots_prefix_collisions_and_traversal_refused(self):
        nested = transfer.initialize(self.source / 'nested')
        with self.assertRaises(ValueError):
            transfer.plan(self.source, nested)
        self.put(self.source, 'collision')
        self.put(self.target, 'collision/child')
        with self.assertRaises(ValueError):
            transfer.plan(self.source, self.target)
        for value in ['../secret', '/secret', 'a//b', 'a/./b', 'a\\b', transfer.MARKER]:
            with self.assertRaises(ValueError):
                transfer.key(value)

    def test_retry_after_partial_commit_and_zero_delta(self):
        self.put(self.source, 'a')
        self.put(self.source, 'b')
        original = transfer.os.replace
        def interrupted(src, dst):
            if Path(dst) == self.target / 'b':
                raise OSError('simulated interrupted commit')
            original(src, dst)
        with patch.object(transfer.os, 'replace', side_effect=interrupted):
            with self.assertRaises(OSError):
                self.sync()
        self.assertTrue((self.target / 'a').exists())
        self.assertFalse((self.target / 'b').exists())
        self.assertTrue(self.sync()['exact_match'])
        self.assertEqual(self.sync()['copied'], 0)


if __name__ == '__main__':
    unittest.main()
