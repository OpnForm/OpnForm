import { readFileSync, existsSync } from 'node:fs'
import { gzipSync } from 'node:zlib'
import { resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

// Nuxt 4 emits an ES module manifest; older builds used JSON.
const buildDir = resolve(fileURLToPath(new URL('../', import.meta.url)), process.env.NUXT_BUILD_DIR || '.nuxt')
const jsonManifestPath = pathToFileURL(resolve(buildDir, 'dist/server/client.manifest.json'))
const moduleManifestPath = pathToFileURL(resolve(buildDir, 'dist/server/client.manifest.mjs'))
const manifestPath = existsSync(moduleManifestPath) ? moduleManifestPath : jsonManifestPath
// Inspect Vite's client output, shared by all Nitro deployment presets.
// Amplify publishes to .amplify-hosting instead of .output/public.
const buildAssetsUrl = pathToFileURL(`${resolve(buildDir, 'dist/client/_nuxt')}/`)
const maximumGzipBytes = 420 * 1024
const routeEntry = 'pages/forms/[slug]/index.vue'
const scenarios = {
  classic: ['OpenForm', 'TextInput', 'SelectInput'],
  focused: ['OpenFormFocused', 'FocusedSelectorInput', 'FocusedToggleInput'],
}
const forbiddenSources = [
  '/@codemirror/',
  '/codemirror/',
  '/crisp-sdk-web/',
  '/amplitude-js/',
  '/@amplitude/analytics-browser/',
  '/vue-draggable-plus/',
  '/overlayscrollbars/',
  '/overlayscrollbars-vue/',
  '/composables/useAuthFlow.js',
  '/components/workspaces/settings/',
  '/components/users/settings/',
]

if (!existsSync(manifestPath)) {
  throw new Error('Nuxt client manifest not found. Run this check after `nuxt build`.')
}

const manifest = manifestPath === moduleManifestPath
  ? (await import(moduleManifestPath.href)).default
  : JSON.parse(readFileSync(manifestPath, 'utf8'))

if (!manifest[routeEntry]) {
  throw new Error(`Missing ${routeEntry} from the Nuxt client manifest.`)
}

function findComponentEntry(componentName) {
  return Object.entries(manifest).find(([, entry]) => entry.name === componentName)?.[0]
}

function inspectScenario(scenarioName, componentNames) {
  const roots = [routeEntry, ...componentNames.map((componentName) => {
    const entryKey = findComponentEntry(componentName)
    if (!entryKey) throw new Error(`Missing ${componentName} from the Nuxt client manifest.`)
    return entryKey
  })]
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

    const assetUrl = new URL(entry.file, buildAssetsUrl)
    if (!existsSync(assetUrl)) {
      throw new Error(`Missing built asset for ${key}: ${entry.file}`)
    }
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
  console.log(`Public ${scenarioName} form bundle: ${gzipKilobytes} KiB gzip across ${entries.size} manifest entries.`)

  if (forbiddenHits.size) {
    throw new Error(`Public ${scenarioName} form bundle contains forbidden eager sources: ${[...forbiddenHits].join(', ')}`)
  }

  if (gzipBytes > maximumGzipBytes) {
    throw new Error(`Public ${scenarioName} form bundle exceeds the ${maximumGzipBytes / 1024} KiB gzip budget.`)
  }
}

Object.entries(scenarios).forEach(([scenarioName, componentNames]) => {
  inspectScenario(scenarioName, componentNames)
})
