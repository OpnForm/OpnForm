<template>
  <UButtonGroup
    size="sm"
    orientation="horizontal"
    class="shadow-none"
  >
    <UTooltip
      text="Undo"
      :kbds="['meta','Z']"
      :content="{ side: 'left' }"
      arrow
    >
      <UButton
        :disabled="!canUndo"
        color="neutral"
        variant="outline"
        icon="i-material-symbols-undo"
        class="disabled:text-neutral-500 shadow-none"
        @click="undo"
      />
    </UTooltip>
    <UTooltip
      text="Redo"
      :kbds="['meta','Shift','Z']"
      :content="{ side: 'right' }"
      arrow
    >
      <UButton
        :disabled="!canRedo"
        icon="i-material-symbols-redo"
        color="neutral"
        variant="outline"
        class="disabled:text-neutral-500 shadow-none"
        @click="redo"
      />
    </UTooltip>
  </UButtonGroup>
</template>

<script setup>
const props = defineProps({
  editor: { type: String, default: 'form' }
})

let workingStore = useWorkingFormStore()
if (props.editor === 'view') {
  workingStore = useWorkingViewStore()
}

const { undo, redo, clearHistory } = workingStore
const { canUndo, canRedo } = storeToRefs(workingStore)

defineShortcuts({
  meta_z: {
    whenever: [canUndo],
    handler: () => {
      undo()
    }
  },
  meta_shift_z: {
    whenever: [canRedo],
    handler: () => {
      redo()
    }
  }
})
onMounted(() => {
  setTimeout(() => { clearHistory() }, 500)
})
</script>
