import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { spawn } from 'node:child_process'
import { access } from 'node:fs/promises'

const client = new URL('../', import.meta.url)
const listen = (server) => new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server.address().port)))
const close = (server) => new Promise((resolve) => server.close(resolve))

test('built Nuxt exits setup immediately, then reuses configuration across SSR pages and 404s', { timeout: 30000 }, async () => {
  await access(new URL('.output/server/index.mjs', client))
  const calls = new Map()
  let setupRequired = true
  const backend = createServer((req, res) => {
    calls.set(req.url, (calls.get(req.url) || 0) + 1)
    res.setHeader('content-type', 'application/json')
    res.end(JSON.stringify(req.url === '/content/feature-flags'
      ? { self_hosted: true, setup_required: setupRequired, services: {}, billing: { enabled: false }, license: null }
      : req.url === '/content/plans' ? { tiers: { free: { name: 'Free' } } } : { data: [] }))
  })
  let child
  let output = ''
  try {
    const backendPort = await listen(backend)
    const reservation = createServer()
    const port = await listen(reservation)
    await close(reservation)
    child = spawn(process.execPath, ['.output/server/index.mjs'], {
      cwd: client,
      env: {
        PATH: process.env.PATH,
        NODE_ENV: 'production', NITRO_HOST: '127.0.0.1', NITRO_PORT: String(port),
        NUXT_PUBLIC_API_BASE: `http://127.0.0.1:${backendPort}`,
        NUXT_PRIVATE_API_BASE: `http://127.0.0.1:${backendPort}`,
        NUXT_PUBLIC_APP_URL: `http://127.0.0.1:${port}`,
        NUXT_PUBLIC_ENV: 'testing', NUXT_API_SECRET: 'local-test-only'
      }
    })
    child.stdout.on('data', (data) => { output += data })
    child.stderr.on('data', (data) => { output += data })
    let started = false
    for (let i = 0; i < 100; i++) {
      if (output.includes('Listening on')) { started = true; break }
      if (child.exitCode !== null) assert.fail(`Nuxt exited with ${child.exitCode}: ${output}`)
      await new Promise((resolve) => setTimeout(resolve, 100))
    }
    assert.ok(started, `Nuxt did not start: ${output}`)
    const origin = `http://127.0.0.1:${port}`
    const pending = await fetch(`${origin}/cache-test-missing-page`, { redirect: 'manual' })
    assert.equal(pending.status, 302)
    assert.equal(pending.headers.get('location'), '/setup')
    const setup = await fetch(`${origin}/setup`)
    assert.equal(setup.status, 200)
    await setup.text()
    assert.equal(calls.get('/content/feature-flags'), 2)

    // Model registration completing in Laravel while the same Nuxt process runs.
    setupRequired = false
    for (const path of ['/', '/', '/cache-test-missing-page', '/cache-test-missing-page', '/']) {
      const response = await fetch(`http://127.0.0.1:${port}${path}`, { signal: AbortSignal.timeout(5000) })
      const html = await response.text()
      assert.equal(response.status, path === '/' ? 200 : 404)
      assert.ok(html.includes('featureFlags') && html.includes('planCatalog'), 'SSR includes hydrated configuration')
    }
    assert.equal(calls.get('/content/feature-flags'), 3)
    assert.equal(calls.get('/content/plans'), 1)
  } finally {
    if (child && child.exitCode === null) {
      const exited = new Promise((resolve) => child.once('exit', resolve))
      child.kill('SIGTERM')
      await exited
    }
    await close(backend)
  }
})
