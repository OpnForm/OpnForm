import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'
test('checks nested emitted CSS and fails on uncompiled directives or absent assets', t => {
  const dir = mkdtempSync(join(tmpdir(), 'opnform-css-'))
  t.after(() => rmSync(dir, { recursive: true, force: true }))
  mkdirSync(join(dir, '_nuxt'))
  const run = () => spawnSync(process.execPath, [new URL('./check-built-css.mjs', import.meta.url).pathname, dir], { encoding: 'utf8' })
  assert.notEqual(run().status, 0)
  const file = join(dir, '_nuxt/component.css')
  writeFileSync(file, '.mention{display:inline-flex;color:blue}')
  assert.equal(run().status, 0)
  for (const directive of ['apply text-blue-500', 'screen md', 'reference "app.css"']) {
    writeFileSync(file, `.mention{ @${directive}; }`)
    assert.notEqual(run().status, 0)
  }
})
