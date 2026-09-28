import { describe, expect, it } from 'vitest'
import { summaryChartColors } from '../../lib/summary-chart-colors'

function luminance(hex: string) {
  const [r, g, b] = hex.slice(1).match(/.{2}/g)!.map(value => {
    const channel = parseInt(value, 16) / 255
    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

describe('summary chart contrast', () => {
  it('provides ten distinct category colors', () => {
    expect(new Set(summaryChartColors).size).toBeGreaterThanOrEqual(10)
  })

  it.each(summaryChartColors)('%s contrasts with chart backgrounds and white text', (color) => {
    const foreground = luminance(color)
    expect((luminance('#F5F5F5') + 0.05) / (foreground + 0.05)).toBeGreaterThanOrEqual(3)
    expect((luminance('#FFFFFF') + 0.05) / (foreground + 0.05)).toBeGreaterThanOrEqual(4.5)
  })
})
