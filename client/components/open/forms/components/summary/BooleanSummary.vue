<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-scale" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No responses yet</span>
    </div>

    <!-- Bar Chart View -->
    <div v-else-if="!showPieChart">
      <!-- Single stacked bar -->
      <div class="flex rounded-lg h-10 overflow-hidden gap-0.5 bg-white">
        <div
          class="flex items-center justify-center text-sm font-semibold text-white transition-all duration-500 relative group overflow-hidden"
          :style="{ width: yesPercentage + '%', backgroundColor: chartColors[0] }"
        >
          <span v-if="yesPercentage >= 10">{{ yesPercentage }}%</span>
        </div>
        <div
          class="flex items-center justify-center text-sm font-semibold text-neutral-900 transition-all duration-500 relative group overflow-hidden"
          :style="{ width: noPercentage + '%', backgroundColor: chartColors[1] }"
        >
          <span v-if="noPercentage >= 10">{{ noPercentage }}%</span>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex justify-between mt-3 px-1">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: chartColors[0] }" aria-hidden="true" />
          <div class="text-sm">
            <span class="font-medium text-neutral-900">Yes</span>
            <span class="text-neutral-600 ml-1">{{ yesCount }} ({{ yesPercentage }}%)</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <div class="text-sm text-right">
            <span class="font-medium text-neutral-900">No</span>
            <span class="text-neutral-600 ml-1">{{ noCount }} ({{ noPercentage }}%)</span>
          </div>
          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: chartColors[1] }" aria-hidden="true" />
        </div>
      </div>
    </div>

    <!-- Pie Chart View -->
    <div v-else class="flex flex-col sm:flex-row items-center justify-center gap-8 py-2">
      <div class="w-48 h-48 relative shrink-0" aria-hidden="true">
        <Pie :data="chartData" :options="chartOptions" />
      </div>

      <!-- Legend -->
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: chartColors[0] }" aria-hidden="true" />
          <div class="flex flex-col">
            <span class="text-sm font-medium text-neutral-900">Yes</span>
            <span class="text-xs text-neutral-600">{{ yesCount }} responses ({{ yesPercentage }}%)</span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: chartColors[1] }" aria-hidden="true" />
          <div class="flex flex-col">
            <span class="text-sm font-medium text-neutral-900">No</span>
            <span class="text-xs text-neutral-600">{{ noCount }} responses ({{ noPercentage }}%)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { getSummaryChartColor } from '~/lib/summary-chart-colors'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  field: { type: Object, required: true },
  showPieChart: { type: Boolean, default: false },
})

const chartColors = [getSummaryChartColor(0), getSummaryChartColor(1)]

const distribution = computed(() => props.field.data?.distribution || [])

const yesItem = computed(() => distribution.value.find(item => item.value === 'Yes') || { count: 0, percentage: 0 })
const noItem = computed(() => distribution.value.find(item => item.value === 'No') || { count: 0, percentage: 0 })

const yesCount = computed(() => yesItem.value.count)
const noCount = computed(() => noItem.value.count)
const yesPercentage = computed(() => yesItem.value.percentage)
const noPercentage = computed(() => noItem.value.percentage)
const hasData = computed(() => yesCount.value > 0 || noCount.value > 0)

const chartData = computed(() => ({
  labels: ['Yes', 'No'],
  datasets: [{
    data: [yesCount.value, noCount.value],
    backgroundColor: chartColors,
    borderColor: '#FFFFFF',
    borderWidth: 2,
    hoverBorderColor: '#FFFFFF',
  }]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      callbacks: {
        label: (context) => {
          const label = context.label
          const count = context.raw
          const percentage = label === 'Yes' ? yesPercentage.value : noPercentage.value
          return `${label}: ${count} (${percentage}%)`
        }
      }
    }
  }
}
</script>
