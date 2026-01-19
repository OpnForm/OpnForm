<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-banknotes" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No payments collected yet</span>
    </div>

    <!-- Payment Stats -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Total -->
      <div class="flex flex-col p-4 bg-emerald-50 rounded-xl border border-emerald-100">
        <div class="flex items-center gap-2 mb-2">
          <UIcon name="i-heroicons-banknotes" class="w-4 h-4 text-emerald-600" />
          <span class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Total Revenue</span>
        </div>
        <div class="text-2xl font-bold text-neutral-900">
          {{ formatCurrency(data.total_amount) }}
        </div>
      </div>

      <!-- Count -->
      <div class="flex flex-col p-4 bg-neutral-50 rounded-xl border border-neutral-100">
        <div class="flex items-center gap-2 mb-2">
          <UIcon name="i-heroicons-credit-card" class="w-4 h-4 text-neutral-500" />
          <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Transactions</span>
        </div>
        <div class="text-2xl font-bold text-neutral-900">
          {{ data.transaction_count || 0 }}
        </div>
      </div>

      <!-- Average -->
      <div class="flex flex-col p-4 bg-neutral-50 rounded-xl border border-neutral-100">
        <div class="flex items-center gap-2 mb-2">
          <UIcon name="i-heroicons-calculator" class="w-4 h-4 text-neutral-500" />
          <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Avg. Amount</span>
        </div>
        <div class="text-2xl font-bold text-neutral-900">
          {{ formatCurrency(data.average_amount) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const data = computed(() => props.field.data || {})
const hasData = computed(() => (data.value.transaction_count || 0) > 0)

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '$0.00'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(value)
}
</script>

