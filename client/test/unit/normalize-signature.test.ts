import { describe, expect, it, vi } from 'vitest'
import {
  getNormalizedSignatureData,
  normalizeSignatureCanvas,
} from '../../lib/forms/normalize-signature.js'

describe('normalizeSignatureCanvas', () => {
  it('exports signature pixels as black ink on an opaque white background', () => {
    const operations: string[][] = []
    const normalizedContext = {
      drawImage: vi.fn((...args) => operations.push(['drawImage', ...args.slice(1).map(String)])),
      fillRect: vi.fn(() => operations.push(['fillRect'])),
    }

    Object.defineProperties(normalizedContext, {
      globalCompositeOperation: {
        set(value: string) {
          operations.push(['globalCompositeOperation', value])
        },
      },
      fillStyle: {
        set(value: string) {
          operations.push(['fillStyle', value])
        },
      },
    })

    const normalizedCanvas = {
      width: 0,
      height: 0,
      getContext: vi.fn(() => normalizedContext),
      toDataURL: vi.fn(() => 'data:image/png;base64,normalized'),
    }
    const sourceWidth = 100
    const sourceHeight = 50
    const pixels = new Uint8ClampedArray(sourceWidth * sourceHeight * 4)
    for (let y = 10; y < 30; y++) {
      for (let x = 20; x < 60; x++) {
        pixels[(y * sourceWidth + x) * 4 + 3] = 255
      }
    }
    const sourceCanvas = {
      width: sourceWidth,
      height: sourceHeight,
      getContext: vi.fn(() => ({
        getImageData: vi.fn(() => ({ data: pixels })),
      })),
    } as unknown as HTMLCanvasElement
    const result = normalizeSignatureCanvas(
      sourceCanvas,
      () => normalizedCanvas as unknown as HTMLCanvasElement,
    )

    expect(normalizedCanvas.width).toBe(56)
    expect(normalizedCanvas.height).toBe(36)
    expect(operations).toEqual([
      ['drawImage', '12', '2', '56', '36', '0', '0', '56', '36'],
      ['globalCompositeOperation', 'source-in'],
      ['fillStyle', '#000000'],
      ['fillRect'],
      ['globalCompositeOperation', 'destination-over'],
      ['fillStyle', '#ffffff'],
      ['fillRect'],
    ])
    expect(normalizedCanvas.toDataURL).toHaveBeenCalledWith('image/png')
    expect(result).toBe('data:image/png;base64,normalized')
  })

  it('normalizes the canvas without encoding the original signature first', () => {
    const canvas = {} as HTMLCanvasElement
    const normalizeCanvas = vi.fn(() => 'data:image/png;base64,normalized')
    const saveSignature = vi.fn(() => ({
      isEmpty: false,
      data: 'data:image/png;base64,original',
    }))

    const result = getNormalizedSignatureData({
      isEmpty: vi.fn(() => false),
      saveSignature,
      signaturePad: { canvas },
    }, normalizeCanvas)

    expect(normalizeCanvas).toHaveBeenCalledWith(canvas)
    expect(saveSignature).not.toHaveBeenCalled()
    expect(result).toBe('data:image/png;base64,normalized')
  })
})
