import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { JSDOM } from 'jsdom'

const sdkSource = readFileSync(resolve(__dirname, '../../public/widgets/opnform-sdk.js'), 'utf8')
const sdkMinSource = readFileSync(resolve(__dirname, '../../public/widgets/opnform-sdk.min.js'), 'utf8')

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

  it.each([
    ['source', sdkSource],
    ['minified', sdkMinSource],
  ])('sends allowlisted parent attribution without leaking arbitrary query parameters (%s)', (_variant, source) => {
    const dom = new JSDOM(
      '<!doctype html><html><body><iframe id="demo" src="https://forms.example.test/forms/demo"></iframe></body></html>',
      {
        url: 'https://embedder.example.test/?utm_source=facebook&gclid=click-id&email=secret@example.test',
        runScripts: 'outside-only',
      },
    )

    dom.window.eval(source)
    const iframe = dom.window.document.getElementById('demo') as HTMLIFrameElement
    vi.spyOn(iframe.contentWindow!, 'postMessage').mockImplementation(() => {})
    dom.window.opnform._forms = {}
    dom.window.opnform.init({ autoResize: false })

    expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'opnform:handshake',
        trackingParameters: {
          utm_source: 'facebook',
          gclid: 'click-id',
        },
      }),
      'https://forms.example.test',
    )
  })

  it('does not create an unhandled rejection when passive handshake times out', async () => {
    vi.useFakeTimers()

    try {
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

      await vi.advanceTimersByTimeAsync(3000)

      expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'opnform:handshake',
          formSlug: 'demo',
        }),
        'https://forms.example.test',
      )
    } finally {
      vi.useRealTimers()
    }
  })

  it.each([
    ['source', sdkSource],
    ['minified', sdkMinSource],
  ])('creates form iframes on OpnForm by default with a working SDK handshake (%s)', async (_variant, source) => {
    const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>', {
      url: 'https://embedder.example.test/',
      runScripts: 'outside-only',
    })

    dom.window.eval(source)
    dom.window.opnform.init({ autoResize: false })

    const form = dom.window.opnform.create('demo', { container: '#container' })
    const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement

    const url = new URL(iframe.src)
    expect(url.origin).toBe('https://opnform.com')
    expect(url.pathname).toBe('/forms/demo')
    expect(url.searchParams.get('_sdkToken')).toBeTruthy()
    expect(url.searchParams.get('_sdkParentOrigin')).toBe('https://embedder.example.test')

    mockIframePostMessage(dom.window, iframe)
    simulateHandshakeAck(dom.window, iframe)
    await form.setField('email', 'user@example.test')

    expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'opnform:command',
        _sdkToken: expect.any(String),
      }),
      'https://opnform.com',
    )
    dom.window.close()
  })

  describe.each([
    ['source', sdkSource],
    ['minified', sdkMinSource],
  ])('programmatic form URLs (%s)', (_variant, source) => {
    it.each([
      'https://forms.example.test',
      'https://example.test/',
      'http://localhost:3212///',
    ])('loads the form from %s and sends commands to that origin', async (baseUrl) => {
      const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>', {
        url: 'https://embedder.example.test/',
        runScripts: 'outside-only',
      })

      dom.window.eval(source)
      dom.window.opnform.init({ autoResize: false })
      const form = dom.window.opnform.create('demo', {
        container: '#container',
        baseUrl,
        darkMode: true,
      })
      const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement
      const url = new URL(iframe.src)

      expect(url.origin).toBe(new URL(baseUrl).origin)
      expect(url.pathname).toBe('/forms/demo')
      expect(url.searchParams.get('darkMode')).toBe('true')
      expect(url.searchParams.get('_sdkToken')).toBeTruthy()
      expect(url.searchParams.get('_sdkParentOrigin')).toBe('https://embedder.example.test')

      mockIframePostMessage(dom.window, iframe)
      simulateHandshakeAck(dom.window, iframe)
      await form.setField('email', 'user@example.test')

      expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({ type: 'opnform:command', _sdkToken: expect.any(String) }),
        url.origin,
      )
      dom.window.close()
    })

    it.each([
      'https://opnform.com/forms/demo',
      'https://example.test/forms/demo/',
      'https://forms.example.test/forms/demo',
      'http://localhost:3212/forms/demo',
    ])('uses the full URL %s ahead of baseUrl and registers the form by slug', async (formUrl) => {
      const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>', {
        url: 'https://embedder.example.test/',
        runScripts: 'outside-only',
      })
      dom.window.eval(source)
      dom.window.opnform.init({ autoResize: false })
      const onSubmit = vi.fn()
      const form = dom.window.opnform.create(formUrl, {
        container: '#container',
        baseUrl: 'https://ignored.example.test',
        onSubmit,
      })
      const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement
      const url = new URL(iframe.src)

      expect(url.origin + url.pathname).toBe(formUrl)
      expect(iframe.id).toBe('demo')
      expect(dom.window.opnform.get('demo')).toBe(form)
      expect(url.searchParams.get('_sdkParentOrigin')).toBe('https://embedder.example.test')

      mockIframePostMessage(dom.window, iframe)
      simulateHandshakeAck(dom.window, iframe)
      await form.setField('email', 'user@example.test')
      expect(iframe.contentWindow!.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({ type: 'opnform:command', formSlug: 'demo' }),
        url.origin,
      )

      dom.window.dispatchEvent(new dom.window.MessageEvent('message', {
        data: { type: 'opnform:event', event: 'submit', formSlug: 'demo', payload: { data: { email: 'user@example.test' } } },
        origin: url.origin,
        source: iframe.contentWindow,
      }))
      expect(onSubmit).toHaveBeenCalledOnce()
      dom.window.close()
    })

    it.each([undefined, false])('preserves URL parameters and fragment while applying explicit darkMode=%s', (darkMode) => {
      const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>', {
        url: 'https://embedder.example.test/',
        runScripts: 'outside-only',
      })
      dom.window.eval(source)
      dom.window.opnform.init({ autoResize: false })
      dom.window.opnform.create('https://example.test/forms/demo?email=user%40example.test&tag=a&tag=b&darkMode=true&_sdkToken=old&_sdkParentOrigin=https%3A%2F%2Fold.example.test#section', {
        container: '#container',
        darkMode,
      })
      const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement
      const url = new URL(iframe.src)

      expect(url.searchParams.get('email')).toBe('user@example.test')
      expect(url.searchParams.getAll('tag')).toEqual(['a', 'b'])
      expect(url.searchParams.get('darkMode')).toBe(darkMode === undefined ? 'true' : 'false')
      expect(url.searchParams.get('_sdkToken')).toBeTruthy()
      expect(url.searchParams.get('_sdkToken')).not.toBe('old')
      expect(url.searchParams.get('_sdkParentOrigin')).toBe('https://embedder.example.test')
      expect(url.hash).toBe('#section')
      simulateHandshakeAck(dom.window, iframe)
      dom.window.close()
    })

    it.each([
      '',
      'https://',
      'https://example.test/',
      'https://example.test/forms/',
      'https://example.test/forms/demo/edit',
      '//example.test/forms/demo',
      'javascript:alert(1)',
      'ftp://example.test/forms/demo',
    ])('rejects invalid form input %s without inserting an iframe', (formInput) => {
      const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>', {
        url: 'https://embedder.example.test/',
        runScripts: 'outside-only',
      })
      dom.window.eval(source)
      vi.spyOn(dom.window.console, 'error').mockImplementation(() => {})

      expect(dom.window.opnform.create(formInput, { container: '#container' })).toBeNull()
      expect(dom.window.document.querySelector('iframe')).toBeNull()
      dom.window.close()
    })
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
