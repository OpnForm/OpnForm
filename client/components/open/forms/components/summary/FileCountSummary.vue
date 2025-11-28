<template>
  <div>
    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4">
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ data.count || 0 }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Submissions with files</div>
      </div>
      <div class="text-center p-3 bg-neutral-50 rounded-lg">
        <div class="text-2xl font-semibold text-neutral-900">
          {{ data.total_files || 0 }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">Total files uploaded</div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const data = computed(() => props.field.data || {})
</script>

