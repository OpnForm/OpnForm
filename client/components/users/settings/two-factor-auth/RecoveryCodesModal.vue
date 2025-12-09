<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-md' }"
  >
    <template #header>
      <h2 class="text-lg font-semibold">Recovery Codes</h2>
    </template>

    <template #body>
      <div class="space-y-4">
        <!-- Recovery codes display -->
        <div v-if="codes.length > 0" class="space-y-4">
          <UAlert
            color="warning"
            variant="subtle"
            description="These recovery codes will only be shown once. Please save them in a safe place. You can use them to access your account if you lose access to your authenticator device."
          />

          <div class="space-y-2">
            <div
              v-for="(codeItem, index) in codes"
              :key="index"
              class="flex items-center justify-center p-2 bg-neutral-50 rounded font-mono text-sm text-center"
            >
              <span>{{ typeof codeItem === 'string' ? codeItem : codeItem.code }}</span>
              <span
                v-if="typeof codeItem === 'object' && codeItem.used_at"
                class="text-xs text-neutral-500 ml-2 font-sans"
              >
                Used {{ formatDate(codeItem.used_at) }}
              </span>
            </div>
          </div>

          <div class="flex gap-2">
            <UButton
              block
              color="neutral"
              variant="outline"
              @click="handleCopy"
            >
              Copy All Codes
            </UButton>
            <UButton
              block
              color="primary"
              @click="handleClose"
            >
              I've Saved These Codes
            </UButton>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

<script setup>
const props = defineProps({
  show: { type: Boolean, required: true },
  codes: { type: Array, default: () => [] },
  justRegenerated: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'copy'])

const isOpen = computed({
  get: () => props.show,
  set: (value) => {
    if (!value) emit('close')
  }
})

const handleCopy = () => {
  emit('copy')
}

const handleClose = () => {
  emit('close')
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

</script>

