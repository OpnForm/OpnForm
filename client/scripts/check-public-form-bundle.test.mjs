import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, copyFileSync, writeFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'
import { randomBytes } from 'node:crypto'

function runCheck(t, { preset = 'amplify', missingAsset = false, forbidden = false, oversized = false, moduleManifest = false, buildDir = '.nuxt' } = {}) {
  const root = mkdtempSync(join(tmpdir(), 'opnform-bundle-'))
  t.after(() => rmSync(root, { recursive: true, force: true }))
  const assets = join(root, buildDir, 'dist/client/_nuxt')
  const output = join(root, preset === 'amplify' ? '.amplify-hosting/static/_nuxt' : '.output/public/_nuxt')
  for (const directory of ['scripts', `${buildDir}/dist/server`, assets, output]) {
    mkdirSync(directory.startsWith(root) ? directory : join(root, directory), { recursive: true })
  }
  copyFileSync(new URL('./check-public-form-bundle.mjs', import.meta.url), join(root, 'scripts/check.mjs'))
  const names = ['OpenForm', 'TextInput', 'SelectInput', 'OpenFormFocused', 'FocusedSelectorInput', 'FocusedToggleInput']
  const manifest = Object.fromEntries(['pages/forms/[slug]/index.vue', ...names].map((name) => [name, {
    name, resourceType: 'script', file: `${names.includes(name) ? name : 'route'}.js`,
  }]))
  writeFileSync(
    join(root, `${buildDir}/dist/server/client.manifest.${moduleManifest ? 'mjs' : 'json'}`),
    `${moduleManifest ? 'export default ' : ''}${JSON.stringify(manifest)}`,
  )
  for (const entry of Object.values(manifest)) {
    if (missingAsset && entry.file === 'route.js') continue
    const contents = oversized ? randomBytes(450 * 1024) : 'export default {}'
    writeFileSync(join(assets, entry.file), contents)
    writeFileSync(join(output, entry.file), contents)
    writeFileSync(join(assets, `${entry.file}.map`), JSON.stringify({
      sources: forbidden ? ['../../node_modules/crisp-sdk-web/index.js'] : ['../../components/forms/TextInput.vue'],
    }))
  }
  return spawnSync(process.execPath, [join(root, 'scripts/check.mjs')], { encoding: 'utf8', env: { ...process.env, NUXT_BUILD_DIR: buildDir } })
}

for (const preset of ['amplify', 'node']) {
  test(`checks both public form bundles with the ${preset} output layout`, (t) => {
    const result = runCheck(t, { preset })
    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stdout, /Public classic form bundle:/)
    assert.match(result.stdout, /Public focused form bundle:/)
  })
}

test('fails when a referenced asset is missing', (t) => {
  const result = runCheck(t, { missingAsset: true })
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /Missing built asset/)
})

test('still rejects forbidden eager dependencies', (t) => {
  const result = runCheck(t, { forbidden: true })
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /forbidden eager sources: \/crisp-sdk-web\//)
})

test('still enforces the gzip budget', (t) => {
  const result = runCheck(t, { oversized: true })
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /exceeds the 420 KiB gzip budget/)
})

test('reads the Nuxt 4 ES module manifest and still checks eager dependencies', (t) => {
  const result = runCheck(t, { moduleManifest: true })
  assert.equal(result.status, 0, result.stderr)
  const invalid = runCheck(t, { moduleManifest: true, forbidden: true })
  assert.notEqual(invalid.status, 0)
  assert.match(invalid.stderr, /forbidden eager sources/)
})

test('measures the configured worktree build directory instead of stale default artifacts', (t) => {
  const result = runCheck(t, { buildDir: 'isolated/nuxt', moduleManifest: true })
  assert.equal(result.status, 0, result.stderr)
  const invalid = runCheck(t, { buildDir: 'isolated/nuxt', moduleManifest: true, oversized: true })
  assert.notEqual(invalid.status, 0)
  assert.match(invalid.stderr, /exceeds the 420 KiB gzip budget/)
})
