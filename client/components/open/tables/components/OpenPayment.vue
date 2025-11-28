<template>
  <span
    v-if="value"
    class="-mb-2"
  >
    <UButton
      :to="paymentUrl"
      size="xs"
      variant="soft"
      icon="i-heroicons-credit-card-20-solid"
      trailing-icon="i-heroicons-arrow-top-right-on-square-20-solid"
      label="Payment"
      target="_blank"
    />
  </span>
</template>

<script setup>
const props = defineProps({
  value: {
    type: Object,
  },
})

const paymentUrl = computed(() => {
  if (!props.value) return null
  const isLocal = useRuntimeConfig().public.env === 'local'
  return `https://dashboard.stripe.com${isLocal ? '/test' : ''}/payments/${props.value}`
})
</script>
