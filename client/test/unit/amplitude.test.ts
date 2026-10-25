import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
  init: vi.fn(),
  track: vi.fn(),
  identify: vi.fn(),
  setUserId: vi.fn(),
  createInstance: vi.fn(),
}))

vi.mock('@amplitude/analytics-browser', () => ({
  createInstance: mocks.createInstance,
  Identify: class {
    properties = {}
    set(key, value) { this.properties[key] = value; return this }
  },
}))

describe('Amplitude browser SDK migration', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { amplitudeCode: 'test-key', env: 'production' } }))
    mocks.init.mockReturnValue({ promise: Promise.resolve() })
    mocks.track.mockReturnValue({ promise: Promise.resolve({ code: 200 }) })
    mocks.identify.mockReturnValue({ promise: Promise.resolve({ code: 200 }) })
    mocks.createInstance.mockReturnValue(mocks)
  })

  it('initializes once and preserves explicit event names and properties', async () => {
    const { useAmplitude } = await import('../../composables/useAmplitude.js')
    await Promise.all([
      useAmplitude().logEvent('Form created', { form_id: 42 }),
      useAmplitude().logEvent('Form submitted', { form_id: 42 }),
    ])
    expect(mocks.createInstance).toHaveBeenCalledOnce()
    expect(mocks.init).toHaveBeenCalledWith('test-key', undefined, {
      autocapture: {
        attribution: true,
        pageViews: false,
        sessions: false,
        fileDownloads: false,
        formInteractions: false,
        elementInteractions: false,
        networkTracking: false,
        performanceTracking: false,
        pageUrlEnrichment: false,
        frustrationInteractions: false,
      },
    })
    expect(mocks.track).toHaveBeenCalledWith('Form created', { form_id: 42 })
    expect(mocks.track).toHaveBeenCalledWith('Form submitted', { form_id: 42 })
  })

  it('preserves user identity, email, subscription and plan', async () => {
    const { useAmplitude } = await import('../../composables/useAmplitude.js')
    await useAmplitude().setUser({ id: 42, email: 'test@example.test', is_subscribed: true, plan_tier: 'pro' })
    expect(mocks.setUserId).toHaveBeenCalledWith('42')
    expect(mocks.identify.mock.calls[0][0].properties).toEqual({ email: 'test@example.test', subscribed: true, plan_tier: 'pro' })
  })

  it('does not initialize without a configured key', async () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { amplitudeCode: null, env: 'production' } }))
    const { useAmplitude } = await import('../../composables/useAmplitude.js')
    await useAmplitude().logEvent('Form created')
    expect(mocks.init).not.toHaveBeenCalled()
  })

  it('retries initialization after an SDK failure', async () => {
    mocks.init.mockReturnValueOnce({ promise: Promise.reject(new Error('SDK unavailable')) })
    const { useAmplitude } = await import('../../composables/useAmplitude.js')
    await expect(useAmplitude().logEvent('First event')).rejects.toThrow('SDK unavailable')
    await useAmplitude().logEvent('Retried event')
    expect(mocks.init).toHaveBeenCalledTimes(2)
    expect(mocks.track).toHaveBeenCalledWith('Retried event', undefined)
  })
})
