<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="distribution.length === 0"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-chart-pie" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No responses yet</span>
    </div>

    <template v-else>
      <!-- Bar Chart View -->
      <div v-if="!showPieChart" class="space-y-3">
        <div
          v-for="(item, index) in distribution"
          :key="item.value"
          class="group"
        >
          <div class="flex items-center justify-between text-sm mb-1">
            <span class="font-medium text-neutral-700 break-words min-w-0 max-w-[70%]">{{ item.value }}</span>
            <div class="flex items-center gap-3 shrink-0">
              <span class="text-neutral-600"><span class="sr-only">Responses: </span>{{ item.count }}</span>
              <span class="font-medium text-neutral-900 w-10 text-right">{{ item.percentage }}%</span>
            </div>
          </div>
          
          <div class="h-2.5 bg-neutral-100 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500 ease-out"
              :style="{ width: item.percentage + '%', backgroundColor: getSummaryChartColor(index) }"
            />
          </div>
        </div>
      </div>

      <!-- Pie Chart View -->
      <div v-else class="flex flex-col lg:flex-row items-center justify-center gap-8 py-2">
        <div class="w-56 h-56 relative shrink-0" aria-hidden="true">
          <Pie :data="chartData" :options="chartOptions" />
        </div>

        <!-- Legend -->
        <ul class="grid gap-x-6 gap-y-3 sm:grid-cols-2 min-w-0 max-w-full">
          <li
            v-for="(item, index) in distribution"
            :key="item.value"
            class="flex items-center gap-2.5"
          >
            <div
              class="w-3 h-3 rounded-full flex-shrink-0"
              aria-hidden="true"
              :style="{ backgroundColor: getSummaryChartColor(index) }"
            />
            <div class="text-sm min-w-0 break-words">
              <span class="text-neutral-900 font-medium">{{ item.value }}</span>
              <span class="block text-xs text-neutral-600">{{ item.count }} responses ({{ item.percentage }}%)</span>
            </div>
          </li>
        </ul>
      </div>
    </template>
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

const distribution = computed(() => props.field.data?.distribution || [])

const chartData = computed(() => ({
  labels: distribution.value.map(item => item.value),
  datasets: [{
    data: distribution.value.map(item => item.count),
    backgroundColor: distribution.value.map((_, index) => getSummaryChartColor(index)),
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
          const item = distribution.value[context.dataIndex]
          return `${item.value}: ${item.count} (${item.percentage}%)`
        }
      }
    }
  }
}
</script>
