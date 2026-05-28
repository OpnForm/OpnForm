import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { JSDOM } from 'jsdom'
import {
  MSG_PREFIX,
  createSdkBridgeMessageHandler,
  readSdkTokenFromUrl,
  resolveInitialTrustedOrigin,
} from '../../lib/sdk/sdkBridgeMessaging.js'

const FORM_SLUG = 'demo'
const PARENT_ORIGIN = 'https://embedder.example.test'
const FORM_ORIGIN = 'https://forms.example.test'

function installIframeWindow(dom: JSDOM, search = '') {
  const { window } = dom
  const mockParent = {
    postMessage: vi.fn(),
  }

  Object.defineProperty(window, 'parent', {
    configurable: true,
    value: mockParent,
  })

  return { window, mockParent }
}

function createBridgeHarness(dom: JSDOM, options: {
  initialSdkToken?: string | null
  initialTrustedOrigin?: string | null
  onCommand?: ReturnType<typeof vi.fn>
} = {}) {
  const { window, mockParent } = installIframeWindow(dom)
  const parentMessages: Array<{ message: unknown, origin: string }> = []
  const onCommand = options.onCommand || vi.fn()

  mockParent.postMessage.mockImplementation((message: unknown, origin: string) => {
    parentMessages.push({ message, origin })
  })

  const bridge = createSdkBridgeMessageHandler({
    isIframe: true,
    getFormSlug: () => FORM_SLUG,
    initialSdkToken: options.initialSdkToken ?? null,
    initialTrustedOrigin: options.initialTrustedOrigin ?? null,
    postToParent: (message, origin) => {
      mockParent.postMessage(message, origin)
    },
    onCommand,
  })

  function dispatchMessage(data: Record<string, unknown>, overrides: {
    origin?: string
    source?: Window | { postMessage: ReturnType<typeof vi.fn> }
  } = {}) {
    bridge.onMessage(new window.MessageEvent('message', {
      data,
      origin: overrides.origin ?? PARENT_ORIGIN,
      source: (overrides.source ?? mockParent) as MessageEventSource,
    }))
  }

  return { window, mockParent, bridge, onCommand, parentMessages, dispatchMessage }
}

describe('SDK bridge messaging security', () => {
  let dom: JSDOM

  beforeEach(() => {
    dom = new JSDOM('<!doctype html><html><body></body></html>', {
      url: `${FORM_ORIGIN}/forms/${FORM_SLUG}`,
    })
    vi.stubGlobal('window', dom.window)
    vi.stubGlobal('document', dom.window.document)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('accepts handshake, locks origin, and sends handshake-ack', () => {
    const { bridge, parentMessages, dispatchMessage } = createBridgeHarness(dom)

    dispatchMessage({
      type: `${MSG_PREFIX}handshake`,
      formSlug: FORM_SLUG,
      _sdkToken: 'bridge-token',
      parentOrigin: PARENT_ORIGIN,
    })

    expect(bridge.getSdkToken()).toBe('bridge-token')
    expect(bridge.getTrustedOrigin()).toBe(PARENT_ORIGIN)
    expect(parentMessages).toContainEqual({
      message: {
        type: `${MSG_PREFIX}handshake-ack`,
        formSlug: FORM_SLUG,
        success: true,
      },
      origin: PARENT_ORIGIN,
    })
  })

  it('rejects handshake when parentOrigin does not match event origin', () => {
    const { bridge, parentMessages, dispatchMessage } = createBridgeHarness(dom)

    dispatchMessage({
      type: `${MSG_PREFIX}handshake`,
      formSlug: FORM_SLUG,
      _sdkToken: 'bridge-token',
      parentOrigin: 'https://attacker.example.test',
    })

    expect(bridge.getSdkToken()).toBeNull()
    expect(parentMessages).toHaveLength(0)
  })

  it('rejects commands without a registered SDK token', () => {
    const { onCommand, parentMessages, dispatchMessage } = createBridgeHarness(dom)

    dispatchMessage({
      type: `${MSG_PREFIX}command`,
      command: 'setField',
      formSlug: FORM_SLUG,
      requestId: 1,
      payload: { fieldId: 'email', value: 'test@example.test' },
      _sdkToken: 'wrong-token',
    })

    expect(onCommand).not.toHaveBeenCalled()
    expect(parentMessages).toContainEqual({
      message: expect.objectContaining({
        type: `${MSG_PREFIX}response`,
        success: false,
        error: 'Invalid SDK token',
        requestId: 1,
      }),
      origin: PARENT_ORIGIN,
    })
  })

  it('rejects commands when token does not match the registered token', () => {
    const { onCommand, parentMessages, dispatchMessage } = createBridgeHarness(dom, {
      initialSdkToken: 'registered-token',
      initialTrustedOrigin: PARENT_ORIGIN,
    })

    dispatchMessage({
      type: `${MSG_PREFIX}command`,
      command: 'setField',
      formSlug: FORM_SLUG,
      requestId: 2,
      payload: { fieldId: 'email', value: 'test@example.test' },
      _sdkToken: 'wrong-token',
    })

    expect(onCommand).not.toHaveBeenCalled()
    expect(parentMessages).toContainEqual({
      message: expect.objectContaining({
        success: false,
        error: 'Invalid SDK token',
      }),
      origin: PARENT_ORIGIN,
    })
  })

  it('rejects commands from an unexpected parent origin after origin lock', () => {
    const { onCommand, dispatchMessage } = createBridgeHarness(dom, {
      initialSdkToken: 'registered-token',
      initialTrustedOrigin: PARENT_ORIGIN,
    })

    dispatchMessage({
      type: `${MSG_PREFIX}command`,
      command: 'setField',
      formSlug: FORM_SLUG,
      requestId: 3,
      payload: { fieldId: 'email', value: 'test@example.test' },
      _sdkToken: 'registered-token',
    }, {
      origin: 'https://attacker.example.test',
    })

    expect(onCommand).not.toHaveBeenCalled()
  })

  it('accepts commands after handshake with matching token', () => {
    const { onCommand, dispatchMessage } = createBridgeHarness(dom)

    dispatchMessage({
      type: `${MSG_PREFIX}handshake`,
      formSlug: FORM_SLUG,
      _sdkToken: 'bridge-token',
      parentOrigin: PARENT_ORIGIN,
    })

    dispatchMessage({
      type: `${MSG_PREFIX}command`,
      command: 'setField',
      formSlug: FORM_SLUG,
      requestId: 4,
      payload: { fieldId: 'email', value: 'test@example.test' },
      _sdkToken: 'bridge-token',
    })

    expect(onCommand).toHaveBeenCalledTimes(1)
  })

  it('reads SDK token and parent origin from iframe URL params', () => {
    const urlDom = new JSDOM('<!doctype html><html><body></body></html>', {
      url: `${FORM_ORIGIN}/forms/${FORM_SLUG}?_sdkToken=url-token&_sdkParentOrigin=${encodeURIComponent(PARENT_ORIGIN)}`,
    })

    vi.stubGlobal('window', urlDom.window)
    vi.stubGlobal('document', urlDom.window.document)

    expect(readSdkTokenFromUrl()).toBe('url-token')
    expect(resolveInitialTrustedOrigin(true)).toBe(PARENT_ORIGIN)
  })
})
