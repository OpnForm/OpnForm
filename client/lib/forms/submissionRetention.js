export const SUBMISSION_RETENTION_UNITS = [
  { name: 'Days', value: 'day' },
  { name: 'Weeks', value: 'week' },
  { name: 'Months', value: 'month' },
  { name: 'Years', value: 'year' }
]

export const SUBMISSION_RETENTION_SHORTCUTS = [
  { label: '1 week', value: 1, unit: 'week' },
  { label: '1 month', value: 1, unit: 'month' },
  { label: '6 months', value: 6, unit: 'month' },
  { label: '1 year', value: 1, unit: 'year' }
]

export function setSubmissionRetentionEnabled(form, enabled) {
  if (enabled) {
    return
  }

  form.submission_retention_value = null
  form.submission_retention_unit = null
}

export function syncSubmissionRetentionEnabled(currentState, value, unit) {
  if (value && unit) {
    return true
  }

  if (value == null && unit == null) {
    return false
  }

  return currentState
}

export function applySubmissionRetentionShortcut(form, shortcut) {
  form.submission_retention_value = shortcut.value
  form.submission_retention_unit = shortcut.unit
}
