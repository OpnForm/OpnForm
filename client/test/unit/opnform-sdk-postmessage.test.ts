import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { JSDOM } from 'jsdom'

const sdkSource = readFileSync(resolve(__dirname, '../../public/widgets/opnform-sdk.js'), 'utf8')

function getIframeOrigin(window: Window, iframe: HTMLIFrameElement) {
  return new URL(iframe.src, window.location.href).origin
}

function mockIframePostMessage(window: Window, iframe: HTMLIFrameElement) {
  const iframeOrigin = getIframeOrigin(window, iframe)

  vi.spyOn(iframe.contentWindow!, 'postMessage').mockImplementation((message: {
    type?: string
    formSlug?: string
    requestId?: number
  }) => {
    if (message?.type === 'opnform:command') {
      window.dispatchEvent(new window.MessageEvent('message', {
        data: {
          type: 'opnform:response',
          formSlug: message.formSlug,
          requestId: message.requestId,
          success: true,
          data: { success: true },
        },
        origin: iframeOrigin,
        source: iframe.contentWindow,
      }))
    }
  })
}

function simulateHandshakeAck(window: Window, iframe: HTMLIFrameElement, formSlug = 'demo') {
  window.dispatchEvent(new window.MessageEvent('message', {
    data: {
      type: 'opnform:handshake-ack',
      formSlug,
      success: true,
    },
    origin: getIframeOrigin(window, iframe),
    source: iframe.contentWindow,
  }))
}

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

  mockIframePostMessage(dom.window, iframe)
  dom.window.opnform._forms = {}
  dom.window.opnform.init({ autoResize: false, preventRedirect: true })
  simulateHandshakeAck(dom.window, iframe)

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

  it('sends commands to the iframe origin instead of a wildcard target', async () => {
    const { window, iframe } = createSdkWindow()
    const form = window.opnform.get('demo')

    await form.setField('email', 'user@example.test')

    expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'opnform:command',
        command: 'setField',
        formSlug: 'demo',
        _sdkToken: expect.any(String),
      }),
      'https://forms.example.test',
    )
  })

  it('sends a handshake when discovering an existing iframe', () => {
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

    expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'opnform:handshake',
        formSlug: 'demo',
        _sdkToken: expect.any(String),
        parentOrigin: 'https://embedder.example.test',
      }),
      'https://forms.example.test',
    )
  })

  it('includes the SDK token when creating a form iframe', async () => {
    const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>', {
      url: 'https://embedder.example.test/',
      runScripts: 'outside-only',
    })

    dom.window.eval(sdkSource)
    dom.window.opnform.init({ autoResize: false })

    const form = dom.window.opnform.create('demo', { container: '#container' })
    const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement

    expect(iframe.src).toContain('_sdkToken=')
    expect(iframe.src).toContain('_sdkParentOrigin=')

    mockIframePostMessage(dom.window, iframe)
    simulateHandshakeAck(dom.window, iframe)
    await form.setField('email', 'user@example.test')

    expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'opnform:command',
        _sdkToken: expect.any(String),
      }),
      'https://embedder.example.test',
    )
  })

  it('rejects commands when handshake times out without ack', async () => {
    vi.useFakeTimers()

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

    const form = dom.window.opnform.get('demo')
    const commandPromise = form.setField('email', 'user@example.test')
    const assertion = expect(commandPromise).rejects.toThrow('SDK handshake timeout')

    await vi.advanceTimersByTimeAsync(3000)
    await assertion

    vi.useRealTimers()
  })
})
