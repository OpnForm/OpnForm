<template>
  <UModal
    v-model:open="isOpen"
    title="Some fields were removed"
    description="These fields no longer exist in your form. Their PDF zones have been removed. Add any replacements to the template."
    :close="false"
    :ui="{ content: 'sm:max-w-lg' }"
  >
    <template #title>
      <div class="flex items-center gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
          <Icon name="i-heroicons-document-minus" class="size-5" aria-hidden="true" />
        </span>
        <span>Some fields were removed</span>
      </div>
    </template>

    <template #body>
      <ul class="max-h-64 divide-y divide-neutral-100 overflow-y-auto rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-700">
        <li
          v-for="(zone, index) in zones"
          :key="zone.id"
          class="flex items-center gap-3 px-4 py-3 text-sm"
        >
          <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-neutral-100 dark:bg-neutral-700">
            <UIcon name="i-heroicons-at-symbol" class="size-4 text-neutral-500 dark:text-neutral-300" aria-hidden="true" />
          </span>
          <span class="min-w-0 flex-1 break-words font-medium text-neutral-900 dark:text-white">
            {{ getZoneLabel(zone, index) }}
          </span>
          <span class="shrink-0 text-xs text-neutral-500 dark:text-neutral-400">
            Page {{ zone.page }}
          </span>
        </li>
      </ul>
      <p class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
        Save the template to keep these changes.
      </p>
    </template>

    <template #footer>
      <div class="flex w-full justify-end">
        <UButton color="primary" @click="close">
          Got it
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  zones: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:open'])

const isOpen = computed({
  get: () => props.open,
  set: value => emit('update:open', value),
})

const getZoneLabel = (zone, index) => {
  const label = [zone.field_name, zone.field_label, zone.label]
    .find(value => typeof value === 'string' && value.trim())
  return label?.trim() || `Deleted field ${index + 1}`
}

const close = () => {
  isOpen.value = false
}
</script>
