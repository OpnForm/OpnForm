<template>
  <form class="flex flex-col">
    <slot />
  </form>
</template>

<script setup>
/**
 * Used to pass props to children input components
 */

const props = defineProps({
  theme: { type: String, default: 'default' },
  size: { type: String, default: "md" },
  borderRadius: { type: String, default: "small" },
  form: { type: Object, default: null }
})

// Provide individual theme props for tailwind-variants approach
provide('formTheme', computed(() => props.theme))
provide('formSize', computed(() => props.size))  
provide('formBorderRadius', computed(() => props.borderRadius))
provide('form', computed(() => props.form))
</script>
