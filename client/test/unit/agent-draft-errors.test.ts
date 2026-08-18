import { describe, expect, it } from 'vitest'
import { isAgentDraftVersionConflict } from '../../lib/forms/agent-draft-errors.js'

describe('isAgentDraftVersionConflict', () => {
  it('recognizes an expected version validation error', () => {
    expect(isAgentDraftVersionConflict({
      statusCode: 422,
      data: {
        errors: {
          expected_version: ['Draft version conflict.'],
        },
      },
    })).toBe(true)
  })

  it('does not treat definition validation errors as version conflicts', () => {
    expect(isAgentDraftVersionConflict({
      statusCode: 422,
      data: {
        errors: {
          'definition.properties.0.select.options': ['At least one option is required.'],
        },
      },
    })).toBe(false)
  })

  it('does not treat non-validation failures as version conflicts', () => {
    expect(isAgentDraftVersionConflict({
      statusCode: 500,
      data: {
        errors: {
          expected_version: ['Unexpected error.'],
        },
      },
    })).toBe(false)
  })
})
