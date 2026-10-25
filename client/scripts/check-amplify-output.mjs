import assert from 'node:assert/strict'
import { readFileSync, readdirSync, statSync, realpathSync } from 'node:fs'
import { resolve, sep } from 'node:path'
const root = resolve(process.argv[2] || '.amplify-hosting')
const manifest = JSON.parse(readFileSync(resolve(root, 'deploy-manifest.json'), 'utf8'))
assert.equal(manifest.computeResources.length, 1)
const compute = manifest.computeResources[0]
assert.equal(compute.runtime, 'nodejs24.x', 'Amplify compute must run the patched Node 24 line')
const folder = resolve(root, 'compute', compute.name)
assert.ok(statSync(resolve(folder, compute.entrypoint)).isFile(), 'Compute entrypoint exists')
const bytes = readdirSync(folder, { recursive: true }).reduce((sum, entry) => {
  const path = resolve(folder, entry)
  assert.ok(realpathSync(path).startsWith(realpathSync(folder) + sep), `Compute dependency escapes artifact: ${entry}`)
  const stat = statSync(path)
  return sum + (stat.isFile() ? stat.size : 0)
}, 0)
// Amplify's compute bundle limit is 220 MB, measured before compression.
assert.ok(bytes <= 220_000_000, `Amplify compute bundle is too large: ${bytes} bytes`)
console.log(JSON.stringify({ runtime: compute.runtime, entrypoint: compute.entrypoint, computeBytes: bytes }))
