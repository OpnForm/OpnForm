import {
  resolveInitialTrustedOrigin,
  WILDCARD_ORIGIN,
} from './sdkBridgeMessaging.js'

export const EDITOR_MSG_TYPE = 'opnform:editor:event'

export const EDITOR_EVENTS = {
  FORM_SAVED: 'formSaved',
  FORM_DELETED: 'formDeleted',
  NAVIGATE_BACK: 'navigateBack',
}

const ROUTE_VIEW_MAP = {
  home: 'forms_list',
  'forms-create': 'create_form',
  'forms-slug-edit': 'edit_form',
  'forms-slug-show-submissions': 'submissions',
  'forms-slug-show-share': 'share',
  'forms-slug-show-integrations': 'integrations',
  'forms-slug-show-stats': 'stats',
  'forms-slug-show-summary': 'summary',
  'forms-slug-show-pdf-templates': 'pdf_templates',
}

function isEmbeddedInIframe() {
  if (typeof window === 'undefined') return false
  return window.location !== window.parent.location || window.frameElement !== null
}

function getPostTargetOrigin() {
  return resolveInitialTrustedOrigin(isEmbeddedInIframe()) || WILDCARD_ORIGIN
}

function toPlainObject(value) {
  if (value === null || value === undefined) return value

  try {
    return JSON.parse(JSON.stringify(value))
  } catch {
    return {}
  }
}

function normalizeFormPayload(form) {
  if (!form) return null

  return {
    id: form.id,
    slug: form.slug,
    title: form.title,
    visibility: form.visibility,
  }
}

export function resolveEditorView(routeName) {
  if (!routeName) return null
  return ROUTE_VIEW_MAP[routeName] || routeName
}

export function resolveEditorRouteView(routeName) {
  return {
    view: resolveEditorView(routeName),
    route: routeName,
  }
}

function postMessageSafe(target, message, origin) {
  try {
    target.postMessage(message, origin || WILDCARD_ORIGIN)
  } catch (error) {
    if (error?.name !== 'DataCloneError') {
      console.error('[OpnForm Editor Embed] postMessage failed:', error)
    }
  }
}

/**
 * Sends an editor embed event to the parent window when running inside an iframe.
 */
export function emitEditorEvent(event, payload = {}) {
  if (typeof window === 'undefined') return

  const message = {
    type: EDITOR_MSG_TYPE,
    event,
    payload: toPlainObject(payload),
  }

  if (isEmbeddedInIframe()) {
    postMessageSafe(window.parent, message, getPostTargetOrigin())
  }

  postMessageSafe(window, message, window.location.origin)
}

export function emitEditorFormSaved(form, { isNew = false } = {}) {
  emitEditorEvent(EDITOR_EVENTS.FORM_SAVED, {
    form: normalizeFormPayload(form),
    isNew,
  })
}

export function emitEditorFormDeleted(form) {
  emitEditorEvent(EDITOR_EVENTS.FORM_DELETED, {
    form: normalizeFormPayload(form),
  })
}

export function emitEditorNavigateBack(fromRouteName, toRouteName) {
  emitEditorEvent(EDITOR_EVENTS.NAVIGATE_BACK, {
    from: resolveEditorRouteView(fromRouteName),
    to: resolveEditorRouteView(toRouteName),
  })
}
