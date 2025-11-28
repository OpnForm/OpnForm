<template>
  <div class="flex flex-1 items-center gap-2">
    <div
      v-if="integration.provider"
      class="hidden md:flex items-center"
    >
      <UBadge
        :label="mentionAsText(integration.provider.email)"
        color="neutral"
        variant="subtle"
        size="sm"
        class="max-w-[300px] truncate"
      />
    </div>

    <div
      v-if="integration.data"
      class="ml-auto flex items-center"
    >
      <UButton
        :to="integration.data.url"
        target="_blank"
        color="neutral"
        size="sm"
        variant="outline"
        icon="mdi:google-spreadsheet"
        trailing-icon="heroicons:arrow-top-right-on-square-20-solid"
        label="Open"
      />
    </div>
  </div>
</template>

<script setup>
import { mentionAsText } from '~/lib/utils.js'

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

const { invalidateIntegrations } = useFormIntegrations()
let interval = null

onMounted(() => {
  if (!props.integration.data || props.integration.data.length === 0) {
    interval = setInterval(() => invalidateIntegrations(props.form.id), 3000)
    setTimeout(() => { clearInterval(interval) }, 30000)
  }
})

onBeforeUnmount(() => {
  if (interval) {
    clearInterval(interval)
  }
})
</script>
