import { readFileSync, existsSync } from 'node:fs'
import { gzipSync } from 'node:zlib'

const manifestPath = new URL('../.nuxt/dist/server/client.manifest.json', import.meta.url)
const publicAssetsUrl = new URL('../.output/public/_nuxt/', import.meta.url)
const buildAssetsUrl = new URL('../.nuxt/dist/client/_nuxt/', import.meta.url)
const maximumGzipBytes = 420 * 1024
const representativeComponents = new Set(['OpenForm', 'TextInput', 'SelectInput'])
const forbiddenSources = [
  '/@codemirror/',
  '/codemirror/',
  '/crisp-sdk-web/',
  '/amplitude-js/',
  '/vue-draggable-plus/',
  '/overlayscrollbars/',
  '/overlayscrollbars-vue/',
  '/components/workspaces/settings/',
  '/components/users/settings/',
]

if (!existsSync(manifestPath)) {
  throw new Error('Nuxt client manifest not found. Run this check after `nuxt build`.')
}

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
const roots = ['pages/forms/[slug]/index.vue']

for (const [key, entry] of Object.entries(manifest)) {
  if (representativeComponents.has(entry.name)) roots.push(key)
}

const entries = new Set()
function collectImports(key) {
  if (entries.has(key) || !manifest[key]) return
  entries.add(key)
  for (const importedKey of manifest[key].imports || []) collectImports(importedKey)
}
roots.forEach(collectImports)

let gzipBytes = 0
const forbiddenHits = new Set()

for (const key of entries) {
  const entry = manifest[key]
  if (entry.resourceType !== 'script' || !entry.file) continue

  const assetUrl = new URL(entry.file, publicAssetsUrl)
  if (!existsSync(assetUrl)) continue
  gzipBytes += gzipSync(readFileSync(assetUrl)).length

  const sourceMapUrl = new URL(`${entry.file}.map`, buildAssetsUrl)
  if (!existsSync(sourceMapUrl)) continue
  const sourceMap = JSON.parse(readFileSync(sourceMapUrl, 'utf8'))
  for (const source of sourceMap.sources || []) {
    const normalizedSource = source.replaceAll('\\', '/')
    for (const forbiddenSource of forbiddenSources) {
      if (normalizedSource.includes(forbiddenSource)) forbiddenHits.add(forbiddenSource)
    }
  }
}

const gzipKilobytes = (gzipBytes / 1024).toFixed(1)
console.log(`Public form representative bundle: ${gzipKilobytes} KiB gzip across ${entries.size} manifest entries.`)

if (forbiddenHits.size) {
  throw new Error(`Public form bundle contains forbidden eager sources: ${[...forbiddenHits].join(', ')}`)
}

if (gzipBytes > maximumGzipBytes) {
  throw new Error(`Public form bundle exceeds the ${maximumGzipBytes / 1024} KiB gzip budget.`)
}
