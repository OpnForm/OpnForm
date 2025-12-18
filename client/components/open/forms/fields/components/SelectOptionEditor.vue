<template>
  <div
    v-if="['select', 'multi_select'].includes(field.type)"
    class="px-4"
  >
    <EditorSectionHeader
      icon="i-heroicons-chevron-up-down-20-solid"
      title="Select Options"
    />

    <div class="space-y-4">
      <!-- Display Settings -->
      <div class="space-y-3">
        <!-- Option Display Mode -->
        <FlatSelectInput
          v-model="field.option_display_mode"
          name="option_display_mode"
          :options="[
            { name: 'Text only', value: 'text_only' },
            { name: 'Text & Image', value: 'text_and_image' },
            { name: 'Image only', value: 'image_only' }
          ]"
          label="Option display style"
        />

        <!-- Image Size (when images enabled) -->
        <OptionSelectorInput
          v-if="hasImages"
          seamless
          label="Image Size"
          v-model="field.option_image_size"
          name="option_image_size"
          :options="[
            { name: 'sm', label:'Small'},
            { name: 'md', label:'Medium' },
            { name: 'lg', label:'Large' },
          ]"
          :multiple="false"
          :columns="3"
          class="mt-4"
        />
      </div>

      <!-- Options list editor -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Options
        </label>
        
        <VueDraggable
          v-model="options"
          item-key="id"
          handle=".drag-handle"
          :ghost-class="['opacity-50', 'bg-primary-50', 'dark:bg-primary-900/20', 'rounded-lg']"
          :chosen-class="['bg-primary-100', 'dark:bg-primary-900/30', 'rounded-lg']"
          :animation="200"
          class="space-y-1"
        >
          <template #default>
            <div
              v-for="(option, index) in options"
              :key="option.id || index"
              class="flex items-center gap-1 p-1 bg-neutral-50 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700"
            >
              <!-- Drag handle -->
              <div class="drag-handle flex items-center cursor-grab active:cursor-grabbing text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                <Icon name="heroicons:bars-3" class="h-3 w-3" />
              </div>

              <!-- Text input -->
              <TextInput
                :model-value="option.name"
                :name="`option_${index}_name`"
                wrapper-class="mb-0 flex-1 min-w-0"
                placeholder="Option Text"
                @update:model-value="updateOptionName(index, $event)"
              />

              <!-- Image input (only when images enabled) -->
              <div v-if="hasImages" class="shrink-0 w-20">
                <ImageInput
                  :model-value="option.image"
                  :name="`option_${index}_image`"
                  wrapper-class="mb-0"
                  @update:model-value="updateOptionImage(index, $event)"
                />
              </div>

              <!-- Delete button -->
              <UButton
                icon="i-heroicons-trash"
                color="error"
                variant="outline"
                size="sm"
                class="shrink-0 mt-1"
                :disabled="options.length <= 1"
                title="Remove option"
                @click="removeOption(index)"
              />
            </div>
          </template>
        </VueDraggable>

        <!-- Add option button -->
        <UButton
          icon="i-heroicons-plus"
          label="Add Option"
          color="neutral"
          variant="outline"
          size="sm"
          block
          @click="addOption"
        />
      </div>

      <!-- Additional Options -->
      <div class="space-y-3">
        <!-- Use dropdown instead (focused mode only) -->
        <toggle-switch-input
          v-if="isFocused"
          :model-value="field.use_focused_selector === false"
          label="Use dropdown instead"
          help="Use classic dropdown instead of focused selector with keyboard shortcuts"
          @update:model-value="onUseDropdownChange"
        />

        <!-- Allow creation (not in focused selector mode, not with images) -->
        <toggle-switch-input
          v-if="!isFocusedSelectorActive && !hasImages"
          :form="field"
          name="allow_creation"
          label="Allow respondent to create new options"
          @update:model-value="onAllowCreationChange"
        />

        <!-- Use radio buttons -->
        <toggle-switch-input
          v-if="!isFocusedSelectorActive"
          :form="field"
          name="without_dropdown"
          label="Use radio buttons"
          @update:model-value="onWithoutDropdownChange"
        />

        <!-- Randomize options -->
        <toggle-switch-input
          :form="field"
          name="shuffle_options"
          label="Randomize options order"
        />

        <!-- Min/Max Selection Constraints for multi_select only -->
        <template v-if="field.type === 'multi_select'">
          <div class="flex gap-1">
            <text-input
              name="min_selection"
              native-type="number"
              :min="0"
              class="flex-1"
              :form="field"
              label="Min. required"
              placeholder="1"
              @update:model-value="onMinSelectionChange"
            />
            <text-input
              name="max_selection"
              native-type="number"
              :min="1"
              class="flex-1"
              :form="field"
              label="Max. allowed"
              placeholder="2"
              @update:model-value="onMaxSelectionChange"
            />
            <UButton
              icon="i-heroicons-backspace"
              color="neutral"
              variant="outline"
              class="self-end mb-1"
              title="Clear both values"
              @click="clearMinMaxSelection"
            />
          </div>
          <InputHelp help="Set min/max options allowed, or leave empty for unlimited. Save form to test changes." />
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { VueDraggable } from 'vue-draggable-plus'
import EditorSectionHeader from '~/components/open/forms/components/form-components/EditorSectionHeader.vue'
import ImageInput from '~/components/forms/heavy/ImageInput.vue'

const props = defineProps({
  field: {
    type: Object,
    required: true
  },
  form: {
    type: Object,
    required: false
  }
})

// Get options directly from field
const options = computed({
  get: () => props.field[props.field.type]?.options || [],
  set: (val) => {
    props.field[props.field.type] = { options: val }
  }
})

// Computed properties
const isFocused = computed(() => props.form?.presentation_style === 'focused')

const isFocusedSelectorActive = computed(() => {
  return isFocused.value && props.field.use_focused_selector !== false
})

const hasImages = computed(() => ['text_and_image', 'image_only'].includes(props.field.option_display_mode))

// Option management functions
function addOption() {
  const newOption = {
    id: `option_${Date.now()}`,
    name: '',
    image: null
  }
  options.value = [...options.value, newOption]
}

function removeOption(index) {
  if (options.value.length <= 1) return
  const newOptions = [...options.value]
  newOptions.splice(index, 1)
  options.value = newOptions
}

function updateOptionName(index, value) {
  const newOptions = [...options.value]
  newOptions[index] = {
    ...newOptions[index],
    name: value
  }
  options.value = newOptions
}

function updateOptionImage(index, url) {
  const newOptions = [...options.value]
  newOptions[index] = {
    ...newOptions[index],
    image: url
  }
  options.value = newOptions
}

// Field settings handlers
function onUseDropdownChange(val) {
  props.field.use_focused_selector = !val
  if (props.field.use_focused_selector) {
    props.field.without_dropdown = false
    props.field.allow_creation = false
  }
}

function onAllowCreationChange(val) {
  props.field.allow_creation = val
  if (props.field.allow_creation) {
    props.field.without_dropdown = false
  }
}

function onWithoutDropdownChange(val) {
  props.field.without_dropdown = val
  if (props.field.without_dropdown) {
    props.field.allow_creation = false
    props.field.use_focused_selector = false
  }
}

function onMinSelectionChange(val) {
  props.field.min_selection = val ? parseInt(val) : null
}

function onMaxSelectionChange(val) {
  props.field.max_selection = val ? parseInt(val) : null
}

function clearMinMaxSelection() {
  props.field.min_selection = null
  props.field.max_selection = null
}
</script>
