import { describe, expect, it } from 'vitest'
import { conditionsMet } from '../../lib/forms/FormLogicConditionChecker'
import { parentSubmissionPayload } from '../../lib/forms/embedSubmissionEvent'
import { evaluate } from '../../lib/formulas/evaluator'
import { Validator } from '../../lib/formulas/validator'
import { embeddingAncestors, mergeFramePolicy } from '../../lib/framePolicy'
import DOMPurify from 'dompurify'
import { JSDOM } from 'jsdom'

const condition = (type, operator, value) => ({ value: { property_meta: { id: 'field', type }, operator, value } })

describe('PSEC functional regressions', () => {
  it('does not treat empty groups, zero or false as missing answers', () => {
    expect(conditionsMet({ operatorIdentifier: 'and', children: [] }, {})).toBe(false)
    expect(conditionsMet(condition('number', 'is_empty'), { field: 0 })).toBe(false)
    expect(conditionsMet(condition('checkbox', 'is_empty'), { field: false })).toBe(false)
    expect(conditionsMet(condition('number', 'equals', '0'), { field: 0 })).toBe(true)
  })
  it('rejects malformed regular expressions for both comparison directions', () => {
    for (const operator of ['matches_regex', 'does_not_match_regex']) {
      expect(conditionsMet(condition('rich_text', operator, '['), { field: 'answer' })).toBe(false)
    }
  })
  it('accepts any matching matrix entry and tolerates an absent matrix', () => {
    const rule = condition('matrix', 'contains', { a: 'yes', b: 'no' })
    expect(conditionsMet(rule, { field: null })).toBe(false)
    expect(conditionsMet(rule, { field: { a: 'yes', b: 'yes' } })).toBe(true)
    expect(conditionsMet(rule, { field: { a: 'yes', b: 'no' } })).toBe(true)
  })
  it('shares answers only with explicit creator permission', () => {
    const payload = { type: 'form-submitted', form: { id: 1 }, submission_data: { secret: 'answer' }, meta: { attribution: {} }, completion_time: 3 }
    for (const flag of [undefined, false, 'true']) {
      expect(parentSubmissionPayload(payload, flag)).toEqual({ type: 'form-submitted', form: { id: 1 }, completion_time: 3 })
    }
    expect(parentSubmissionPayload(payload, true)).toEqual(payload)
  })
  it('keeps other CSP protections and production origins exact', () => {
    expect(mergeFramePolicy("script-src 'self'; object-src 'none'; frame-ancestors *;", ["'self'"])).toBe("script-src 'self'; object-src 'none'; frame-ancestors 'self';")
    expect(embeddingAncestors('https://app.example.test:444,http://preview.example.test')).toEqual(["'self'", 'https://app.example.test:444', 'http://preview.example.test'])
    expect(embeddingAncestors('')).not.toContain('http://localhost:3000')
  })
  it('does not execute an unused formula branch or read quoted field references', () => {
    expect(evaluate('IF(TRUE, 7, UNKNOWN())')).toBe(7)
    expect(evaluate('IF(FALSE, UNKNOWN(), 9)')).toBe(9)
    expect(evaluate('ISBLANK({value})', { value: [] })).toBe(true)
    expect(evaluate('IF({value}, 1, 0)', { value: [] })).toBe(0)
    expect(Validator.extractFieldReferences('CONCAT("{literal}", {actual})')).toEqual(['actual'])
  })
  it('sanitizes persisted rich text while retaining safe mentions', () => {
    const purify = DOMPurify(new JSDOM('').window)
    expect(purify.sanitize('<span mention-field-id="field" onclick="alert(1)">value</span><img src=x onerror="alert(1)"><script>alert(1)</script>', { ADD_ATTR: ['mention-field-id'] })).toBe('<span mention-field-id="field">value</span><img src="x">')
  })
})
