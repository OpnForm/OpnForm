import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { spawn } from 'node:child_process'
import { access } from 'node:fs/promises'

const client = new URL('../', import.meta.url)
const listen = (server) => new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server.address().port)))
const close = (server) => new Promise((resolve) => server.close(resolve))

test('built Nuxt pages and 404s reuse configuration across real SSR requests', { timeout: 30000 }, async () => {
  await access(new URL('.output/server/index.mjs', client))
  const calls = new Map()
  const backend = createServer((req, res) => {
    calls.set(req.url, (calls.get(req.url) || 0) + 1)
    res.setHeader('content-type', 'application/json')
    res.end(JSON.stringify(req.url === '/content/feature-flags'
      ? { self_hosted: false, setup_required: false, services: {}, billing: { enabled: false }, license: null }
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
    for (const path of ['/', '/', '/cache-test-missing-page', '/cache-test-missing-page', '/']) {
      const response = await fetch(`http://127.0.0.1:${port}${path}`, { signal: AbortSignal.timeout(5000) })
      const html = await response.text()
      assert.equal(response.status, path === '/' ? 200 : 404)
      assert.ok(html.includes('featureFlags') && html.includes('planCatalog'), 'SSR includes hydrated configuration')
    }
    assert.equal(calls.get('/content/feature-flags'), 1)
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
