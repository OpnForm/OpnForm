<template>
  <UBadge
    v-if="planLabel"
    :color="badgeColor"
    variant="subtle"
    class="shrink-0 rounded-full"
  >
    {{ planLabel }}
  </UBadge>
</template>

<script setup>
const props = defineProps({
  plan: {
    type: String,
    default: null,
  },
})

const planKey = computed(() => {
  if (!props.plan) return null

  return String(props.plan).toLowerCase().replace(/\s+/g, '_')
})

const planLabel = computed(() => props.plan)

const badgeColor = computed(() => {
  switch (planKey.value) {
    case 'free':
      return 'success'
    case 'business':
      return 'warning'
    case 'enterprise':
      return 'secondary'
    case 'self_hosted':
      return 'success'
    case 'pro':
    default:
      return 'primary'
  }
})
</script>
