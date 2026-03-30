function isIgnorableError(error) {
  if (!error) {
    return false
  }

  if (error.code === "ECONNRESET" || error.code === "EPIPE") {
    return true
  }

  const message = typeof error.message === "string" ? error.message : String(error)
  return message.includes("ECONNRESET") || message.includes("EPIPE")
}

function logIgnoredError(kind, error) {
  const message = typeof error?.message === "string" ? error.message : String(error)
  console.warn(`[e2e-runtime-guard] ignored ${kind}: ${message}`)
}

function attachStreamGuard(stream, name) {
  if (!stream || typeof stream.on !== "function") {
    return
  }

  stream.on("error", (error) => {
    if (isIgnorableError(error)) {
      logIgnoredError(`${name}.error`, error)
      return
    }

    throw error
  })
}

attachStreamGuard(process.stdout, "stdout")
attachStreamGuard(process.stderr, "stderr")

const originalEmit = process.emit
process.emit = function patchedEmit(event, ...args) {
  if (event === "unhandledRejection") {
    const reason = args[0]
    if (isIgnorableError(reason)) {
      logIgnoredError("emit.unhandledRejection", reason)
      return false
    }
  }

  if (event === "uncaughtException") {
    const error = args[0]
    if (isIgnorableError(error)) {
      logIgnoredError("emit.uncaughtException", error)
      return false
    }
  }

  return originalEmit.call(this, event, ...args)
}

process.on("unhandledRejection", (reason) => {
  if (isIgnorableError(reason)) {
    logIgnoredError("unhandledRejection", reason)
    return
  }

  throw reason
})

process.on("uncaughtException", (error) => {
  if (isIgnorableError(error)) {
    logIgnoredError("uncaughtException", error)
    return
  }

  throw error
})
