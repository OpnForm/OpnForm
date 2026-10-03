<template>
  <VueDraggable
    :model-value="fields"
    group="form-elements"
    item-key="id"
    class="grid grid-cols-12 relative transition-all w-full"
    :class="draggingNewBlock ? 'rounded-md bg-blue-50 dark:bg-neutral-800' : ''"
    ghost-class="ghost-item"
    filter=".not-draggable"
    :animation="200"
    @add="handleDragAdd"
    @update="handleDragUpdate"
  >
    <div
      v-for="element in fields"
      :key="element.id"
      :class="getFieldWidthClasses(element.width)"
    >
      <VTransition name="fadeHeight">
        <OpenFormField
          :field="element"
          :form-manager="formManager"
        />
      </VTransition>
    </div>
  </VueDraggable>
</template>

<script setup>
import { VueDraggable } from 'vue-draggable-plus'
import OpenFormField from '../OpenFormField.vue'

const props = defineProps({
  fields: { type: Array, required: true },
  formManager: { type: Object, required: true },
})

const workingFormStore = useWorkingFormStore()
const draggingNewBlock = computed(() => workingFormStore.draggingNewBlock)

function getAbsoluteIndex(relativeIndex) {
  return props.formManager.structure.value.getTargetDropIndex(
    relativeIndex,
    props.formManager.state.currentPage,
  )
}

function handleDragAdd(event) {
  const targetIndex = getAbsoluteIndex(event.newIndex)
  workingFormStore.addBlock(event?.clonedData, targetIndex, false)
}

function handleDragUpdate(event) {
  const oldTargetIndex = getAbsoluteIndex(event.oldIndex)
  const newTargetIndex = getAbsoluteIndex(event.newIndex)
  if (oldTargetIndex !== newTargetIndex) {
    workingFormStore.moveField(oldTargetIndex, newTargetIndex)
  }
}

function getFieldWidthClasses(width) {
  return {
    '1/2': 'sm:col-span-6 col-span-full',
    '1/3': 'sm:col-span-4 col-span-full',
    '2/3': 'sm:col-span-8 col-span-full',
    '1/4': 'sm:col-span-3 col-span-full',
    '3/4': 'sm:col-span-9 col-span-full',
  }[width] || 'col-span-full'
}
</script>

<style lang="scss" scoped>
.ghost-item {
  @apply bg-blue-100 dark:bg-blue-900 rounded-md;
}
</style>
