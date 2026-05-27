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
            <span class="font-medium text-neutral-700 truncate max-w-[70%]">{{ item.value }}</span>
            <div class="flex items-center gap-3">
              <span class="text-neutral-500">{{ item.count }}</span>
              <span class="font-medium text-neutral-900 w-10 text-right">{{ item.percentage }}%</span>
            </div>
          </div>
          
          <div class="h-2.5 bg-neutral-100 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500 ease-out"
              :class="[
                'bg-neutral-900 group-hover:bg-neutral-800'
              ]"
              :style="{ width: item.percentage + '%', backgroundColor: chartColors[index % chartColors.length] }"
            />
          </div>
        </div>
      </div>

      <!-- Pie Chart View -->
      <div v-else class="flex flex-col sm:flex-row items-center justify-center gap-8 py-2">
        <div class="w-56 h-56 relative">
          <Pie :data="chartData" :options="chartOptions" />
        </div>

        <!-- Legend -->
        <div class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar pr-2">
          <div
            v-for="(item, index) in distribution"
            :key="item.value"
            class="flex items-center gap-2.5"
          >
            <div
              class="w-3 h-3 rounded-full flex-shrink-0"
              :style="{ backgroundColor: chartColors[index % chartColors.length] }"
            />
            <div class="text-sm">
              <span class="text-neutral-700 font-medium">{{ item.value }}</span>
              <span class="text-neutral-400 mx-1">•</span>
              <span class="text-neutral-500">{{ item.percentage }}%</span>
            </div>
          </div>
        </div>
      </div>
    </template>
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

const chartColors = [
  '#FEF3C7', // amber-100
  '#FDE68A', // amber-200
  '#FCD34D', // amber-300
  '#FBBF24', // amber-400
  '#F59E0B', // amber-500
  '#D97706', // amber-600
  '#B45309', // amber-700
  '#92400E', // amber-800
]

const distribution = computed(() => props.field.data?.distribution || [])

const chartData = computed(() => ({
  labels: distribution.value.map(item => item.value),
  datasets: [{
    data: distribution.value.map(item => item.count),
    backgroundColor: chartColors.slice(0, distribution.value.length),
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
          const item = distribution.value[context.dataIndex]
          return `${item.value}: ${item.count} (${item.percentage}%)`
        }
      }
    }
  }
}
</script>

