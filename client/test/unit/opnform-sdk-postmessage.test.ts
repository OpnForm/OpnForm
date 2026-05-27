import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { JSDOM } from 'jsdom'

const sdkSource = readFileSync(resolve(__dirname, '../../public/widgets/opnform-sdk.js'), 'utf8')

function createSdkWindow() {
  const dom = new JSDOM(
    '<!doctype html><html><body><iframe id="demo" src="https://forms.example.test/forms/demo"></iframe></body></html>',
    {
      url: 'https://embedder.example.test/',
      runScripts: 'outside-only',
    },
  )

  dom.window.eval(sdkSource)
  const iframe = dom.window.document.getElementById('demo') as HTMLIFrameElement

  vi.spyOn(iframe.contentWindow!, 'postMessage').mockImplementation(() => {})
  dom.window.opnform._forms = {}
  dom.window.opnform.init({ autoResize: false, preventRedirect: true })

  return { window: dom.window, iframe }
}

describe('OpnForm public SDK postMessage security', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('ignores SDK events that do not come from the registered iframe', () => {
    const { window } = createSdkWindow()
    const form = window.opnform.get('demo')

    window.dispatchEvent(new window.MessageEvent('message', {
      data: {
        type: 'opnform:event',
        event: 'ready',
        formSlug: 'demo',
        payload: { data: { forged: true } },
      },
      origin: 'https://forms.example.test',
      source: window,
    }))

    expect(form.isReady()).toBe(false)
    expect(form.getData()).toEqual({})
  })

  it('accepts SDK events from the registered iframe origin', () => {
    const { window, iframe } = createSdkWindow()
    const form = window.opnform.get('demo')

    window.dispatchEvent(new window.MessageEvent('message', {
      data: {
        type: 'opnform:event',
        event: 'ready',
        formSlug: 'demo',
        payload: { data: { trusted: true } },
      },
      origin: 'https://forms.example.test',
      source: iframe.contentWindow,
    }))

    expect(form.isReady()).toBe(true)
    expect(form.getData()).toEqual({ trusted: true })
  })

  it('sends commands to the iframe origin instead of a wildcard target', () => {
    const { window, iframe } = createSdkWindow()
    const form = window.opnform.get('demo')

    form.setField('email', 'user@example.test')

    expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'opnform:command',
        command: 'setField',
        formSlug: 'demo',
      }),
      'https://forms.example.test',
    )
  })
})
