import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('rehearsal', Path(__file__).with_name('cutover-rehearsal.py'))
rehearsal = importlib.util.module_from_spec(spec)
spec.loader.exec_module(rehearsal)


class CutoverJournalTest(unittest.TestCase):
    def ready(self):
        journal = rehearsal.Journal()
        journal.freeze_source()
        journal.verify_target(sql=True, files=True, queues=True)
        return journal

    def test_missing_evidence_or_source_fence_blocks_target(self):
        journal = rehearsal.Journal()
        with self.assertRaises(ValueError):
            journal.activate_target()
        journal.freeze_source()
        for missing in ['sql', 'files', 'queues']:
            evidence = dict(sql=True, files=True, queues=True)
            evidence[missing] = False
            with self.assertRaises(ValueError):
                journal.verify_target(**evidence)

    def test_simple_rollback_allowed_before_target_writes(self):
        journal = self.ready()
        journal.activate_target()
        journal.rollback_simple()
        self.assertEqual(journal.phase, 'source-writable')

    def test_postwrite_simple_rollback_and_premature_source_resume_refused(self):
        journal = self.ready()
        journal.activate_target()
        journal.note_target_write()
        with self.assertRaises(ValueError):
            journal.rollback_simple()
        with self.assertRaises(ValueError):
            journal.resume_source()
        with self.assertRaises(ValueError):
            journal.verify_reverse(sql=True, files=True, queues=True)

    def test_reverse_requires_all_three_proofs_and_frozen_target(self):
        journal = self.ready()
        journal.activate_target()
        journal.note_target_write()
        journal.freeze_target()
        with self.assertRaises(ValueError):
            journal.verify_reverse(sql=True, files=True, queues=False)
        journal.verify_reverse(sql=True, files=True, queues=True)
        journal.resume_source()
        self.assertEqual(journal.phase, 'source-writable')


if __name__ == '__main__':
    unittest.main()
