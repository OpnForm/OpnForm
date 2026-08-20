<template>
  <div class="group">
    <div class="flex items-center gap-1 p-2 rounded-md hover:bg-neutral-50 transition-colors">
      <div class="w-4 h-4 flex items-center justify-center opacity-60 group-hover:opacity-100 transition-opacity">
        <UIcon name="clarity:drag-handle-line" class="h-6 w-6 -ml-1 shrink-0 text-neutral-400" />
      </div>

      <div class="w-4 h-4 flex items-center justify-center">
        <BlockTypeIcon
          v-if="column.type"
          :type="column.type"
          bg-class="bg-transparent"
          text-class="text-neutral-600"
          class="flex-shrink-0"
        />
      </div>

      <UTooltip :text="technicalName || displayName" :content="{ align: 'start' }">
        <div class="flex-1 min-w-0">
          <div class="text-sm truncate">{{ displayName }}</div>
          <div v-if="technicalName" class="text-xs text-neutral-500 truncate">
            {{ technicalName }}
          </div>
        </div>
      </UTooltip>

      <UTooltip v-if="column.isRemoved" text="Column was removed from form" :content="{ align: 'end' }">
        <UIcon name="i-heroicons-trash" class="w-3 h-3 text-neutral-400" />
      </UTooltip>

      <div class="flex items-center gap-1">
        <UTooltip :text="pinLabel">
          <UButton
            size="xs"
            :variant="preference?.pinned ? 'soft' : 'ghost'"
            icon="i-ic-baseline-push-pin"
            :color="preference?.pinned ? 'primary' : 'neutral'"
            :aria-label="`${pinLabel}: ${displayName}`"
            @click.prevent="tableState.toggleColumnPin(column.id)"
          />
        </UTooltip>

        <UTooltip :text="wrapLabel">
          <UButton
            size="xs"
            :variant="preference?.wrapped ? 'soft' : 'ghost'"
            icon="i-ic-baseline-wrap-text"
            :color="preference?.wrapped ? 'primary' : 'neutral'"
            :aria-label="`${wrapLabel}: ${displayName}`"
            @click.prevent="tableState.toggleColumnWrapping(column.id)"
          />
        </UTooltip>

        <UTooltip :text="visibilityLabel">
          <UButton
            size="xs"
            variant="ghost"
            color="neutral"
            :icon="visible ? 'i-heroicons-eye-solid' : 'i-heroicons-eye-slash-solid'"
            :aria-label="`${visibilityLabel}: ${displayName}`"
            @click.prevent="tableState.toggleColumnVisibility(column.id)"
          />
        </UTooltip>
      </div>
    </div>
  </div>
</template>

<script setup>
import BlockTypeIcon from '~/components/open/forms/components/BlockTypeIcon.vue'

const props = defineProps({
  column: {
    type: Object,
    required: true,
  },
  tableState: {
    type: Object,
    required: true,
  },
  preference: {
    type: Object,
    default: () => ({}),
  },
  visible: {
    type: Boolean,
    default: true,
  },
  displayName: {
    type: String,
    required: true,
  },
  technicalName: {
    type: String,
    default: '',
  },
})

const pinLabel = computed(() => props.preference?.pinned === 'left' ? 'Unpin column' : 'Pin column to left')
const wrapLabel = computed(() => props.preference?.wrapped ? 'Disable text wrapping' : 'Enable text wrapping')
const visibilityLabel = computed(() => props.visible ? 'Hide column' : 'Show column')
</script>

<style scoped>
.group {
  cursor: grab;
}

.group:active {
  cursor: grabbing;
}
</style>
