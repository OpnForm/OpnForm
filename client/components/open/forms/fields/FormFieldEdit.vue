<template>
  <div 
    :class="{ 'sidebar-bounce': sidebarBounce }"
    class="sidebar-container"
  >
    <div class="p-2 border-b sticky top-0 z-20 bg-white">
      <UButton
        v-if="!field"
        size="sm"
        color="neutral"
        icon="i-heroicons-x-mark-20-solid"
        variant="ghost"
        @click="closeSidebar"
      />
      <template v-else>
        <div class="flex">
          <UButton
            size="sm"
            color="neutral"
            icon="i-heroicons-x-mark-20-solid"
            variant="ghost"
            @click="closeSidebar"
          />
          <div class="ml-2 flex flex-grow items-center space-between min-w-0 gap-x-3">
            <div class="flex-grow" />
            <BlockTypeIcon
              :type="field.type"
            />

            <p
              v-if="blocksTypes[field.type]"
              class="text-sm text-neutral-500"
            >
              {{ blocksTypes[field.type].title }}
            </p>
            
            <UDropdownMenu
              :items="dropdownItems"
              :content="{ side: 'bottom', align: 'start' }"
              arrow
            >
              <UButton
                color="neutral"
                variant="outline"
                icon="i-heroicons-ellipsis-vertical"
              />
            </UDropdownMenu>
          </div>
        </div>
      </template>
    </div>

    <template v-if="field">
      <div class="bg-neutral-100 border-b">
        <UTabs
          v-model="activeTab"
          :items="tabItems"
          color="primary"
          :content="false"
          class="w-full"
        />
      </div>
      <div v-if="activeTab === 'options'">
        <FieldOptions
          v-if="!isBlockField"
          :form="form"
          :field="field"
        />
        <BlockOptions
          v-else
          :form="form"
          :field="field"
        />
      </div>
      <div v-else-if="activeTab === 'logic'">
        <FormBlockLogicEditor
          class="py-2 px-4"
          :form="form"
          :field="field"
        />
      </div>
      <div v-else-if="activeTab === 'validation'">
        <custom-field-validation
          class="py-2 px-4"
          :form="form"
          :field="field"
        />
      </div>
    </template>
    <div
      v-else
      class="text-center p-10 text-sm text-neutral-500"
    >
      Click on field to edit it.
    </div>
  </div>
</template>


<script setup>
import { storeToRefs } from 'pinia'
import FieldOptions from './components/FieldOptions.vue'
import BlockOptions from './components/BlockOptions.vue'
import BlockTypeIcon from '../components/BlockTypeIcon.vue'
import blocksTypes from '~/data/blocks_types.json'
import FormBlockLogicEditor from '../components/form-logic-components/FormBlockLogicEditor.vue'
import CustomFieldValidation from '../components/CustomFieldValidation.vue'

const workingFormStore = useWorkingFormStore()
const { content: form, sidebarBounce } = storeToRefs(workingFormStore)

const selectedFieldIndex = computed(() => workingFormStore.selectedFieldIndex)

const field = computed(() => {
  return form.value && selectedFieldIndex.value !== null
    ? form.value.properties[selectedFieldIndex.value]
    : null
})

// Only set the page once when the component is mounted
// This prevents page jumps when editing field properties
onMounted(() => {
  if (selectedFieldIndex.value !== null) {
    if (workingFormStore.structureService) {
      workingFormStore.structureService.setPageForField(selectedFieldIndex.value)
    }
  }
})

const isBlockField = computed(() => {
  return field.value && field.value.type && typeof field.value.type === 'string' && field.value.type.startsWith('nf')
})

const typeCanBeChanged = computed(() => {
  const textualTypes = ["text", "rich_text", "url", "email", "phone_number", "number"]
  const selectionTypes = ["select", "multi_select"]
  const scaleTypes = ["rating", "scale", "slider"]
  const booleanTypes = ["checkbox"]
  return [
    ...textualTypes,
    ...selectionTypes,
    ...scaleTypes,
    ...booleanTypes,
  ].includes(field.value.type)
})

// Composable for field type changing logic
const useFieldTypeChange = () => {

  const onChangeType = (newType) => {
    if (["select", "multi_select"].includes(field.value.type)) {
      field.value[newType] = field.value[field.value.type] // Set new options with new type
      delete field.value[field.value.type] // remove old type options
    }

    // Preserve/downgrade content when converting between text and rich_text
    if ((field.value.type === 'text' && newType === 'rich_text') || (field.value.type === 'rich_text' && newType === 'text')) {
      // keep existing value in place; renderer handles component mapping
    }

    field.value.type = newType
  }

  const getChangeTypeOptions = (currentType) => {
    const textualTypes = ["text", "rich_text", "url", "email", "phone_number", "number"]
    const selectionTypes = ["select", "multi_select"]
    const scaleTypes = ["rating", "scale", "slider"]
    const booleanTypes = ["checkbox"]

    let candidateTypes = []

    if (textualTypes.includes(currentType)) {
      candidateTypes = [...textualTypes, ...booleanTypes]
    } else if (selectionTypes.includes(currentType)) {
      candidateTypes = [...selectionTypes]
    } else if (scaleTypes.includes(currentType)) {
      candidateTypes = [...scaleTypes]
    } else if (booleanTypes.includes(currentType)) {
      candidateTypes = [...textualTypes, ...booleanTypes]
    }

    return candidateTypes
      .filter((type) => type !== currentType)
      .map((type) => {
        const meta = blocksTypes[type] || {}
        return {
          label: meta.title || type,
          value: type,
          icon: meta.icon || undefined,
          onClick: () => onChangeType(type)
        }
      })
  }

  return {
    getChangeTypeOptions
  }
}

const { getChangeTypeOptions } = useFieldTypeChange()

function removeBlock() {
  workingFormStore.removeField(field.value)
}

function closeSidebar() {
  // Explicitly clear the selected field index to prevent issues with subsequent block additions
  workingFormStore.selectedFieldIndex = null
  workingFormStore.closeEditFieldSidebar()
}

const dropdownItems = computed(() => {
  const baseItems = [
    [{
      label: 'Copy field ID',
      icon: 'i-heroicons-clipboard-20-solid',
      onClick: () => {
        navigator.clipboard.writeText(field.value.id)
        useAlert().success('Field ID copied to clipboard')
      }
    }],
    [{
      label: 'Duplicate',
      icon: 'i-heroicons-document-duplicate-20-solid',
      kbds: ['meta', 'd'],
      onClick: () => workingFormStore.duplicateField(field.value)
    }]
  ]

  // Add change type option with nested menu if type can be changed
  if (typeCanBeChanged.value && !isBlockField.value) {
    const changeTypeOptions = getChangeTypeOptions(field.value.type)
    if (changeTypeOptions.length > 0) {
      baseItems.push([{
        label: 'Change type',
        icon: 'i-heroicons-arrows-right-left-20-solid',
        children: [changeTypeOptions]
      }])
    }
  }

  // Add remove option
  baseItems.push([{
    label: 'Remove',
    icon: 'i-heroicons-trash-20-solid',
    color: 'error',
    kbds: ['meta', 'backspace'],
    onClick: removeBlock
  }])

  return baseItems
})
defineShortcuts(extractShortcuts(dropdownItems.value))

const activeTab = ref('options')

const tabItems = computed(() => {
  const commonTabs = [
    { label: 'Options', value: 'options' },
    { label: 'Logic', value: 'logic' },
  ]

  if (isBlockField.value) {
    return commonTabs
  } else {
    return [
      ...commonTabs,
      { label: 'Validation', value: 'validation' },
    ]
  }
})

</script>

<style scoped>
.sidebar-container {
  transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.sidebar-bounce {
  animation: bounce-left 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes bounce-left {
  0% {
    transform: translateX(0);
  }
  20% {
    transform: translateX(-6px);
  }
  40% {
    transform: translateX(0);
  }
  60% {
    transform: translateX(-3px);
  }
  80% {
    transform: translateX(0);
  }
  90% {
    transform: translateX(-1px);
  }
  100% {
    transform: translateX(0);
  }
}
</style>
