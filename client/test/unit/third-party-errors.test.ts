import { describe, expect, it } from 'vitest'
import { shouldDropThirdPartyError } from '../../lib/telemetry/third-party-errors.js'

const applicationUrls = [
  'https://customer.example',
  'https://opnform.com',
  'https://assets.opnform.example/production',
]
const externalScript = 'https://analytics.example/tracker.js'

function taggedError (filenames: string[]) {
  return {
    tags: { third_party_code: true },
    exception: {
      values: [{ stacktrace: { frames: filenames.map(filename => ({ filename })) } }],
    },
  }
}

describe('shouldDropThirdPartyError', () => {
  it('drops exclusively third-party errors with an identifiable external script', () => {
    expect(shouldDropThirdPartyError(taggedError([externalScript, '[native code]']), applicationUrls))
      .toBe(true)
  })

  it.each([
    'https://customer.example/custom-root/client.js',
    'https://opnform.com/public-sdk.js',
    'https://assets.opnform.example/production/client.js',
    'https://other-origin.example/base/_nuxt/client.js',
    'https://other-origin.example/widgets/opnform-sdk.js',
    '<anonymous>',
    'blob:https://customer.example/sample',
    '',
  ])('keeps mixed or ambiguous errors containing %s even when tagged', (filename) => {
    expect(shouldDropThirdPartyError(taggedError([externalScript, filename]), applicationUrls))
      .toBe(false)
  })

  it('keeps a linked application error', () => {
    const event = taggedError([externalScript])
    event.exception.values.push({ stacktrace: { frames: [{ filename: 'https://opnform.com/app.js' }] } })

    expect(shouldDropThirdPartyError(event, applicationUrls)).toBe(false)
  })

  it('keeps untagged events and events with no identifiable script', () => {
    expect(shouldDropThirdPartyError({ ...taggedError([externalScript]), tags: {} }, applicationUrls))
      .toBe(false)
    expect(shouldDropThirdPartyError(taggedError(['[native code]']), applicationUrls)).toBe(false)
    expect(shouldDropThirdPartyError(taggedError([]), applicationUrls)).toBe(false)
  })
})
