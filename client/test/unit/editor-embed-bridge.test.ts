import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { JSDOM } from 'jsdom'
import {
  EDITOR_EVENTS,
  EDITOR_MSG_TYPE,
  emitEditorEvent,
  emitEditorFormDeleted,
  emitEditorFormSaved,
  emitEditorNavigateBack,
  resolveEditorRouteView,
  resolveEditorView,
} from '../../lib/sdk/editorEmbedBridge.js'

const PARENT_ORIGIN = 'https://cms.example.test'
const EDITOR_ORIGIN = 'https://opnform.example.test'

function installIframeWindow(dom: JSDOM, search = '') {
  const { window } = dom
  const mockParent = {
    postMessage: vi.fn(),
  }

  Object.defineProperty(window, 'parent', {
    configurable: true,
    value: mockParent,
  })

  Object.defineProperty(window, 'frameElement', {
    configurable: true,
    value: {},
  })

  window.history.replaceState({}, '', `${window.location.pathname}${search}`)

  return { window, mockParent }
}

describe('editor embed bridge', () => {
  let dom: JSDOM
  let mockParent: { postMessage: ReturnType<typeof vi.fn> }
  let windowMessages: Array<{ message: unknown, origin: string }>

  beforeEach(() => {
    dom = new JSDOM('<!doctype html><html><body></body></html>', {
      url: `${EDITOR_ORIGIN}/forms/demo-form/edit`,
    })

    const installed = installIframeWindow(dom)
    mockParent = installed.mockParent
    windowMessages = []

    vi.spyOn(dom.window, 'postMessage').mockImplementation((message: unknown, origin: string) => {
      windowMessages.push({ message, origin })
    })

    vi.stubGlobal('window', dom.window)
    vi.stubGlobal('document', dom.window.document)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('resolveEditorView', () => {
    it('maps known route names to stable view identifiers', () => {
      expect(resolveEditorView('home')).toBe('forms_list')
      expect(resolveEditorView('forms-slug-edit')).toBe('edit_form')
      expect(resolveEditorView('forms-slug-show-submissions')).toBe('submissions')
    })

    it('falls back to the route name for unknown routes', () => {
      expect(resolveEditorView('custom-route')).toBe('custom-route')
    })

    it('returns null when route name is missing', () => {
      expect(resolveEditorView(null)).toBeNull()
    })
  })

  describe('resolveEditorRouteView', () => {
    it('returns both view and route fields', () => {
      expect(resolveEditorRouteView('forms-slug-edit')).toEqual({
        view: 'edit_form',
        route: 'forms-slug-edit',
      })
    })
  })

  describe('emitEditorEvent', () => {
    it('posts to parent and current window with the editor message type', () => {
      emitEditorEvent('testEvent', { foo: 'bar' })

      expect(mockParent.postMessage).toHaveBeenCalledWith(
        {
          type: EDITOR_MSG_TYPE,
          event: 'testEvent',
          payload: { foo: 'bar' },
        },
        '*',
      )

      expect(windowMessages).toContainEqual({
        message: {
          type: EDITOR_MSG_TYPE,
          event: 'testEvent',
          payload: { foo: 'bar' },
        },
        origin: EDITOR_ORIGIN,
      })
    })

    it('uses _sdkParentOrigin when provided in the iframe URL', () => {
      const installed = installIframeWindow(dom, `?_sdkParentOrigin=${encodeURIComponent(PARENT_ORIGIN)}`)

      emitEditorEvent('testEvent', {})

      expect(installed.mockParent.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({ type: EDITOR_MSG_TYPE }),
        PARENT_ORIGIN,
      )
    })

    it('does not post to parent when not embedded in an iframe', () => {
      Object.defineProperty(dom.window, 'parent', {
        configurable: true,
        value: dom.window,
      })
      Object.defineProperty(dom.window, 'frameElement', {
        configurable: true,
        value: null,
      })

      emitEditorEvent('testEvent', {})

      expect(mockParent.postMessage).not.toHaveBeenCalled()
      expect(windowMessages).toHaveLength(1)
    })
  })

  describe('named emit helpers', () => {
    it('emits formSaved with normalized form payload', () => {
      emitEditorFormSaved({
        id: 1,
        slug: 'demo-form',
        title: 'Demo',
        visibility: 'public',
        extra: 'ignored',
      }, { isNew: true })

      expect(mockParent.postMessage).toHaveBeenCalledWith(
        {
          type: EDITOR_MSG_TYPE,
          event: EDITOR_EVENTS.FORM_SAVED,
          payload: {
            form: {
              id: 1,
              slug: 'demo-form',
              title: 'Demo',
              visibility: 'public',
            },
            isNew: true,
          },
        },
        '*',
      )
    })

    it('emits formDeleted with normalized form payload', () => {
      emitEditorFormDeleted({
        id: 2,
        slug: 'old-form',
        title: 'Old',
        visibility: 'draft',
      })

      expect(mockParent.postMessage).toHaveBeenCalledWith(
        {
          type: EDITOR_MSG_TYPE,
          event: EDITOR_EVENTS.FORM_DELETED,
          payload: {
            form: {
              id: 2,
              slug: 'old-form',
              title: 'Old',
              visibility: 'draft',
            },
          },
        },
        '*',
      )
    })

    it('emits navigateBack with from and to route views', () => {
      emitEditorNavigateBack('forms-slug-edit', 'forms-slug-show-submissions')

      expect(mockParent.postMessage).toHaveBeenCalledWith(
        {
          type: EDITOR_MSG_TYPE,
          event: EDITOR_EVENTS.NAVIGATE_BACK,
          payload: {
            from: {
              view: 'edit_form',
              route: 'forms-slug-edit',
            },
            to: {
              view: 'submissions',
              route: 'forms-slug-show-submissions',
            },
          },
        },
        '*',
      )
    })
  })
})
