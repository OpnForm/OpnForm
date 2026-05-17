import { describe, it, expect } from 'vitest'
import { normalizeFormula } from '../../../lib/formulas/normalizeFormula.js'

describe('normalizeFormula', () => {
  it('trims and collapses whitespace outside strings', () => {
    const input = '  {a}   +   {b}  '
    expect(normalizeFormula(input)).toBe('{a} + {b}')
  })

  it('preserves whitespace inside double-quoted strings', () => {
    const input = 'CONCAT("Hello   World",   {name})'
    expect(normalizeFormula(input)).toBe('CONCAT("Hello   World", {name})')
  })

  it('preserves whitespace inside single-quoted strings', () => {
    const input = "CONCAT('Hi   there',   {name})"
    expect(normalizeFormula(input)).toBe("CONCAT('Hi   there', {name})")
  })
})
