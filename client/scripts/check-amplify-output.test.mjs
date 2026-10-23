import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, writeFileSync, truncateSync, rmSync, symlinkSync, unlinkSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'
test('rejects old Amplify runtimes, missing entrypoints and oversized compute artifacts', t => {
  const root = mkdtempSync(join(tmpdir(), 'opnform-amplify-'))
  t.after(() => rmSync(root, { recursive: true, force: true }))
  const folder = join(root, 'compute/default')
  mkdirSync(folder, { recursive: true })
  const manifest = runtime => writeFileSync(join(root, 'deploy-manifest.json'), JSON.stringify({ computeResources: [{ name: 'default', runtime, entrypoint: 'server.js' }] }))
  const run = () => spawnSync(process.execPath, [new URL('./check-amplify-output.mjs', import.meta.url).pathname, root], { encoding: 'utf8' })
  manifest('nodejs24.x')
  assert.notEqual(run().status, 0)
  writeFileSync(join(folder, 'server.js'), 'console.log("ready")')
  assert.equal(run().status, 0)
  symlinkSync(join(root, 'deploy-manifest.json'), join(folder, 'outside-compute.json'))
  assert.notEqual(run().status, 0)
  unlinkSync(join(folder, 'outside-compute.json'))
  manifest('nodejs20.x')
  assert.notEqual(run().status, 0)
  manifest('nodejs24.x')
  truncateSync(join(folder, 'server.js'), 220_000_001)
  assert.notEqual(run().status, 0)
})
