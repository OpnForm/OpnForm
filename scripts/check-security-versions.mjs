import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (path) => JSON.parse(readFileSync(new URL(path, import.meta.url), 'utf8'))
const php = read('../api/composer.lock')
const npm = read('../client/package-lock.json')
const atLeast = (actual, minimum) => {
  assert.match(actual, /^v?\d+\.\d+\.\d+$/, `Expected a stable version, received ${actual}`)
  const version = actual.replace(/^v/, '').split('.').map(Number)
  const floor = minimum.split('.').map(Number)
  return version.some((part, index) => part > floor[index] && version.slice(0, index).every((value, i) => value === floor[i]))
    || version.every((part, index) => part === floor[index])
}

// Supplement registry scanners: the MCP OAuth advisories were absent from
// Composer audit when this migration was prepared. Keep these floors explicit.
for (const [name, minimum] of Object.entries({
  'laravel/framework': '12.70.1',
  'laravel/mcp': '1.1.1',
  'symfony/http-foundation': '7.4.21',
  'phpoffice/phpspreadsheet': '1.30.7'
})) {
  const version = php.packages.find((pkg) => pkg.name === name)?.version
  assert.ok(version && atLeast(version, minimum), `${name} must be >= ${minimum}, received ${version}`)
}
for (const [name, minimum] of Object.entries({ nuxt: '4.6.2', '@nuxt/devtools': '3.4.4', vitest: '5.0.5', 'happy-dom': '21.0.4', katex: '0.18.2' })) {
  const entries = Object.entries(npm.packages).filter(([path]) => path.endsWith(`node_modules/${name}`))
  assert.ok(entries.length, `Missing ${name}`)
  for (const [path, pkg] of entries) {
    assert.ok(atLeast(pkg.version, minimum), `${path} must be >= ${minimum}, received ${pkg.version}`)
  }
}

// GHSA-g7r4-m6w7-qqqr was introduced in 0.27.3. The i18n compiler's
// 0.25.x branch is unaffected; do not force an unrelated API migration.
for (const [path, pkg] of Object.entries(npm.packages).filter(([path]) => path.endsWith('node_modules/esbuild'))) {
  assert.ok(atLeast(pkg.version, '0.25.0') && !(atLeast(pkg.version, '0.27.3') && !atLeast(pkg.version, '0.28.1')), `Affected esbuild version at ${path}: ${pkg.version}`)
}
console.log('Verified security version floors in Composer and npm locks.')
