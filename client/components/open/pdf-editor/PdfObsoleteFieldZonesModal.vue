<template>
  <UModal v-model:open="isOpen" :ui="{ content: 'sm:max-w-lg' }">
    <template #header>
      <div>
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">
          Obsolete PDF field mappings
        </h2>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
          These zones have been removed because their mapped fields were deleted from this form. Add any needed fields to the PDF template again.
        </p>
      </div>
    </template>

    <template #body>
      <ul class="space-y-2">
        <li
          v-for="zone in zones"
          :key="zone.id"
          class="rounded-md border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700"
        >
          <p class="font-medium text-neutral-900 dark:text-white">
            {{ getZoneLabel(zone) }}
          </p>
          <p class="mt-0.5 text-neutral-500 dark:text-neutral-400">
            Page {{ zone.page }}
          </p>
        </li>
      </ul>
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

const getZoneLabel = zone => zone.field_name || zone.field_label || zone.label || zone.field_id

const close = () => {
  isOpen.value = false
}
</script>
