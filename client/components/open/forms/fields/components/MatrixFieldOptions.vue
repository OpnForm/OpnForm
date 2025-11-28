<template>
  <div
    v-if="localField.type === 'matrix'"
    class="px-4"
  >
    <EditorSectionHeader
      icon="i-heroicons-table-cells-20-solid"
      title="Matrix"
    />

    <div class="grid grid-cols-2 gap-4">
      <div class="">
        <div
          v-for="(row, i) in localField.rows"
          :key="i"
          class="flex items-center space-x-2"
        >
          <text-input
            v-model="localField.rows[i]"
            name="rows"
            wrapper-class="mb-1"
            @update:model-value="updateField"
          />
          <button @click="removeMatrixRow(i)">
            <Icon
              name="heroicons:trash"
              class="text-neutral-300 w-4 h-4 mb-2"
            />
          </button>
        </div>
        <UButton
          size="xs"
          color="neutral"
          variant="soft"
          icon="i-heroicons-plus"
          @click="addMatrixRow"
        >
          Add row
        </UButton>
      </div>
      <div class="">
        <div
          v-for="(column, i) in localField.columns"
          :key="i"
          class="flex items-center space-x-2"
        >
          <text-input
            v-model="localField.columns[i]"
            wrapper-class="mb-1"
            @update:model-value="updateField"
          />
          <button @click="removeMatrixColumn(i)">
            <Icon
              name="heroicons:trash"
              class="text-neutral-300 w-4 h-4 mb-2"
            />
          </button>
        </div>
        <UButton
          size="xs"
          color="neutral"
          variant="soft"
          icon="i-heroicons-plus"
          @click="addMatrixColumn"
        >
          Add column
        </UButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import EditorSectionHeader from '~/components/open/forms/components/form-components/EditorSectionHeader.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  },
})

const emit = defineEmits(['update:modelValue'])

const localField = ref({ ...props.modelValue })

watch(() => props.modelValue, (newField) => {
  localField.value = { ...newField }
}, { deep: true })

const selectionData = computed(() => {
  return Object.fromEntries(localField.value.rows?.map(row => [row, '']))
})

function updateField() {
  emit('update:modelValue', { ...localField.value })
}

function addMatrixRow() {
  localField.value.rows.push(generateUniqueLabel(localField.value.rows, 'Row'))
  localField.value.selection_data = selectionData.value
  updateField()
}

function removeMatrixRow(index) {
  localField.value.rows.splice(index, 1)
  localField.value.selection_data = selectionData.value
  updateField()
}

function addMatrixColumn() {
  localField.value.columns.push(generateUniqueLabel(localField.value.columns, null))
  localField.value.selection_data = selectionData.value
  updateField()
}

function removeMatrixColumn(index) {
  localField.value.columns.splice(index, 1)
  localField.value.selection_data = selectionData.value
  updateField()
}

function generateUniqueLabel(array, prefix = null) {
  let uniqueNumber = 1 // Start checking from 1
  let label = prefix ? `${prefix} ${uniqueNumber}` : uniqueNumber
  while (array.includes(label)) {
    uniqueNumber++ // Increment if the number is found in the array
    label = prefix ? `${prefix} ${uniqueNumber}` : uniqueNumber
  }
  return label // Return the first unique number found
}
</script>