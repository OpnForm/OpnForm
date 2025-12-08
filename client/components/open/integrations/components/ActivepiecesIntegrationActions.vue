<template>
  <div class="flex flex-1 items-center">
    <div
      v-if="integration && providerUrl"
      class="hidden md:block space-y-2"
    >
      <UButton
        color="neutral"
        variant="ghost"
        size="sm"
        :href="`${providerUrl}?utm_source=opnform-edit`"
        target="_blank"
        external
      >
        Edit Workflow
      </UButton>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  integration: {
    type: Object,
    required: true,
  },
  form: {
    type: Object,
    required: true,
  }
})

const providerUrl = computed(() => {
  return props.integration?.data?.provider_url || null
})
</script>

