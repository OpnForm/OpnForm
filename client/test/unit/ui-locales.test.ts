import { describe, expect, it } from 'vitest'
import { resolveUiLocaleKey } from '../../lib/ui-locales.js'

describe('Nuxt UI locale chunks', () => {
  const available = ['en', 'fr', 'ar', 'pt_br', 'zh_cn', 'zh_tw']
  it.each([
    ['fr', 'fr'], ['fr-FR', 'fr'], ['ar', 'ar'], ['pt', 'pt_br'],
    ['pt-BR', 'pt_br'], ['zh-CN', 'zh_cn'], ['zh', 'en'], ['unknown', 'en'],
  ])('keeps the locale fallback for %s', (locale, expected) => {
    expect(resolveUiLocaleKey(locale, available)).toBe(expected)
  })
})
