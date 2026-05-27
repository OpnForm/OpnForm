<template>
  <div :class="panelClasses">
    <slot />
  </div>
</template>

<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'subtle'].includes(v),
  },
  padding: {
    type: String,
    default: 'md',
    validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
  },
})

const paddingMap = {
  none: '',
  sm: 'p-4',
  md: 'p-5',
  lg: 'p-6',
}

const variantMap = {
  default: 'border-neutral-300 shadow-sm bg-white',
  subtle: 'border-neutral-200 bg-neutral-50/50',
}

const panelClasses = computed(() => [
  'rounded-lg border',
  paddingMap[props.padding],
  variantMap[props.variant],
])
</script>
