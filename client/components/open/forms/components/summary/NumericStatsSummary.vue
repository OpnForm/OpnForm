<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-chart-bar" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No numeric data collected yet</span>
    </div>

    <!-- Stats Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="flex flex-col items-center p-4 bg-neutral-50 rounded-xl border border-neutral-100">
        <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">Average</span>
        <span class="text-2xl font-bold text-neutral-900">{{ formatNumber(data.average) }}</span>
      </div>
      
      <div class="flex flex-col items-center p-4 bg-neutral-50 rounded-xl border border-neutral-100">
        <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">Minimum</span>
        <span class="text-2xl font-bold text-neutral-900">{{ formatNumber(data.min) }}</span>
      </div>
      
      <div class="flex flex-col items-center p-4 bg-neutral-50 rounded-xl border border-neutral-100">
        <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">Maximum</span>
        <span class="text-2xl font-bold text-neutral-900">{{ formatNumber(data.max) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const data = computed(() => props.field.data || {})
const hasData = computed(() => data.value.count > 0)

const formatNumber = (value) => {
  if (value === null || value === undefined) return '-'
  return Number.isInteger(value) ? value : value.toFixed(2)
}
</script>

