export function createAgentDraftAutosave(sync, delay = 900) {
  let timer = null

  function clear() {
    if (timer === null) return
    clearTimeout(timer)
    timer = null
  }

  function schedule() {
    clear()
    timer = setTimeout(() => {
      timer = null
      sync({ keepalive: false }).catch(() => {})
    }, delay)
  }

  function flush() {
    if (timer === null) return Promise.resolve()
    clear()
    return sync({ keepalive: true })
  }

  return { schedule, flush }
}
