<template>
  <div class="w-full">
    <h3 class="font-medium text-lg mb-4">Traffic Breakdown</h3>
    
    <div v-if="!form.is_pro" class="border border-neutral-300 rounded-lg shadow-xs p-4 relative overflow-hidden">
      <div class="absolute inset-0 z-10">
        <div class="p-5 max-w-md mx-auto flex flex-col items-center justify-center h-full">
          <p class="text-center">
            You need a <pro-tag
              upgrade-modal-title="Upgrade today to access detailed analytics"
              class="mx-1"
            /> subscription to access detailed traffic breakdown.
          </p>
          <UButton
            class="mt-5 flex justify-center"
            @click.prevent="openSubscriptionModal({modal_title: 'Upgrade to unlock detailed Analytics'})"
            label="Subscribe"
          />
        </div>
      </div>
      <img
        src="/img/pages/forms/blurred_graph.png"
        alt="Sample Graph"
        class="mx-auto w-full filter blur-md z-0 pointer-events-none"
      >
    </div>
    
    <VTransition v-else name="fade">
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="i in 6" :key="i" class="border border-neutral-300 rounded-lg shadow-xs p-4 space-y-4">
          <USkeleton class="h-6 w-24" />
          <div class="space-y-4">
            <USkeleton class="h-4 w-full rounded-full" />
            <div class="flex flex-wrap gap-2 justify-center">
              <USkeleton v-for="j in 3" :key="j" class="h-4 w-16 rounded" />
            </div>
          </div>
        </div>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div 
          v-for="chart in chartTypes" 
          :key="chart.id"
          class="border border-neutral-300 rounded-lg shadow-xs p-4"
        >
          <h4 class="font-medium mb-3">{{ chart.title }}</h4>
          
          <div v-if="Object.keys(metaStats[chart.id] || {}).length === 0" class="text-sm text-gray-500 text-center py-2">
            No data available
          </div>
          
          <div v-else class="space-y-4">
            <div class="w-full h-4 flex rounded-full overflow-hidden">
              <div 
                v-for="(item, index) in getDisplayChartData(chart.id)" 
                :key="index"
                :style="{ 
                  width: item.percentage + '%',
                  backgroundColor: item.color
                }"
                class="h-full"
                :title="`${item.displayName}: ${item.count} (${item.percentage}%)`"
              ></div>
            </div>
            
            <div class="flex flex-wrap gap-3 items-center justify-center mt-2">
              <template v-for="(item, index) in getDisplayChartData(chart.id)" :key="index">
                <div v-if="item.type !== 'others'" class="flex items-center gap-1">
                  <div 
                    class="w-3 h-3 rounded-sm"
                    :style="{ backgroundColor: item.color }"
                  ></div>
                  <span class="text-xs">{{ item.displayName }} ({{ item.count }})</span>
                </div>
                
                <!-- "Other" group with popover -->
                <UPopover v-else arrow>
                  <div class="flex items-center gap-1 cursor-pointer">
                    <div class="w-3 h-3 rounded-sm" :style="{ backgroundColor: item.color }"></div>
                    <span class="text-xs">{{ item.displayName }} ({{ item.count }})</span>
                  </div>
                  
                  <template #content>
                    <div class="w-72 p-3 max-h-40 overflow-y-auto bg-white shadow-lg rounded-md">
                      <div class="space-y-2">
                        <div 
                          v-for="otherItem in getOtherItems(chart.id)" 
                          :key="otherItem.type" 
                          class="flex items-center justify-between"
                        >
                          <span class="text-sm">{{ otherItem.displayName }}</span>
                          <span class="text-sm font-medium">{{ otherItem.count }}</span>
                        </div>
                      </div>
                    </div>
                  </template>
                </UPopover>
              </template>
            </div>
          </div>
        </div>
      </div>
    </VTransition>
  </div>
</template>

<script setup>
import ProTag from "~/components/app/ProTag.vue"

const props = defineProps({
  form: { 
    type: Object, 
    required: true 
  },
  metaData: {
    type: Object,
    required: true
  },
  isLoading: {
    type: Boolean,
    default: false
  }
})

const { openSubscriptionModal } = useAppModals()

// Chart types configuration
const chartTypes = [
  {
    id: 'source',
    title: 'Traffic Sources'
  },
  {
    id: 'device',
    title: 'Devices'
  },
  {
    id: 'browser',
    title: 'Browsers'
  },
  {
    id: 'os',
    title: 'Operating Systems'
  },
  {
    id: 'country',
    title: 'Countries'
  }
]

// Consistent colors for all chart types
const CHART_COLORS = [
  '#2563eb', // blue-600 (1st item)
  '#16a34a', // green-600 (2nd item)
  '#f59e0b', // amber-500 (3rd item)
  '#ec4899', // pink-500 (4th item)
  '#8b5cf6', // purple-500 (5th item)
  '#9ca3af', // gray-400 (Other)
]

// Maximum number of items to show in legend before grouping as "Other"
const MAX_LEGEND_ITEMS = 5

// Meta stats data
const metaStats = computed(() => {
  return {
    source: props.metaData?.source || {},
    device: props.metaData?.device || {},
    country: props.metaData?.country || {},
    browser: props.metaData?.browser || {},
    os: props.metaData?.os || {}
  }
})

// Generate chart data for a specific chart type
const getChartData = (chartId) => {
  const chart = chartTypes.find(c => c.id === chartId)
  if (!chart) return []
  
  const data = metaStats.value[chart.id]
  if (!data || Object.keys(data).length === 0) return []
  
  // Calculate total
  const total = Object.values(data).reduce((sum, count) => sum + count, 0)
  
  // Format and sort data
  return Object.entries(data).map(([type, count]) => {
    const displayName = type.charAt(0).toUpperCase() + type.slice(1)
    return {
      type,
      displayName,
      count,
      percentage: Math.round((count / total) * 100)
    }
  }).sort((a, b) => b.count - a.count) // Sort by count, highest first
}

// Get all items not shown in the main legend - this is still needed for the popover content
const getOtherItems = (chartId) => {
  const data = getChartData(chartId)
  if (data.length <= MAX_LEGEND_ITEMS) return []
  
  return data.slice(MAX_LEGEND_ITEMS)
}

// Get display-ready chart data with Others included
const getDisplayChartData = (chartId) => {
  const allData = getChartData(chartId)
  if (allData.length === 0) return []
  
  // Get the top N items
  const topItems = allData.slice(0, MAX_LEGEND_ITEMS).map((item, index) => ({
    ...item,
    color: CHART_COLORS[index] // Assign color based on position
  }))
  
  // If there are more items, add an "Others" category
  if (allData.length > MAX_LEGEND_ITEMS) {
    // Calculate total count and percentage for "Others"
    const othersCount = allData.slice(MAX_LEGEND_ITEMS).reduce((sum, item) => sum + item.count, 0)
    const totalCount = allData.reduce((sum, item) => sum + item.count, 0)
    const othersPercentage = Math.round((othersCount / totalCount) * 100)
    
    // Add Others to the result
    topItems.push({
      type: 'others',
      displayName: 'Others',
      count: othersCount,
      percentage: othersPercentage,
      color: CHART_COLORS[CHART_COLORS.length - 1] // Last color is for Others
    })
  }
  
  return topItems
}
</script>