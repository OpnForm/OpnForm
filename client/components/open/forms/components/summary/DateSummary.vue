<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-calendar" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No dates collected yet</span>
    </div>

    <!-- Date Stats -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="flex items-start gap-4 p-4 bg-neutral-50 rounded-xl border border-neutral-100 relative overflow-hidden group">
        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center border border-neutral-200 shadow-sm z-10">
          <UIcon name="i-heroicons-clock" class="w-5 h-5 text-neutral-500" />
        </div>
        
        <div class="z-10">
          <div class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">Earliest Response</div>
          <div class="text-lg font-bold text-neutral-900">
            {{ formatDate(data.earliest) }}
          </div>
        </div>

        <!-- Decorative background element -->
        <UIcon name="i-heroicons-clock" class="absolute -right-4 -bottom-4 w-24 h-24 text-neutral-100 opacity-50 transform rotate-12 group-hover:scale-110 transition-transform duration-500" />
      </div>

      <div class="flex items-start gap-4 p-4 bg-neutral-50 rounded-xl border border-neutral-100 relative overflow-hidden group">
        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center border border-neutral-200 shadow-sm z-10">
          <UIcon name="i-heroicons-calendar" class="w-5 h-5 text-neutral-500" />
        </div>
        
        <div class="z-10">
          <div class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-1">Latest Response</div>
          <div class="text-lg font-bold text-neutral-900">
            {{ formatDate(data.latest) }}
          </div>
        </div>

        <!-- Decorative background element -->
        <UIcon name="i-heroicons-calendar" class="absolute -right-4 -bottom-4 w-24 h-24 text-neutral-100 opacity-50 transform rotate-12 group-hover:scale-110 transition-transform duration-500" />
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

