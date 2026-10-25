import { describe, expect, it } from 'vitest'
import {
  SUBMISSION_RETENTION_SHORTCUTS,
  applySubmissionRetentionShortcut,
  setSubmissionRetentionEnabled,
  syncSubmissionRetentionEnabled
} from '../../lib/forms/submissionRetention.js'

describe('submission retention settings', () => {
  it('does not choose a destructive period when retention is enabled', () => {
    const form = {
      submission_retention_value: null,
      submission_retention_unit: null
    }

    setSubmissionRetentionEnabled(form, true)

    expect(form).toMatchObject({
      submission_retention_value: null,
      submission_retention_unit: null
    })
  })

  it('preserves an existing custom period when enabling retention', () => {
    const form = {
      submission_retention_value: 3,
      submission_retention_unit: 'week'
    }

    setSubmissionRetentionEnabled(form, true)

    expect(form).toMatchObject({
      submission_retention_value: 3,
      submission_retention_unit: 'week'
    })
  })

  it('clears both persisted fields when retention is disabled', () => {
    const form = {
      submission_retention_value: 6,
      submission_retention_unit: 'month'
    }

    setSubmissionRetentionEnabled(form, false)

    expect(form).toMatchObject({
      submission_retention_value: null,
      submission_retention_unit: null
    })
  })

  it.each(SUBMISSION_RETENTION_SHORTCUTS)(
    'applies the $label shortcut',
    (shortcut) => {
      const form = {
        submission_retention_value: null,
        submission_retention_unit: null
      }

      applySubmissionRetentionShortcut(form, shortcut)

      expect(form).toMatchObject({
        submission_retention_value: shortcut.value,
        submission_retention_unit: shortcut.unit
      })
    }
  )

  it('reflects retention values restored by undo', () => {
    expect(syncSubmissionRetentionEnabled(false, 3, 'day')).toBe(true)
  })

  it('reflects retention values cleared by undo', () => {
    expect(syncSubmissionRetentionEnabled(true, null, null)).toBe(false)
  })

  it('keeps the panel open while a period is partially entered', () => {
    expect(syncSubmissionRetentionEnabled(true, null, 'day')).toBe(true)
    expect(syncSubmissionRetentionEnabled(true, 3, null)).toBe(true)
  })
})
