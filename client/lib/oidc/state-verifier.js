const stateVerifierStorageKey = (slug, state) => `oidc_state_verifier:${slug}:${state}`

export const storeOidcStateVerifier = (slug, state, verifier) => {
  if (import.meta.server || !slug || !state || !verifier) {
    return
  }

  try {
    sessionStorage.setItem(stateVerifierStorageKey(slug, state), verifier)
  } catch {
    // Ignore storage failures. The callback API will reject the state if the verifier cannot be saved.
  }
}

export const consumeOidcStateVerifier = (slug, state) => {
  if (import.meta.server || !slug || !state) {
    return null
  }

  try {
    const key = stateVerifierStorageKey(slug, state)
    const verifier = sessionStorage.getItem(key)

    if (verifier) {
      sessionStorage.removeItem(key)
    }

    return verifier
  } catch {
    return null
  }
}
