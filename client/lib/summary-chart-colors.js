// Each color contrasts at least 3:1 with white and the neutral-100 bar track.
// Keep labels and values visible: color alone cannot identify every category.
export const summaryChartColors = [
  '#0072B2', // blue
  '#B45309', // amber
  '#007F73', // teal
  '#9F4B96', // purple
  '#B23A48', // red
  '#5267B3', // indigo
  '#6C7420', // olive
  '#8A572A', // brown
  '#167D9A', // cyan
  '#6B5B73', // slate purple
]

export function getSummaryChartColor(index) {
  return summaryChartColors[index % summaryChartColors.length]
}
