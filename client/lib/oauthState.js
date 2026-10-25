const PREFIX = 'opnform:oauth-state:'
const TTL = 5 * 60 * 1000

export function rememberOAuthState(provider, state, storage = window.localStorage, now = Date.now()) {
  if (typeof state !== 'string' || !state) throw new Error('Missing OAuth state')
  storage.setItem(PREFIX + state, JSON.stringify({ provider, expiresAt: now + TTL }))
}

export function consumeOAuthState(provider, state, storage = window.localStorage, now = Date.now()) {
  if (typeof state !== 'string' || !state) return false
  const value = storage.getItem(PREFIX + state)
  storage.removeItem(PREFIX + state)
  try {
    const flow = JSON.parse(value)
    return flow?.provider === provider && flow.expiresAt > now
  } catch {
    return false
  }
}
