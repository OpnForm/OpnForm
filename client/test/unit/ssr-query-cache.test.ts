import { describe, it, expect, vi } from 'vitest'
import { QueryClient, dehydrate } from '@tanstack/vue-query'
import { registerSsrQueryCleanup } from '../../lib/ssr-query-cache.js'
import { isMarketingRoute } from '../../lib/marketing-route.js'

describe('SSR request cache lifetime', () => {
  it('releases every query after dehydration while retaining the response payload', () => {
    const callbacks = new Map()
    const nuxt = { hooks: { hook: (event, callback) => callbacks.set(event, callback) } }
    const client = new QueryClient({ defaultOptions: { queries: { gcTime: Infinity } } })
    const state = { value: null }
    registerSsrQueryCleanup(nuxt, client, state, dehydrate)
    client.setQueryData(['form'], { title: 'Public form', fields: ['Œ', 'é', '中文'] })
    callbacks.get('app:rendered')()
    expect(client.getQueryCache().getAll()).toHaveLength(0)
    expect(state.value.queries[0].state.data.fields).toEqual(['Œ', 'é', '中文'])
  })
  it('clears aborted renders without serializing failed request data', () => {
    const callbacks = new Map()
    const client = new QueryClient()
    const state = { value: null }
    const dehydrateSpy = vi.fn()
    registerSsrQueryCleanup({ hooks: { hook: (event, callback) => callbacks.set(event, callback) } }, client, state, dehydrateSpy)
    client.setQueryData(['user'], { id: 42 })
    callbacks.get('app:error')()
    expect(client.getQueryCache().getAll()).toHaveLength(0)
    expect(dehydrateSpy).not.toHaveBeenCalled()
    expect(state.value).toBeNull()
  })
})

it.each(['/home', '/forms/a/edit', '/settings', '/admin', '/oauth/callback', '/setup', '/forms/create', '/mcp/private'])('keeps private and authentication routes synchronous: %s', (path) => {
  expect(isMarketingRoute({ path })).toBe(false)
})
it.each(['/', '/pricing', '/integrations', '/integrations/slack', '/opnform-vs-typeform', '/ai-form-builder'])('defers private bootstrap on marketing route %s', (path) => {
  expect(isMarketingRoute({ path })).toBe(true)
})
