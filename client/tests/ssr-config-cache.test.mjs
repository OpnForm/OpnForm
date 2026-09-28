import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { mkdtemp, rm, readFile, writeFile } from 'node:fs/promises'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { join } from 'node:path'
import { createNitro, build, prepare } from 'nitropack'
import { ofetch } from 'ofetch'

const client = fileURLToPath(new URL('../', import.meta.url))
const listen = (server) => new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(`http://127.0.0.1:${server.address().port}`)))
const close = (server) => new Promise((resolve) => server.close(resolve))
const endpoints = [
  { name: 'feature-flags', path: '/content/feature-flags', state: 'featureFlags', refresh: 'refreshFeatureFlags', data: { self_hosted: false, services: {}, version: 'first' } },
  { name: 'plan-catalog', path: '/content/plans', state: 'planCatalog', refresh: 'refreshPlanCatalog', data: { tiers: { pro: { name: 'Pro' } }, version: 'first' } }
]

async function eventually(check) {
  for (let attempt = 0; attempt < 100; attempt++) {
    if (await check()) return
    await new Promise((resolve) => setTimeout(resolve, 10))
  }
  assert.fail('Background cache refresh did not complete')
}

test('SSR configuration uses the real Nitro cache across requests', { timeout: 60000 }, async (t) => {
  const directory = await mkdtemp(join(client, '.ssr-cache-test-'))
  const calls = []
  const upstream = new Map()
  const states = new Map()
  const plugins = new Map()
  const originalNow = Date.now
  const globalKeys = ['defineNuxtPlugin', 'useState', '$fetch', '__testContentApi']
  const originalGlobals = new Map(globalKeys.map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]))
  const backend = createServer((req, res) => {
    calls.push({ path: req.url, headers: req.headers })
    const result = upstream.get(req.url)
    res.writeHead(result?.status || 404, { 'content-type': 'application/json' })
    res.end(JSON.stringify(result?.data || {}))
  })
  let server
  let nitro
  try {
    const apiBase = await listen(backend)
    const resetHandler = join(directory, 'reset.js')
    await writeFile(resetHandler, "export default defineEventHandler(async () => { await useStorage('cache').clear(); return {} })")
    nitro = await createNitro({
      rootDir: directory, preset: 'node-listener', logLevel: 0, compatibilityDate: '2024-10-30',
      runtimeConfig: { privateApiBase: apiBase, public: { apiBase: 'http://unused.invalid' }, apiSecret: 'test-ssr-secret' },
      handlers: [
        ...endpoints.map(({ name }) => ({ route: `/api/${name}`, handler: join(client, 'server/api', `${name}.get.js`) })),
        { route: '/__test/reset', handler: resetHandler }
      ],
      storage: { cache: { driver: 'memory' } },
      externals: { inline: [/./] }
    })
    await prepare(nitro)
    await build(nitro)
    const { listener } = await import(pathToFileURL(join(directory, '.output/server/index.mjs')).href)
    server = createServer(listener)
    const origin = await listen(server)
    const read = (name, options = {}) => ofetch(`/api/${name}`, { baseURL: origin, retry: 0, ...options })
    globalThis.defineNuxtPlugin = (plugin) => plugin
    globalThis.useState = (key, init) => {
      if (!states.has(key)) states.set(key, { value: init() })
      return states.get(key)
    }
    globalThis.$fetch = (path, options) => ofetch(path, { baseURL: origin, ...options })
    // Bootstrap must use Nitro; explicitly requested refreshes use this direct API.
    globalThis.__testContentApi = {
      featureFlags: { list: (options) => ofetch('/content/feature-flags', { baseURL: apiBase, ...options }) },
      plans: { list: (options) => ofetch('/content/plans', { baseURL: apiBase, ...options }) }
    }
    for (const { name } of endpoints) {
      for (const side of ['server', 'client']) {
        const source = await readFile(join(client, 'plugins', `${name}.${side}.js`), 'utf8')
        const filename = join(directory, `${name}.${side}.mjs`)
        await writeFile(filename, source.replace("import { contentApi } from '~/api/content'", 'const contentApi = globalThis.__testContentApi'))
        plugins.set(`${name}.${side}`, (await import(pathToFileURL(filename).href)).default)
      }
    }
    t.beforeEach(async () => {
      Date.now = originalNow
      await fetch(`${origin}/__test/reset`)
      calls.length = 0
      states.clear()
      for (const { path, data } of endpoints) upstream.set(path, { status: 200, data })
    })

    for (const endpoint of endpoints) {
      const { name, path, data, state, refresh } = endpoint
      await t.test(`${name}: concurrent requests and changing query/visitor headers share one fetch`, async () => {
        const results = await Promise.all(Array.from({ length: 40 }, (_, i) => read(name, {
          query: { t: i }, headers: { authorization: 'Bearer visitor-token', cookie: `visitor=${i}`, 'x-custom-domain': 'visitor.example', 'x-api-secret': 'visitor-secret' }
        })))
        for (const result of results) assert.deepEqual(result, data)
        assert.equal(calls.length, 1)
        assert.equal(calls[0].path, path)
        assert.equal(calls[0].headers.authorization, undefined)
        assert.equal(calls[0].headers.cookie, undefined)
        assert.equal(calls[0].headers['x-custom-domain'], undefined)
        assert.equal(calls[0].headers['x-api-secret'], 'test-ssr-secret')
      })

      await t.test(`${name}: cold 429 is not retried by either SSR or upstream fetch`, async () => {
        upstream.set(path, { status: 429, data: {} })
        await plugins.get(`${name}.server`)({ provide() {} })
        assert.equal(calls.length, 1)
        assert.deepEqual(states.get(state).value, name === 'feature-flags' ? {} : { tiers: {} })
        upstream.set(path, { status: 200, data })
        assert.deepEqual(await read(name), data)
        assert.equal(calls.length, 2)
      })

      await t.test(`${name}: invalid successful responses do not poison the cache`, async () => {
        upstream.set(path, { status: 200, data: {} })
        await assert.rejects(read(name), (error) => error.statusCode === 502)
        upstream.set(path, { status: 200, data })
        assert.deepEqual(await read(name), data)
        assert.equal(calls.length, 2)
      })

      await t.test(`${name}: repeated SSR, hydration and explicit refresh keep their semantics`, async () => {
        const provided = {}
        const app = { provide: (key, value) => { provided[key] = value } }
        for (let i = 0; i < 10; i++) {
          states.clear()
          await plugins.get(`${name}.server`)(app)
          assert.deepEqual(states.get(state).value, data)
        }
        assert.equal(calls.length, 1)
        await plugins.get(`${name}.client`)(app)
        assert.equal(calls.length, 1, 'client hydration must not refetch')
        // Preserve the explicit cache-busting query sent by the client refresh.
        const fresh = { ...data, version: 'changed' }
        const now = originalNow()
        Date.now = () => now
        upstream.set(`${path}?t=${now}`, { status: 200, data: fresh })
        await provided[refresh]()
        assert.deepEqual(states.get(state).value, fresh)
        assert.equal(calls.length, 2)
        assert.ok(calls[1].path.startsWith(`${path}?t=`))
        assert.deepEqual(await read(name), data, 'manual refresh must not contaminate the shared SSR cache')
      })

      await t.test(`${name}: failed expired refresh keeps good data, then a recovery updates it`, async () => {
        assert.deepEqual(await read(name), data)
        Date.now = () => originalNow() + 601000
        upstream.set(path, { status: 503, data: {} })
        assert.deepEqual(await read(name), data)
        await eventually(() => calls.length === 2)
        const fresh = { ...data, version: 'recovered' }
        upstream.set(path, { status: 200, data: fresh })
        await eventually(async () => (await read(name)).version === 'recovered')
        assert.equal(calls.length, 3)
        for (let i = 0; i < 5; i++) assert.deepEqual(await read(name), fresh)
        assert.equal(calls.length, 3)
      })
    }
  } finally {
    Date.now = originalNow
    for (const key of globalKeys) {
      const descriptor = originalGlobals.get(key)
      if (descriptor) Object.defineProperty(globalThis, key, descriptor)
      else delete globalThis[key]
    }
    if (server) await close(server)
    await close(backend)
    await nitro?.close()
    await rm(directory, { recursive: true, force: true })
  }
})
