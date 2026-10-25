import { beforeEach, describe, expect, it, vi } from 'vitest'

const getSpy = vi.hoisted(() => vi.fn())

vi.mock('../../api/base.js', () => ({
  apiService: {
    get: getSpy,
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

import { oidcApi } from '../../api/oidc.js'

describe('oidcApi callback', () => {
  beforeEach(() => {
    getSpy.mockReset()
  })

  it('disables retries because an authorization code can only be redeemed once', () => {
    oidcApi.callback('company-sso', { code: 'single-use-code', state: 'state-token' }, 'state-verifier')

    expect(getSpy).toHaveBeenCalledWith('/auth/company-sso/callback?code=single-use-code&state=state-token', {
      headers: {
        Accept: 'application/json',
        'X-OIDC-State-Verifier': 'state-verifier',
      },
      retry: false,
    })
  })
})
