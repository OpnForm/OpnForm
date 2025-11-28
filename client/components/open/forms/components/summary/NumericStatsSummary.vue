<template>
  <div>
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <!-- Stats Grid -->
    <div v-else class="grid grid-cols-3 gap-4">
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ formatNumber(data.average) }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Average</div>
      </div>
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ formatNumber(data.min) }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Minimum</div>
      </div>
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ formatNumber(data.max) }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Maximum</div>
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

