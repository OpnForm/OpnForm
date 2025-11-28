<template>
  <div>
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <!-- Date Stats -->
    <div v-else class="grid grid-cols-2 gap-4">
      <div class="p-3 bg-neutral-50 rounded-lg">
        <div class="text-xs text-neutral-500 mb-1">Earliest</div>
        <div class="text-lg font-medium text-neutral-900">
          {{ formatDate(data.earliest) }}
        </div>
      </div>
      <div class="p-3 bg-neutral-50 rounded-lg">
        <div class="text-xs text-neutral-500 mb-1">Latest</div>
        <div class="text-lg font-medium text-neutral-900">
          {{ formatDate(data.latest) }}
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
const hasData = computed(() => (data.value.count || 0) > 0)

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  try {
    const date = new Date(dateStr)
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  } catch {
    return dateStr
  }
}
</script>

