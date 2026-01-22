<template>
  <div class="flex flex-1 items-center">
    <div
      v-if="integration"
      class="hidden md:block space-y-1"
    >
      <UBadge
        :label="mentionAsText(integration.data.filename_pattern)"
        color="neutral"
        variant="subtle"
        size="sm"
        class="max-w-[300px] block truncate"
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
