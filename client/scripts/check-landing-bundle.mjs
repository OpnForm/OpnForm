import { readFileSync, existsSync } from 'node:fs'
import { gzipSync } from 'node:zlib'
import { resolve } from 'node:path'
import { pathToFileURL } from 'node:url'

const root = resolve(import.meta.dirname, '..')
const build = resolve(root, process.env.NUXT_BUILD_DIR || '.nuxt')
const manifest = (await import(pathToFileURL(resolve(build, 'dist/server/client.manifest.mjs')))).default
const roots = ['node_modules/nuxt/dist/app/entry.js', 'pages/index.vue']
const entries = new Set()
function collect(key) {
  if (entries.has(key) || !manifest[key]) return
  entries.add(key)
  for (const child of manifest[key].imports || []) collect(child)
}
for (const entry of roots) {
  if (!manifest[entry]) throw new Error(`Missing landing entry: ${entry}`)
  collect(entry)
}
let bytes = 0
let scripts = 0
const forbidden = ['/codemirror/', '/@codemirror/', '/quill/', '/vue-country-flag-next/', '/crisp-sdk-web/', '/@microsoft/clarity/']
for (const key of entries) {
  const entry = manifest[key]
  if (entry.resourceType !== 'script') continue
  scripts++
  const file = resolve(build, 'dist/client/_nuxt', entry.file)
  bytes += gzipSync(readFileSync(file)).length
  if (!existsSync(`${file}.map`)) continue
  const map = JSON.parse(readFileSync(`${file}.map`, 'utf8'))
  for (const source of map.sources || []) {
    if (forbidden.some(fragment => source.replaceAll('\\', '/').includes(fragment))) {
      throw new Error(`Optional dependency in the landing's static graph: ${source}`)
    }
  }
}
console.log(`Landing static JavaScript: ${(bytes / 1024).toFixed(1)} KiB gzip across ${scripts} files.`)
if (bytes > 375 * 1024 || scripts > 50) throw new Error('Landing exceeds its 375 KiB gzip / 50 static script budget.')
