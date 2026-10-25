import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { readFileSync, realpathSync, readdirSync, writeFileSync } from 'node:fs'
import { resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
assert.equal(process.version, 'v24.22.1')
const root = realpathSync(process.argv[2])
const compute = resolve(root, 'compute/default')
const checkout = fileURLToPath(new URL('../', import.meta.url))
assert.ok(!root.startsWith(checkout), 'Runtime artifact must be copied outside the checkout')
const manifest = JSON.parse(readFileSync(resolve(root, 'deploy-manifest.json')))
assert.equal(manifest.computeResources[0].runtime, 'nodejs24.x')
for (const entry of readdirSync(compute, { recursive: true })) {
  assert.ok(realpathSync(resolve(compute, entry)).startsWith(realpathSync(compute) + sep), `External compute dependency: ${entry}`)
}
const env = { ...process.env, NUXT_PUBLIC_API_BASE: process.env.IMAGE_QA_API || 'http://127.0.0.1:8089', NUXT_PRIVATE_API_BASE: process.env.IMAGE_QA_API || 'http://127.0.0.1:8089', NUXT_PUBLIC_APP_URL: 'http://127.0.0.1:3000' }
delete env.NODE_PATH
delete env.NODE_OPTIONS
const runtime = spawn(process.execPath, ['server.js'], { cwd: compute, env, stdio: ['ignore', 'pipe', 'pipe'] })
let output = ''
runtime.stdout.on('data', data => { output += data })
runtime.stderr.on('data', data => { output += data })
const launched = new Promise((resolve, reject) => { runtime.once('spawn', resolve); runtime.once('error', reject) })
try {
  await launched
  const health = spawn(process.execPath, [fileURLToPath(new URL('./check-linux-client-health.mjs', import.meta.url))], {
    env: { ...env, IMAGE_QA_UI: 'http://127.0.0.1:3000', IMAGE_QA_OUTPUT: 'amplify-runtime-health.json' }, stdio: 'inherit',
  })
  const status = await new Promise((resolve, reject) => { health.once('error', reject); health.once('exit', resolve) })
  assert.equal(status, 0, 'Amplify compute SSR health must pass')
  assert.equal(runtime.exitCode, null, 'Amplify compute remains alive')
  writeFileSync('amplify-runtime.json', JSON.stringify({ node: process.version, platform: process.platform, root, entrypoint: 'server.js', standalone: true, status: 'passed', testedSha: process.env.GITHUB_SHA || null, headSha: process.env.SOURCE_HEAD_SHA || null }, null, 2)+'\n')
} finally {
  runtime.kill('SIGTERM')
  writeFileSync('amplify-runtime.log', output)
}
