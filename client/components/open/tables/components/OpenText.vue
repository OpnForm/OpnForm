<template>
  <div 
    class="text-sm text-neutral-900" 
    :title="String(value ?? '')"
    v-text="value"
  />
</template>

<script setup>
defineProps({
  value: {
    type: [String, Number],
    required: false,
    default: null
  },
})
</script>
