export function normalizeSignatureCanvas(sourceCanvas, createCanvas = () => document.createElement('canvas')) {
  const normalizedCanvas = createCanvas()
  normalizedCanvas.width = sourceCanvas.width
  normalizedCanvas.height = sourceCanvas.height

  const context = normalizedCanvas.getContext('2d')
  if (!context) {
    throw new Error('Unable to normalize signature canvas')
  }

  context.drawImage(sourceCanvas, 0, 0)

  context.globalCompositeOperation = 'source-in'
  context.fillStyle = '#000000'
  context.fillRect(0, 0, normalizedCanvas.width, normalizedCanvas.height)

  context.globalCompositeOperation = 'destination-over'
  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, normalizedCanvas.width, normalizedCanvas.height)

  return normalizedCanvas.toDataURL('image/png')
}
