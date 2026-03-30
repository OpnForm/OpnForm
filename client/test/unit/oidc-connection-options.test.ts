import { describe, it, expect } from 'vitest'
import { getOidcRequireStateDefault, getOidcRequireStateForEdit } from '../../lib/oidc/connection-options.js'

describe('oidc connection options', () => {
  it('defaults require_state to true for new connections', () => {
    expect(getOidcRequireStateDefault()).toBe(true)
  })

  it('returns false when require_state is missing', () => {
    expect(getOidcRequireStateForEdit(undefined)).toBe(false)
    expect(getOidcRequireStateForEdit({})).toBe(false)
  })

  it('returns stored require_state for existing connections', () => {
    expect(getOidcRequireStateForEdit({ require_state: true })).toBe(true)
    expect(getOidcRequireStateForEdit({ require_state: false })).toBe(false)
  })
})
