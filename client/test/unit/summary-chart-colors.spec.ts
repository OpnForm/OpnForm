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

  it.each(summaryChartColors)('%s contrasts with chart backgrounds and white separators', (color) => {
    const foreground = luminance(color)
    expect((luminance('#F5F5F5') + 0.05) / (foreground + 0.05)).toBeGreaterThanOrEqual(3)
    expect((luminance('#FFFFFF') + 0.05) / (foreground + 0.05)).toBeGreaterThanOrEqual(3)
  })

  it('keeps boolean percentages readable on both segment colors', () => {
    // BooleanSummary uses white text for Yes, neutral-900 for No.
    expect(1.05 / (luminance(summaryChartColors[0]) + 0.05)).toBeGreaterThanOrEqual(4.5)
    expect((luminance(summaryChartColors[1]) + 0.05) / (luminance('#171717') + 0.05)).toBeGreaterThanOrEqual(4.5)
  })
})
