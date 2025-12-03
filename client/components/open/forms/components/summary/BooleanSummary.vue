<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <!-- Bar Chart View -->
    <div v-else-if="!showPieChart">
      <!-- Single stacked bar -->
      <div class="flex rounded-full h-8 overflow-hidden">
        <div
          class="bg-blue-400 flex items-center justify-center text-xs font-medium text-white transition-all duration-300"
          :style="{ width: yesPercentage + '%' }"
        >
          <span v-if="yesPercentage >= 15">{{ yesPercentage }}%</span>
        </div>
        <div
          class="bg-neutral-300 flex items-center justify-center text-xs font-medium text-neutral-600 transition-all duration-300"
          :style="{ width: noPercentage + '%' }"
        >
          <span v-if="noPercentage >= 15">{{ noPercentage }}%</span>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex justify-center gap-6 mt-3">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-sm bg-blue-400" />
          <span class="text-sm text-neutral-600">Yes ({{ yesCount }})</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-sm bg-neutral-300" />
          <span class="text-sm text-neutral-600">No ({{ noCount }})</span>
        </div>
      </div>
    </div>

    <!-- Pie Chart View -->
    <div v-else class="flex flex-col sm:flex-row items-center justify-center gap-8 py-4">
      <div class="w-48 h-48">
        <Pie :data="chartData" :options="chartOptions" />
      </div>

      <!-- Legend -->
      <div class="space-y-2">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-sm bg-blue-400" />
          <span class="text-sm text-neutral-700">Yes ({{ yesCount }})</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-sm bg-neutral-300" />
          <span class="text-sm text-neutral-700">No ({{ noCount }})</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  field: { type: Object, required: true },
  showPieChart: { type: Boolean, default: false },
})

const chartColors = ['#51a2ff', '#D1D5DB'] // blue-400, neutral-300

const distribution = computed(() => props.field.data?.distribution || [])

const yesItem = computed(() => distribution.value.find(item => item.value === 'Yes') || { count: 0, percentage: 0 })
const noItem = computed(() => distribution.value.find(item => item.value === 'No') || { count: 0, percentage: 0 })

const yesCount = computed(() => yesItem.value.count)
const noCount = computed(() => noItem.value.count)
const yesPercentage = computed(() => yesItem.value.percentage)
const noPercentage = computed(() => noItem.value.percentage)
const hasData = computed(() => yesCount.value > 0 || noCount.value > 0)

const chartData = computed(() => ({
  labels: distribution.value.map(item => item.value),
  datasets: [{
    data: [yesCount.value, noCount.value],
    backgroundColor: chartColors,
    borderWidth: 0,
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

