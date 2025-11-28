<template>
  <div class="my-4">
    <OptionSelectorInput
      :key="selectorKey"
      :model-value="currentStyle"
      name="presentation_style_switch"
      :options="styleOptions"
      :multiple="false"
      :columns="2"
      @update:modelValue="onSelectStyle"
    >
      <template #label>
        <div class="flex items-center gap-0.5">
          <span class="text-neutral-700 font-semibold text-xs">Form Style</span>
          <UButton
            color="neutral"
            variant="ghost"
            size="xs"
            icon="i-heroicons-question-mark-circle"
            class="text-neutral-500"
            @click="openHelpArticle"
          />
        </div>
      </template>
    </OptionSelectorInput>

    <UModal
      v-model:open="showConfirmModal"
      :dismissible="false"
      :title="`Switch to ${pendingStyleLabel} style?`"
      :description="modalDescription"
    >
      <template #body>
        <div class="space-y-2" v-if="removalList.length">
          <div
            v-for="(item, idx) in removalList"
            :key="idx"
            class="flex items-center gap-2 p-2 border border-gray-200 rounded-md bg-gray-50/50"
          >
            <BlockTypeIcon :type="item.type" />
            <div class="flex flex-col">
              <span class="text-sm font-medium text-neutral-700">{{ item.name || item.title }}</span>
              <span class="text-xs text-neutral-500">{{ item.title }}</span>
            </div>
          </div>
        </div>
        <div v-else class="text-sm text-neutral-600">No blocks will be removed.</div>
      </template>
      <template #footer>
        <div class="flex justify-between w-full">
          <UButton color="neutral" variant="outline" label="Cancel" @click="cancelSwitch" />
          <UButton color="primary" :label="`Switch to ${pendingStyleLabel}`" @click="confirmSwitch" />
        </div>
      </template>
    </UModal>
  </div>
  
</template>

<script setup>
import blocksTypes from '~/data/blocks_types.json'
import BlockTypeIcon from '../BlockTypeIcon.vue'
import seedFocusedFirstBlockImage from '~/lib/forms/seed-focused-image'
import { ensureSettingsObject } from '@/composables/forms/initForm'

const workingFormStore = useWorkingFormStore()
const formRef = storeToRefs(workingFormStore).content
const crisp = useCrisp()

const form = computed(() => formRef.value || {})
const currentStyle = computed(() => form.value.presentation_style || 'classic')

const styleOptions = [
  {
    name: 'classic',
    label: 'Classic',
    icon: 'opnform:form-style-classic',
    iconClass: 'w-[91px] h-[65px] rounded shadow *:transition-colors duration-150 ease-out [--icon-fg:#737373] [--icon-muted:#D4D4D4] group-hover:[--icon-fg:#1d4ed8] group-hover:[--icon-muted:#60a5fa] group-aria-selected:[--icon-fg:#1d4ed8] group-aria-selected:[--icon-muted:#60a5fa] group-[aria-selected=true]:[--icon-fg:#1d4ed8] group-[aria-selected=true]:[--icon-muted:#60a5fa]',
    tooltip: 'Classic form: multiple inputs per page, multi-line layout, supports multiple pages and layout blocks.'
  },
  {
    name: 'focused',
    label: 'Focused',
    icon: 'opnform:form-style-focused',
    iconClass: 'w-[91px] h-[65px] rounded shadow *:transition-colors duration-150 ease-out [--icon-fg:#737373] [--icon-muted:#D4D4D4] group-hover:[--icon-fg:#1d4ed8] group-hover:[--icon-muted:#60a5fa] group-aria-selected:[--icon-fg:#1d4ed8] group-aria-selected:[--icon-muted:#60a5fa] group-[aria-selected=true]:[--icon-fg:#1d4ed8] group-[aria-selected=true]:[--icon-muted:#60a5fa]',
    tooltip: 'Typeform-like, one question per step.'
  }
]

const showConfirmModal = ref(false)
const removalList = ref([])
const pendingStyle = ref(null)
const selectorKey = ref(0)

// No local selection state. Display follows the actual form style (currentStyle).

const pendingStyleLabel = computed(() => pendingStyle.value === 'focused' ? 'Focused' : 'Classic')

const modalDescription = computed(() => {
  const count = removalList.value.length
  return count > 0
    ? `Switching to Focused will remove ${count} block${count>1?'s':''} not supported in this mode.`
    : 'Switch to Focused style.'
})

// Use shared helper to seed first block image for focused mode

function onSelectStyle(newVal) {
  if (!form.value) return
  const oldVal = currentStyle.value
  if (newVal === oldVal) return

  // Compute blocks to remove for both directions
  const unsupportedIn = (target) => {
    return Object.values(blocksTypes)
      .filter(def => !(def.available_in || ['classic', 'focused']).includes(target))
      .map(def => def.name)
  }

  const disallowedNames = new Set(unsupportedIn(newVal))
  const props = Array.isArray(form.value.properties) ? form.value.properties : []
  removalList.value = props
    .filter(p => p && disallowedNames.has(p.type))
    .map(p => ({ type: p.type, title: blocksTypes[p.type]?.title || p.type, name: p.name }))

  pendingStyle.value = newVal

  if (newVal === 'classic' && removalList.value.length === 0) {
    // No removal, apply immediately
    form.value.presentation_style = 'classic'
    // Reset input size when returning to classic
    form.value.size = 'md'
    workingFormStore.closeAllSidebars()
    return
  }

  if (newVal === 'focused' && removalList.value.length === 0) {
    form.value.presentation_style = 'focused'
    // Ensure large input size in focused mode
    form.value.size = 'lg'
    // Ensure settings object is initialized
    ensureSettingsObject(form.value)
    // Enable navigation arrows by default in focused mode
    if (form.value.settings.navigation_arrows === undefined) {
      form.value.settings.navigation_arrows = true
    }
    // Enable focused components (selectors for small select lists, toggles for checkboxes)
    enableFocusedComponents(form.value)
    // Seed first block with an abstract image to highlight focused mode
    seedFocusedFirstBlockImage(form.value)
    workingFormStore.closeAllSidebars()
    return
  }

  // Show modal; do not change selection until confirm
  showConfirmModal.value = true
  // Force re-mount so the internal local state of OptionSelectorInput resets to props.modelValue
  nextTick(() => { selectorKey.value++ })
}

function confirmSwitch() {
  if (!form.value) return
  const target = pendingStyle.value
  const disallowedNames = new Set(
    Object.values(blocksTypes)
      .filter(def => !(def.available_in || ['classic', 'focused']).includes(target))
      .map(def => def.name)
  )
  const props = Array.isArray(form.value.properties) ? form.value.properties : []
  form.value.properties = props.filter(p => !(p && disallowedNames.has(p.type)))
  form.value.presentation_style = target
  // Ensure large input size in focused mode
  if (target === 'focused') {
    form.value.size = 'lg'
    // Ensure settings object is initialized
    ensureSettingsObject(form.value)
    // Enable navigation arrows by default in focused mode
    if (form.value.settings.navigation_arrows === undefined) {
      form.value.settings.navigation_arrows = true
    }
    // Enable focused components (selectors for small select lists, toggles for checkboxes)
    enableFocusedComponents(form.value)
    // Seed first block with an abstract image to highlight focused mode
    seedFocusedFirstBlockImage(form.value)
  } else if (target === 'classic') {
    // Reset input size when returning to classic
    form.value.size = 'md'
    // Disable focused components when returning to classic
    disableFocusedComponents(form.value)
  }
  workingFormStore.closeAllSidebars()
  showConfirmModal.value = false
  pendingStyle.value = null
  removalList.value = []
}

function cancelSwitch() {
  showConfirmModal.value = false
  pendingStyle.value = null
  removalList.value = []
}

function enableFocusedComponents(formValue) {
  if (!formValue || !Array.isArray(formValue.properties)) return
  
  formValue.properties.forEach(field => {
    if (!field) return
    
    // Handle select/multi_select fields
    if (['select', 'multi_select'].includes(field.type)) {
      const options = field[field.type]?.options || []
      
      // In focused mode, FocusedSelectorInput is the default for select/multi_select
      // Use focused selector for fields with 4 or fewer options
      if (options.length <= 4) {
        // Ensure focused selector is enabled (remove any explicit disable)
        if (field.use_focused_selector === false) {
          delete field.use_focused_selector
        }
        // Disable conflicting options
        field.without_dropdown = false
        field.allow_creation = false
      } else if (options.length > 4) {
        // For fields with MORE than 4 options, disable focused selector to use dropdown
        field.use_focused_selector = false
        // Preserve existing dropdown behavior (allow_creation, etc.)
      }
    }
    
    // Handle checkbox fields - FocusedToggleInput is default in focused mode
    // Nothing special to do here - BlockRenderer handles it by default
    // But ensure any explicit disables are removed
    if (field.type === 'checkbox') {
      if (field.use_focused_toggle === false) {
        delete field.use_focused_toggle
      }
    }
  })
}

function disableFocusedComponents(formValue) {
  if (!formValue || !Array.isArray(formValue.properties)) return
  
  formValue.properties.forEach(field => {
    if (!field) return
    
    // Remove focused selector property for select/multi_select
    if (['select', 'multi_select'].includes(field.type)) {
      delete field.use_focused_selector
    }
    
    // Remove focused toggle property for checkboxes
    if (field.type === 'checkbox') {
      delete field.use_focused_toggle
      delete field.focused_checkbox_style
    }
  })
}

function openHelpArticle() {
  crisp.openHelpdeskArticle('how-to-create-a-typeformstyle-onequestionatatime-form-focused-mode-1d5r5x')
}
</script>


