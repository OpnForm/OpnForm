<template>
  <div class="text-center py-12 px-6 bg-neutral-50 rounded-lg border-2 border-dashed border-neutral-300">
    <UIcon
      v-if="icon"
      :name="icon"
      class="mx-auto h-12 w-12 text-neutral-400"
    />
    <h3 class="mt-2 text-lg font-semibold text-neutral-900">
      {{ title }}
    </h3>
    <p
      v-if="description"
      class="mt-1 text-sm text-neutral-500"
    >
      {{ description }}
    </p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>
