<template>
  <UPopover
    ref="popover"
    v-model:open="mentionState.open"
    :content="content"
    arrow
    @close="cancel"
  >
    <slot></slot>
    <template #content>
      <div class="p-2 max-h-[300px] flex flex-col">
        <div class="flex items-center border-b -mx-2 px-2">
          <div class="font-semibold w-1/2 mb-2 flex-grow">
            Insert Mention
          </div>
          <input
            v-model="fallbackValue"
            class="p-1 mb-2 text-sm w-1/2 border rounded-md hover:bg-neutral-50"
            placeholder="Fallback value"
          >
        </div>
        <div class="overflow-scroll pt-2">
          <!-- Form Fields -->
          <div class="w-full max-w-xs mb-2">
            <div class="text-sm text-neutral-500 mb-1">
              Select a field
            </div>
            <div class="space-y-1">
              <div
                v-for="field in filteredMentions"
                :key="field.id"
                class="flex items-center p-2 rounded-md cursor-pointer hover:bg-neutral-100"
                :class="{ 'bg-blue-50 border border-blue-100 inset-0': selectedField?.id === field.id, 'border border-transparent': selectedField?.id !== field.id }"
                @click="selectField(field)"
                @dblclick="selectField(field, true)"
              >
                <BlockTypeIcon
                  :type="field.type"
                  class="mr-2"
                />
                <p class="text-sm text-neutral-700 truncate">
                  {{ field.name }}
                </p>
              </div>
            </div>
          </div>
          
          <!-- Computed Variables -->
          <div v-if="computedVariables && computedVariables.length > 0" class="w-full max-w-xs mb-2 border-t pt-2 mt-2">
            <div class="text-sm text-neutral-500 mb-1">
              Variables
            </div>
            <div class="space-y-1">
              <div
                v-for="variable in computedVariables"
                :key="variable.id"
                class="flex items-center p-2 rounded-md cursor-pointer hover:bg-neutral-100"
                :class="{ 'bg-purple-50 border border-purple-100 inset-0': selectedField?.id === variable.id, 'border border-transparent': selectedField?.id !== variable.id }"
                @click="selectField(variable)"
                @dblclick="selectField(variable, true)"
              >
                <Icon
                  name="i-heroicons-variable"
                  class="h-4 w-4 text-purple-500 mr-2"
                />
                <p class="text-sm text-neutral-700 truncate">
                  {{ variable.name }}
                </p>
              </div>
            </div>
          </div>
        </div>
          
        <div class="flex border-t pt-2 -mx-2 px-2 justify-end space-x-2">
          <UButton
            size="sm"
            color="primary"
            class="px-6"
            :disabled="!selectedField"
            @click="insertMention" 
          >
            Insert
          </UButton>
          <UButton
            size="sm"
            color="neutral"
            variant="outline"
            @click="cancel"
          >
            Cancel
          </UButton>
        </div>
      </div>
    </template>
  </UPopover>
</template>
    
<script setup>
import BlockTypeIcon from '~/components/open/forms/components/BlockTypeIcon.vue'
import blocksTypes from '~/data/blocks_types.json'

const props = defineProps({
  mentionState: {
    type: Object,
    required: true
  },
  mentions: {
    type: Array,
    required: true
  },
  computedVariables: {
    type: Array,
    default: () => []
  },
  content: {
    type: Object,
    default: () => ({ side: 'bottom', align: 'end' })
  },
})

defineShortcuts({
  escape: () => {
    props.mentionState.open = false
  }
})

const selectedField = ref(null)
const fallbackValue = ref('')

const filteredMentions = computed(() => {
  return props.mentions.filter(mention => blocksTypes[mention.type]?.is_input ?? false)
})

function selectField(field, insert = false) {
  selectedField.value = {...field}
  if (insert) {
    insertMention()
  }
}

watch(() => props.mentionState.open, (newValue) => {
  if (newValue) {
    selectedField.value = null
    fallbackValue.value = ''
  }
})

const insertMention = () => {
  if (selectedField.value && props.mentionState.onInsert) {
    props.mentionState.onInsert({
      field: selectedField.value,
      fallback: fallbackValue.value
    })
  }
}

const cancel = () => {
  if (props.mentionState.onCancel) {
    props.mentionState.onCancel()
  }
}
</script>