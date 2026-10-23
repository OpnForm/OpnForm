import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
const root = resolve(process.argv[2] || (process.env.NITRO_PRESET === 'aws-amplify' ? '.amplify-hosting/static' : '.output/public'))
const files = readdirSync(root, { recursive: true, withFileTypes: true })
  .filter(entry => entry.isFile() && entry.name.endsWith('.css'))
  .map(entry => resolve(entry.parentPath, entry.name))
if (!files.length) throw new Error(`No built CSS found in ${root}`)
const failures = files.flatMap(path => [...readFileSync(path, 'utf8').matchAll(/@(apply|screen|reference)\b/g)].map(match => `${path}: uncompiled @${match[1]}`))
if (failures.length) throw new Error(failures.join('\n'))
console.log(`Checked ${files.length} CSS assets: zero uncompiled Tailwind directives.`)
