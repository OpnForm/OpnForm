import { describe, expect, it, vi } from 'vitest'
import { normalizeSignatureCanvas } from '../../lib/forms/normalize-signature.js'

describe('normalizeSignatureCanvas', () => {
  it('exports signature pixels as black ink on an opaque white background', () => {
    const operations: string[][] = []
    const context = {
      drawImage: vi.fn(() => operations.push(['drawImage'])),
      fillRect: vi.fn(() => operations.push(['fillRect'])),
    }

    Object.defineProperties(context, {
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
      getContext: vi.fn(() => context),
      toDataURL: vi.fn(() => 'data:image/png;base64,normalized'),
    }
    const sourceCanvas = { width: 1260, height: 300 } as HTMLCanvasElement
    const result = normalizeSignatureCanvas(
      sourceCanvas,
      () => normalizedCanvas as unknown as HTMLCanvasElement,
    )

    expect(normalizedCanvas.width).toBe(1260)
    expect(normalizedCanvas.height).toBe(300)
    expect(operations).toEqual([
      ['drawImage'],
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
})
