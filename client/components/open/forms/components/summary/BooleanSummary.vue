<template>
  <div>
    <!-- Bar Chart View -->
    <div v-if="!showPieChart" class="space-y-2">
      <div
        v-for="item in distribution"
        :key="item.value"
        class="flex items-center gap-3"
      >
        <!-- Label -->
        <div class="w-16 text-sm text-neutral-700 flex-shrink-0">
          {{ item.value }}
        </div>

        <!-- Bar -->
        <div class="flex-1 bg-neutral-100 rounded-full h-6 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="item.value === 'Yes' ? 'bg-green-400' : 'bg-neutral-300'"
            :style="{ width: item.percentage + '%' }"
          />
        </div>

        <!-- Stats -->
        <div class="w-12 text-right text-sm text-neutral-500 flex-shrink-0">
          {{ item.percentage }}%
        </div>
        <div class="w-24 text-right text-sm text-neutral-400 flex-shrink-0">
          {{ item.count }} {{ item.count === 1 ? 'response' : 'responses' }}
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="distribution.length === 0"
        class="text-center py-4 text-neutral-400 text-sm"
      >
        No responses
      </div>
    </div>

    <!-- Pie Chart View -->
    <div v-else class="flex flex-col sm:flex-row items-center justify-center gap-8 py-4">
      <div class="w-48 h-48">
        <Pie :data="chartData" :options="chartOptions" />
      </div>

      <!-- Legend -->
      <div class="space-y-2">
        <div
          v-for="(item, index) in distribution"
          :key="item.value"
          class="flex items-center gap-2"
        >
          <div
            class="w-3 h-3 rounded-sm flex-shrink-0"
            :style="{ backgroundColor: chartColors[index] }"
          />
          <span class="text-sm text-neutral-700">{{ item.value }}</span>
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

const chartColors = ['#4ADE80', '#D1D5DB'] // green-400, neutral-300

const distribution = computed(() => props.field.data?.distribution || [])

const chartData = computed(() => ({
  labels: distribution.value.map(item => item.value),
  datasets: [{
    data: distribution.value.map(item => item.count),
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
          const item = distribution.value[context.dataIndex]
          return `${item.value}: ${item.count} (${item.percentage}%)`
        }
      }
    }
  }
}
</script>

