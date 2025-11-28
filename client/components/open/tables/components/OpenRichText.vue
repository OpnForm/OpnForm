<template>
  <div 
    class="text-sm text-neutral-900"
    v-html="value"
  />
</template>

<script setup>
defineProps({
  value: {
    type: String,
    required: false,
    default: ''
  },
})
</script>


