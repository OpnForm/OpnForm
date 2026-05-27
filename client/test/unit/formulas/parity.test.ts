/**
 * Formula Engine Parity Tests
 *
 * These tests ensure that the JavaScript formula engine produces identical results
 * to the PHP implementation. Test cases are loaded from a shared JSON fixture file
 * that both PHP and JS tests read.
 */

import { describe, it, expect } from 'vitest'
import { evaluate } from '../../../lib/formulas/evaluator.js'
import * as fs from 'fs'
import * as path from 'path'

// Load the parity test cases from fixtures
const parityTestsPath = path.resolve(__dirname, '../../fixtures/formula-parity-tests.json')
let parityTests: Array<{
  name: string
  formula: string
  context: Record<string, unknown>
  expected: unknown
}> = []

try {
  const json = JSON.parse(fs.readFileSync(parityTestsPath, 'utf-8'))
  parityTests = json.tests ?? []
} catch (e) {
  console.warn('Could not load parity tests:', e)
}

describe('Formula Engine Parity Tests', () => {
  if (parityTests.length === 0) {
    it.skip('has parity test cases loaded', () => {
      // Skip if parity tests not found
    })
    return
  }

  parityTests.forEach((testCase) => {
    const { name, formula, context, expected } = testCase

    it(`parity: ${name}`, () => {
      const result = evaluate(formula, context)

      // Handle floating point comparison
      if (typeof expected === 'number' && typeof result === 'number') {
        expect(result).toBeCloseTo(expected, 10)
      } else {
        expect(result).toEqual(expected)
      }
    })
  })
})
