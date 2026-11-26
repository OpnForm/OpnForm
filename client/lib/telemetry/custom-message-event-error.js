export function isAnonymousFormMessageCloneError (event) {
  if (event.transaction !== '/forms/:slug()') {
    return false
  }

  const exception = event.exception?.values?.[0]
  const frames = exception?.stacktrace?.frames

  // Inline form scripts can accidentally pass the DOM event instead of its data.
  return exception?.type === 'DataCloneError' &&
    exception.value === "Failed to execute 'postMessage' on 'Window': MessageEvent object could not be cloned." &&
    exception.mechanism?.type === 'auto.browser.browserapierrors.addEventListener' &&
    frames?.at(-1)?.filename === '<anonymous>'
}
