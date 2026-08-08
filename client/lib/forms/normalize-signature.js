const getSignatureBounds = (sourceCanvas) => {
  const sourceContext = sourceCanvas.getContext('2d')
  if (!sourceContext) {
    throw new Error('Unable to read signature canvas')
  }

  const { width, height } = sourceCanvas
  const pixels = sourceContext.getImageData(0, 0, width, height).data
  let minX = width
  let minY = height
  let maxX = -1
  let maxY = -1

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      if (pixels[(y * width + x) * 4 + 3] === 0) continue

      minX = Math.min(minX, x)
      minY = Math.min(minY, y)
      maxX = Math.max(maxX, x)
      maxY = Math.max(maxY, y)
    }
  }

  if (maxX < minX || maxY < minY) {
    return { x: 0, y: 0, width, height }
  }

  const padding = Math.max(8, Math.round(Math.min(width, height) * 0.04))
  const x = Math.max(0, minX - padding)
  const y = Math.max(0, minY - padding)
  const right = Math.min(width, maxX + padding + 1)
  const bottom = Math.min(height, maxY + padding + 1)

  return { x, y, width: right - x, height: bottom - y }
}

export function normalizeSignatureCanvas(sourceCanvas, createCanvas = () => document.createElement('canvas')) {
  const bounds = getSignatureBounds(sourceCanvas)
  const normalizedCanvas = createCanvas()
  normalizedCanvas.width = bounds.width
  normalizedCanvas.height = bounds.height

  const context = normalizedCanvas.getContext('2d')
  if (!context) {
    throw new Error('Unable to normalize signature canvas')
  }

  context.drawImage(
    sourceCanvas,
    bounds.x,
    bounds.y,
    bounds.width,
    bounds.height,
    0,
    0,
    bounds.width,
    bounds.height,
  )

  context.globalCompositeOperation = 'source-in'
  context.fillStyle = '#000000'
  context.fillRect(0, 0, normalizedCanvas.width, normalizedCanvas.height)

  context.globalCompositeOperation = 'destination-over'
  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, normalizedCanvas.width, normalizedCanvas.height)

  return normalizedCanvas.toDataURL('image/png')
}

export function getNormalizedSignatureData(signaturePad, normalizeCanvas = normalizeSignatureCanvas) {
  if (signaturePad.isEmpty()) return null

  const signatureCanvas = signaturePad.signaturePad?.canvas
  if (signatureCanvas) return normalizeCanvas(signatureCanvas)

  return signaturePad.saveSignature().data || null
}
