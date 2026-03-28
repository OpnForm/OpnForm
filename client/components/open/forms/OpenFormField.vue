<template>
  <div
    v-if="!isFieldHidden"
    :id="'block-' + field.id"
    ref="form-block"
    class="px-2"
    :class="[
      {
        'group/nffield hover:bg-neutral-100/50 relative hover:z-10 transition-colors hover:border-neutral-200 border-dashed border border-transparent box-border dark:hover:border-blue-900 dark:hover:bg-blue-950 rounded-md': isAdminPreview,
        'cursor-pointer':workingFormStore.showEditFieldSidebar && isAdminPreview,
        'bg-blue-50 hover:!bg-blue-50 dark:bg-neutral-700! dark:hover:bg-neutral-700! rounded-md': beingEdited,
      }]"
    @click="setFieldAsSelected"
    @dblclick="fieldDoubleClick"
  >
    <div
      class="-m-[1px] w-full max-w-full mx-auto"
      :class="{'relative transition-colors':isAdminPreview}"
    >
      <div
        v-if="isAdminPreview"
        class="absolute translate-y-full lg:translate-y-0 -bottom-1 left-1/2 -translate-x-1/2 lg:-translate-x-full lg:-left-1 lg:top-1 lg:bottom-0 hidden group-hover/nffield:block z-10"
      >
        <div
          class="flex lg:flex-col bg-white dark:!bg-white border rounded-sm shadow-xs z-50 p-[1px] relative"
        >
          <div
            class="p-1 hover:!text-blue-500 dark:hover:!text-blue-500 hover:bg-blue-50 cursor-pointer !text-neutral-500 dark:!text-neutral-500 flex items-center justify-center rounded-md"
            role="button"
            @click.stop.prevent="openAddFieldSidebar"
          >
            <UTooltip
              arrow
              text="Add new field"
              :content="{ side: 'left' }"
              :ui="{ container: 'z-50' }"
            >
              <Icon
                name="i-heroicons-plus-circle-20-solid"
                class="w-5 h-5 !text-neutral-500 dark:!text-neutral-500 hover:!text-blue-500 dark:hover:!text-blue-500"
              />
            </UTooltip>
          </div>
          <div
            class="p-1 hover:!text-blue-500 dark:hover:!text-blue-500 hover:bg-blue-50 cursor-pointer flex items-center justify-center text-center !text-neutral-500 dark:!text-neutral-500 rounded-md"
            role="button"
            @click.stop.prevent="editFieldOptions"
          >
            <UTooltip
              arrow
              text="Edit field settings"
              :content="{ side: 'left' }"
              :ui="{ container: 'z-50' }"
            >
              <Icon
                name="heroicons:cog-8-tooth-20-solid"
                class="w-5 h-5 !text-neutral-500 dark:!text-neutral-500 hover:!text-blue-500 dark:hover:!text-blue-500"
              />
            </UTooltip>
          </div>
          <div
            class="p-1 hover:!text-red-600 dark:hover:!text-red-600 hover:bg-red-50 cursor-pointer flex items-center justify-center text-center !text-red-500 dark:!text-red-500 rounded-md"
            role="button"
            @click.stop.prevent="removeField"
          >
            <UTooltip
              arrow
              text="Delete field"
              :content="{ side: 'left' }"
              :ui="{ container: 'z-50' }"
            >
              <Icon
                name="heroicons:trash-20-solid"
                class="w-5 h-5 !text-red-500 dark:!text-red-500 hover:!text-red-600 dark:hover:!text-red-600"
              />
            </UTooltip>
          </div>
        </div>
      </div>
      <div v-if="field">
        <BlockRenderer :block="field" :form-manager="formManager" />
      </div>
      
      <div
        class="hidden group-hover/nffield:flex translate-x-full absolute right-0 top-0 h-full w-5 flex-col justify-center pl-1 pt-3"
      >
        <div
          class="flex items-center bg-neutral-100 dark:bg-neutral-800 border rounded-md h-12 text-neutral-500 dark:text-neutral-400 dark:border-neutral-500 cursor-grab handle min-h-[40px]"
        >
          <Icon
            name="clarity:drag-handle-line"
            class="h-6 w-6 -ml-1 block shrink-0"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import FormLogicPropertyResolver from "~/lib/forms/FormLogicPropertyResolver.js"
import { FormMode, createFormModeStrategy } from "~/lib/forms/FormModeStrategy.js"
import { useWorkingFormStore } from '~/stores/working_form'
import BlockRenderer from './BlockRenderer.vue'

// Define props
const props = defineProps({
  field: {
    type: Object,
    required: true
  },
  formManager: {
    type: Object,
    required: true
  }
})

// Derive everything from formManager
const form = computed(() => props.formManager?.config?.value || {})
const dataForm = computed(() => props.formManager?.form || {})
const showHidden = computed(() => props.formManager?.strategy?.value?.display?.showHiddenFields || false)

// Setup stores and reactive state
const workingFormStore = useWorkingFormStore()
const selectedFieldIndex = computed(() => workingFormStore.selectedFieldIndex)
const showEditFieldSidebar = computed(() => workingFormStore.showEditFieldSidebar)
const strategy = computed(() => props.formManager?.strategy?.value || createFormModeStrategy(FormMode.LIVE))
const isAdminPreview = computed(() => strategy.value?.admin?.showAdminControls || false)

// Computed properties
// Field rendering is delegated to BlockRenderer

const isFieldHidden = computed(() => !showHidden.value && shouldBeHidden.value)

const shouldBeHidden = computed(() => 
  (new FormLogicPropertyResolver(props.field, dataForm.value)).isHidden()
)

// Required/props now handled inside BlockRenderer
/* noop */

const beingEdited = computed(() => 
  isAdminPreview.value && 
  showEditFieldSidebar.value && 
  form.value.properties.findIndex((item) => item.id === props.field.id) === selectedFieldIndex.value
)

// Methods
function editFieldOptions() {
  if (!isAdminPreview.value) return
  workingFormStore.openSettingsForField(props.field, true)
}

function setFieldAsSelected() {
  if (!isAdminPreview.value || !workingFormStore.showEditFieldSidebar) return
  workingFormStore.openSettingsForField(props.field)
}

function fieldDoubleClick() {
  if (!isAdminPreview.value) return
  workingFormStore.openSettingsForField(props.field)
}

function openAddFieldSidebar() {
  if (!isAdminPreview.value) return
  workingFormStore.openAddFieldSidebar(props.field)
}

function removeField() {
  if (!isAdminPreview.value) return
  workingFormStore.removeField(props.field)
}

/**
 * Get the right input component options for the field/options
 */

</script>


