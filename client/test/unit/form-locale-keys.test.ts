import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'fs'
import { join, resolve } from 'path'

const clientRoot = resolve(__dirname, '../..')
const langDir = join(clientRoot, 'i18n/lang')

const flattenKeys = (obj: Record<string, unknown>, prefix = ''): string[] =>
  Object.entries(obj).flatMap(([key, value]) =>
    value && typeof value === 'object'
      ? flattenKeys(value as Record<string, unknown>, `${prefix}${key}.`)
      : [`${prefix}${key}`]
  )

const loadKeys = (file: string) =>
  new Set(flattenKeys(JSON.parse(readFileSync(join(langDir, file), 'utf-8'))))

const listSourceFiles = (dir: string): string[] =>
  readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry)
    if (statSync(path).isDirectory()) return listSourceFiles(path)
    return /\.(vue|js)$/.test(entry) ? [path] : []
  })

describe('form locale keys', () => {
  const enKeys = loadKeys('en.json')

  it('defines every forms.* key used by public form components in en.json', () => {
    const used = new Set<string>()
    for (const file of listSourceFiles(join(clientRoot, 'components/open/forms'))) {
      for (const match of readFileSync(file, 'utf-8').matchAll(/\bt\(\s*['"](forms\.[\w.]+)['"]/g)) {
        used.add(match[1])
      }
    }

    expect(used.size).toBeGreaterThan(0)
    expect([...used].filter((key) => !enKeys.has(key))).toEqual([])
  })

  it.each(readdirSync(langDir).filter((file) => file.endsWith('.json') && file !== 'en.json'))(
    '%s has the same keys as en.json',
    (file) => {
      const keys = loadKeys(file)
      expect([...enKeys].filter((key) => !keys.has(key))).toEqual([])
      expect([...keys].filter((key) => !enKeys.has(key))).toEqual([])
    }
  )
})
