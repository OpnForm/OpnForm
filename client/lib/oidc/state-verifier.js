const stateVerifierStorageKey = (slug, state) => `oidc_state_verifier:${slug}:${state}`
const automaticRetryStorageKey = (slug) => `oidc_automatic_retry:${slug}`

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

export const canAutomaticallyRetryOidcSignIn = (slug) => {
  if (import.meta.server || !slug) {
    return false
  }

  try {
    return !sessionStorage.getItem(automaticRetryStorageKey(slug))
  } catch {
    return false
  }
}

export const markOidcAutomaticRetry = (slug) => {
  if (import.meta.server || !slug) {
    return
  }

  try {
    sessionStorage.setItem(automaticRetryStorageKey(slug), '1')
  } catch {
    // Ignore storage failures and fall back to the manual recovery screen.
  }
}

export const clearOidcAutomaticRetry = (slug) => {
  if (import.meta.server || !slug) {
    return
  }

  try {
    sessionStorage.removeItem(automaticRetryStorageKey(slug))
  } catch {
    // Ignore storage failures. A fresh manual login can still continue normally.
  }
}
