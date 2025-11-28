<template>
  <div v-if="isFocusedMode" class="px-4 mt-6">
    <EditorSectionHeader icon="i-heroicons-photo" :icon-class="headerIconClass" title="Image" />

    <!-- Unified image input with settings popover (focus/brightness/alt) -->
    <ImageWithSettings :form="model" name="image.url" label="Image" kind="block" />

    <!-- Layout selection appears only when an image is present -->
    <template v-if="model?.image && model.image.url">
      <OptionSelectorInput
        v-model="model.image.layout"
        name="image.layout"
        :form="model"
        label="Layout"
        :options="layoutOptions"
        :multiple="false"
        :columns="3"
      />
    </template>
  </div>
</template>

<script setup>
import EditorSectionHeader from '~/components/open/forms/components/form-components/EditorSectionHeader.vue'
import ImageWithSettings from './ImageWithSettings.vue'

const props = defineProps({
  model: { type: Object, required: true },
  form: { type: Object, required: false }
})

const isFocusedMode = computed(() => (props.form?.presentation_style || 'classic') === 'focused')

const isEmpty = computed(() => !props.model?.image || !props.model.image?.url)
const headerIconClass = computed(() => isEmpty.value ? 'text-blue-500! animate-pulse' : '')

// DRY icon classes
const iconBaseClass = 'w-[70px] h-[50px] rounded transition-colors duration-150 ease-out [--icon-fg:#737373] [--icon-muted:#D4D4D4] group-hover:[--icon-fg:#3b82f6] group-hover:[--icon-muted:#60a5fa] text-neutral-500 group-hover:text-blue-500 group-aria-selected:[--icon-fg:#3b82f6] group-aria-selected:[--icon-muted:#60a5fa] group-[aria-selected=true]:[--icon-fg:#3b82f6] group-[aria-selected=true]:[--icon-muted:#60a5fa] group-aria-selected:text-blue-500 group-[aria-selected=true]:text-blue-500'
const iconSelectedClass = '[--icon-fg:#3b82f6] [--icon-muted:#60a5fa] text-blue-500'

const layoutOptions = [
  { name: 'between',      icon: 'opnform:form-layout-between',      iconClass: iconBaseClass, iconSelectedClass },
  { name: 'left-small',   icon: 'opnform:form-layout-left-small',    iconClass: iconBaseClass, iconSelectedClass },
  { name: 'left-split',   icon: 'opnform:form-layout-left-split',    iconClass: iconBaseClass, iconSelectedClass },
  { name: 'background',   icon: 'opnform:form-layout-background',    iconClass: iconBaseClass, iconSelectedClass },
  { name: 'right-small',  icon: 'opnform:form-layout-right-small',   iconClass: iconBaseClass, iconSelectedClass },
  { name: 'right-split',  icon: 'opnform:form-layout-right-split',   iconClass: iconBaseClass, iconSelectedClass },
]

function setDefaultImageSettings() {
  if (!props.model.image) props.model.image = {}
  props.model.image.layout = props.model.image.layout ?? 'between'
  props.model.image.focal_point = props.model.image.focal_point ?? { x: 50, y: 50 }
  props.model.image.brightness = typeof props.model.image.brightness === 'number' ? props.model.image.brightness : 0
}

function clearImageSettings() {
  if (!props.model.image) return
  props.model.image.alt = null
  props.model.image.layout = null
  props.model.image.focal_point = null
  props.model.image.brightness = 0
}

watch(() => props.model?.image?.url, (val) => {
  if (val) {
    setDefaultImageSettings()
  } else {
    clearImageSettings()
  }
})
</script>


