<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No payments
    </div>

    <!-- Payment Stats -->
    <div v-else class="grid grid-cols-3 gap-4">
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ formatCurrency(data.total_amount) }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Total Amount</div>
      </div>
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ data.transaction_count || 0 }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Transactions</div>
      </div>
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ formatCurrency(data.average_amount) }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Average</div>
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

