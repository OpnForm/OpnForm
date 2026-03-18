<template>
  <VTransition name="fade">
    <div v-if="loading" :key="'skeleton'">
      <slot name="skeleton" />
    </div>
    <div v-else :key="'content'">
      <slot />
    </div>
  </VTransition>
</template>

<script setup>
defineProps({
  loading: { type: Boolean, default: false },
})
</script>
