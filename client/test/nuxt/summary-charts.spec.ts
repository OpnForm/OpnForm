import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import DistributionSummary from '~/components/open/forms/components/summary/DistributionSummary.vue'
import BooleanSummary from '~/components/open/forms/components/summary/BooleanSummary.vue'
import { summaryChartColors } from '~/lib/summary-chart-colors'

vi.mock('vue-chartjs', () => ({
  Pie: defineComponent({ name: 'Pie', props: ['data', 'options'], template: '<canvas />' }),
}))

const global = { stubs: { UIcon: true } }

describe('summary charts', () => {
  it('keeps every pie slice, legend and bar color aligned beyond the palette length', async () => {
    const distribution = Array.from({ length: summaryChartColors.length + 2 }, (_, i) => ({
      value: `Option ${i + 1}`, count: i + 1, percentage: i + 1,
    }))
    const wrapper = mount(DistributionSummary, {
      props: { field: { data: { distribution } }, showPieChart: true }, global,
    })
    const colors = wrapper.findComponent({ name: 'Pie' }).props('data').datasets[0].backgroundColor
    expect(colors).toHaveLength(distribution.length)
    const swatches = wrapper.findAll('li [aria-hidden="true"]')
    const swatchColors = swatches.map(swatch => (swatch.element as HTMLElement).style.backgroundColor)
    expect(swatches).toHaveLength(distribution.length)
    distribution.forEach((item, i) => {
      const sample = document.createElement('div')
      sample.style.backgroundColor = colors[i]
      expect(swatchColors[i]).toBe(sample.style.backgroundColor)
      expect(wrapper.findAll('li')[i].text()).toContain(`${item.count} responses (${item.percentage}%)`)
    })
    await wrapper.setProps({ showPieChart: false })
    const bars = wrapper.findAll('[style*="background-color"]')
    expect(bars.map(bar => (bar.element as HTMLElement).style.backgroundColor)).toEqual(swatchColors)
    expect(wrapper.text()).toContain('Option 12')
  })

  it('keeps Yes/No labels aligned with values when the response order differs', () => {
    const wrapper = mount(BooleanSummary, {
      props: {
        field: { data: { distribution: [
          { value: 'No', count: 9, percentage: 90 },
          { value: 'Yes', count: 1, percentage: 10 },
        ] } }, showPieChart: true,
      }, global,
    })
    const data = wrapper.findComponent({ name: 'Pie' }).props('data')
    expect(data.labels).toEqual(['Yes', 'No'])
    expect(data.datasets[0].data).toEqual([1, 9])
    expect(wrapper.text()).toContain('1 responses (10%)')
    expect(wrapper.text()).toContain('9 responses (90%)')
  })

  it('shows the percentage of small boolean segments outside the bar', () => {
    const wrapper = mount(BooleanSummary, {
      props: { field: { data: { distribution: [
        { value: 'Yes', count: 1, percentage: 1 },
        { value: 'No', count: 99, percentage: 99 },
      ] } } }, global,
    })
    expect(wrapper.text()).toContain('1 (1%)')
    expect(wrapper.text()).toContain('99 (99%)')
  })

  it('preserves the empty state', () => {
    for (const component of [DistributionSummary, BooleanSummary]) {
      const wrapper = mount(component, { props: { field: { data: {} }, showPieChart: true }, global })
      expect(wrapper.text()).toContain('No responses yet')
      expect(wrapper.find('canvas').exists()).toBe(false)
    }
  })
})
