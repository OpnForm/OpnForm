<template>
  <div class="p-4">
    <div :class="innerClasses">
      <slot />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  fluid: { type: Boolean, default: false },
  spacing: {
    type: String,
    default: 'md',
    validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
  },
})

const spacingMap = {
  none: '',
  sm: 'space-y-4',
  md: 'space-y-6',
  lg: 'space-y-8',
}

const innerClasses = computed(() => [
  'w-full',
  !props.fluid && 'max-w-4xl mx-auto',
  spacingMap[props.spacing],
])
</script>
