// Each color contrasts at least 3:1 with white and the neutral-100 bar track.
// Keep labels and values visible: color alone cannot identify every category.
export const summaryChartColors = [
  '#2563EB', // blue
  '#EA580C', // orange
  '#059669', // emerald
  '#9333EA', // purple
  '#E11D48', // rose
  '#0891B2', // cyan
  '#B87900', // gold
  '#DB2777', // pink
  '#4F46E5', // indigo
  '#568F0B', // lime
]

export function getSummaryChartColor(index) {
  return summaryChartColors[index % summaryChartColors.length]
}
