import { describe, expect, it } from 'vitest'
import {
  getPublicFormResponseStatus,
  isPublicFormNotFoundError,
  publicFormRetryDelay,
  shouldRetryPublicFormRequest,
} from '../../lib/forms/public-form-loading.js'

describe('public form loading policy', () => {
  it.each([
    { statusCode: 404 },
    { status: 404 },
    { response: { status: 404 } },
  ])('recognizes a 404 across supported fetch error shapes', (error) => {
    expect(isPublicFormNotFoundError(error)).toBe(true)
    expect(getPublicFormResponseStatus(error)).toBe(404)
    expect(shouldRetryPublicFormRequest(0, error)).toBe(false)
  })

  it.each([
    [{ statusCode: 408 }, true],
    [{ statusCode: 425 }, true],
    [{ statusCode: 429 }, true],
    [{ statusCode: 500 }, true],
    [{ statusCode: 502 }, true],
    [{ statusCode: 503 }, true],
    [{ statusCode: 504 }, true],
    [new TypeError('fetch failed'), true],
    [{ statusCode: 400 }, false],
    [{ statusCode: 401 }, false],
    [{ statusCode: 403 }, false],
    [{ statusCode: 422 }, false],
  ])('retries only transient failures', (error, expected) => {
    expect(shouldRetryPublicFormRequest(0, error)).toBe(expected)
  })

  it('stops after two retries', () => {
    const error = { statusCode: 503 }

    expect(shouldRetryPublicFormRequest(0, error)).toBe(true)
    expect(shouldRetryPublicFormRequest(1, error)).toBe(true)
    expect(shouldRetryPublicFormRequest(2, error)).toBe(false)
  })

  it('maps every non-404 failure to service unavailable', () => {
    expect(getPublicFormResponseStatus({ statusCode: 500 })).toBe(503)
    expect(getPublicFormResponseStatus(new TypeError('fetch failed'))).toBe(503)
  })

  it('uses short bounded exponential delays', () => {
    expect(publicFormRetryDelay(0)).toBe(250)
    expect(publicFormRetryDelay(1)).toBe(500)
    expect(publicFormRetryDelay(10)).toBe(1_000)
  })
})
