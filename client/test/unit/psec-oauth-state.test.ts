import { describe, it, expect } from 'vitest'
import { rememberOAuthState, consumeOAuthState } from '../../lib/oauthState'

function storage() {
  const values = new Map()
  return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: (key) => values.delete(key) }
}

describe('OAuth browser initiation', () => {
  it('accepts the initiated provider once', () => {
    const cache = storage()
    rememberOAuthState('google', 'state', cache, 0)
    expect(consumeOAuthState('google', 'state', cache, 1)).toBe(true)
    expect(consumeOAuthState('google', 'state', cache, 2)).toBe(false)
  })
  it('rejects unsolicited, cross-provider, malformed and expired states', () => {
    const cache = storage()
    expect(consumeOAuthState('google', 'unsolicited', cache, 1)).toBe(false)
    rememberOAuthState('stripe', 'state', cache, 0)
    expect(consumeOAuthState('google', 'state', cache, 1)).toBe(false)
    rememberOAuthState('google', 'expired', cache, 0)
    expect(consumeOAuthState('google', 'expired', cache, 300001)).toBe(false)
    cache.setItem('opnform:oauth-state:broken', '{')
    expect(consumeOAuthState('google', 'broken', cache, 1)).toBe(false)
  })
})
