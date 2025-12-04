<template>
  <ClientOnlyWrapper v-if="block && hasComponent" :client-only="clientOnlyVal" :key="'dyn-' + (block?.id || 'dyn')">
    <Suspense>
      <component
        :is="componentVal"
        v-bind="boundProps"
        @input-filled="$emit('input-filled', { blockId: block?.id })"
      />
      <template #fallback>
        <USkeleton class="w-full h-16 my-1.5" />
      </template>
    </Suspense>
    <template #fallback>
      <USkeleton class="w-full h-16 my-1.5" />
    </template>
  </ClientOnlyWrapper>
  <div v-else-if="block" :key="'static-' + (block?.id || 'static')">
    <div
      v-if="block.type === 'nf-text' && block.content"
      :id="block.id"
      :key="'text-' + block.id"
      class="nf-text w-full my-1.5 break-words whitespace-break-spaces"
      :class="[getFieldAlignClasses(block)]"
    >
      <TextBlock
        :content="block.content"
        :form="form"
        :form-data="dataForm"
        :media="shouldInjectBetweenMedia ? block.image : null"
      />
    </div>
    <div
      v-else-if="block.type === 'nf-code' && block.content"
      :id="block.id"
      :key="'code-' + block.id"
      class="nf-code w-full px-2 my-1.5"
      v-html="block.content"
    />
    <div
      v-else-if="block.type === 'nf-divider'"
      :id="block.id"
      :key="'divider-' + block.id"
      class="border-b my-4 w-full mx-2"
    />
    <div
      v-else-if="block.type === 'nf-image' && (isAdminPreview || (!isAdminPreview && block.image_block))"
      :id="block.id"
      :key="'image-' + block.id"
      class="my-4 w-full"
      :class="[getFieldAlignClasses(block)]"
      @dblclick="editFieldOptions"
    >
      <div
        v-if="!block.image_block"
        class="p-4 border border-dashed text-center"
      >
        <a
          href="#"
          class="text-blue-800 dark:text-blue-200"
          @click.prevent="editFieldOptions"
        >Open block settings to upload image.</a>
      </div>
      <img
        v-else
        :alt="block.name"
        :src="block.image_block"
        class="max-w-full inline-block"
        :class="roundedClass"
      >
    </div>
    <div
      v-else-if="block.type === 'nf-video' && (isAdminPreview || (!isAdminPreview && block.video_block))"
      :id="block.id"
      :key="'video-' + block.id"
      class="my-4 w-full"
      :class="[getFieldAlignClasses(block)]"
      @dblclick="editFieldOptions"
    >
      <div
        v-if="!block.video_block"
        class="p-4 border border-dashed text-center"
      >
        <a
          href="#"
          class="text-blue-800 dark:text-blue-200"
          @click.prevent="editFieldOptions"
        >Open block settings to add video URL.</a>
      </div>
      <EmbedMedia
        v-else
        :src="block.video_block"
        :is-dark="darkMode"
      />
    </div>
  </div>
</template>

<script setup>
import ClientOnlyWrapper from '~/components/global/ClientOnlyWrapper.vue'
import { useComponentRegistry } from '~/composables/components/useComponentRegistry'
import TextBlock from '~/components/forms/core/TextBlock.vue'
import { shuffleArray } from '~/lib/utils.js'

const props = defineProps({
  block: { type: Object, required: false, default: null },
  formManager: { type: Object, required: true }
})

const workingFormStore = useWorkingFormStore()

const form = computed(() => props.formManager?.config?.value || {})
const dataForm = computed(() => props.formManager?.form || {})
const darkMode = computed(() => props.formManager?.darkMode?.value || false)
const strategy = computed(() => props.formManager?.strategy?.value || {})
const isAdminPreview = computed(() => strategy.value?.admin?.showAdminControls || false)

// Use centralized fieldState from manager
const fieldState = computed(() => props.formManager?.fieldState)

const { getFormComponent } = useComponentRegistry()

const componentInfo = computed(() => {
  const field = props.block
  if (!field || !field.type) return null
  let componentName
  if (field.type === 'text' && field.multi_lines) componentName = 'TextAreaInput'
  else if (field.type === 'url' && field.file_upload) componentName = 'FileInput'
  // In focused mode, use FocusedSelectorInput by default unless explicitly disabled
  else if (['select','multi_select'].includes(field.type) && form.value.presentation_style === 'focused' && field.use_focused_selector !== false) componentName = 'FocusedSelectorInput'
  else if (['select','multi_select'].includes(field.type) && field.without_dropdown) componentName = 'FlatSelectInput'
  // In focused mode, use FocusedToggleInput by default unless explicitly disabled
  else if (field.type === 'checkbox' && form.value.presentation_style === 'focused' && field.use_focused_toggle !== false) componentName = 'FocusedToggleInput'
  else if (field.type === 'checkbox' && field.use_toggle_switch) componentName = 'ToggleSwitchInput'
  else if (field.type === 'signature') componentName = 'SignatureInput'
  else if (field.type === 'phone_number' && !field.use_simple_text_input) componentName = 'PhoneInput'
  else {
    componentName = {
      text: 'TextInput',
      rich_text: 'RichTextAreaInput',
      number: 'TextInput',
      rating: 'RatingInput',
      scale: 'ScaleInput',
      slider: 'SliderInput',
      select: 'SelectInput',
      multi_select: 'SelectInput',
      date: 'DateInput',
      files: 'FileInput',
      checkbox: 'CheckboxInput',
      url: 'TextInput',
      email: 'TextInput',
      phone_number: 'TextInput',
      matrix: 'MatrixInput',
      barcode: 'BarcodeInput',
      payment: 'PaymentInput',
      code: 'CodeInput'
    }[field.type]
  }
  return getFormComponent(componentName)
})

const componentVal = computed(() => {
  const val = componentInfo.value
  if (!val) return null
  // Registry may return a string/async component directly OR an object { component, clientOnly }
  if (typeof val === 'string' || typeof val === 'function') return val
  if (typeof val === 'object' && val) {
    return val.component || val // if it already is a component
  }
  return null
})

const clientOnlyVal = computed(() => {
  const val = componentInfo.value
  if (val && typeof val === 'object' && 'clientOnly' in val) return !!val.clientOnly
  return false
})

const hasComponent = computed(() => !!componentVal.value)

function getFieldAlignClasses(field) {
  if (!field.align || field.align === 'left') return 'text-left'
  else if (field.align === 'right') return 'text-right'
  else if (field.align === 'center') return 'text-center'
  else if (field.align === 'justify') return 'text-justify'
}

const shouldInjectBetweenMedia = computed(() => (
  props.block?.image &&
  props.block.image.url &&
  props.block.image.layout === 'between' &&
  form.value?.presentation_style === 'focused' &&
  !(strategy.value?.display?.forceClassicPresentation === true)
)) 

const roundedClass = computed(() => {
  const radius = form.value?.border_radius || 'small'
  const map = {
    none: 'rounded-none',
    small: 'rounded-lg',
    full: 'rounded-[20px]'
  }
  return map[radius] || 'rounded-lg'
})

// Map select options once at component creation (shuffle if enabled)
const selectOptions = (() => {
  const field = props.block
  if (!field || !['select', 'multi_select'].includes(field.type)) return null
  const options = field[field.type]?.options?.map(option => ({ name: option.name, value: option.name })) ?? []
  return field.shuffle_options && options.length > 1 ? shuffleArray(options) : options
})()

const boundProps = computed(() => {
  const field = props.block
  if (!field) return {}
  const unified = fieldState.value?.getState(field) || { required: !!field?.required, effectiveDisabled: !!field?.disabled, hiddenIndicator: !!field?.hidden }

  const inputProperties = {
    key: field.id,
    name: field.id,
    form: dataForm.value,
    label: (field.hide_field_name) ? null : field.name + (unified.hiddenIndicator ? ' (Hidden Field)' : ''),
    color: form.value.color,
    placeholder: field.placeholder,
    help: field.help,
    helpPosition: (field.help_position) ? field.help_position : 'below_input',
    uppercaseLabels: form.value.uppercase_labels == 1 || form.value.uppercase_labels == true,
    maxCharLimit: (field.max_char_limit) ? parseInt(field.max_char_limit) : null,
    showCharLimit: field.show_char_limit || false,
    isDark: darkMode.value,
    locale: (form.value?.language) ? form.value.language : 'en',
    media: shouldInjectBetweenMedia.value ? field.image : null,
    presentation: form.value?.presentation_style || 'classic',
    required: !!unified.required,
    disabled: !!unified.effectiveDisabled
  }

  if (field.type === 'matrix') {
    inputProperties.rows = field.rows
    inputProperties.columns = field.columns
  }
  if (field.type === 'barcode') inputProperties.decoders = field.decoders

  if (['select', 'multi_select'].includes(field.type)) {
    inputProperties.options = selectOptions ?? []
    inputProperties.multiple = (field.type === 'multi_select')
    inputProperties.allowCreation = (field.allow_creation === true)
    inputProperties.searchable = (inputProperties.options.length > 4)
    inputProperties.clearable = !unified.required
    if (field.type === 'multi_select') {
      inputProperties.minSelection = field.min_selection || null
      inputProperties.maxSelection = field.max_selection || null
    }
  } else if (field.type === 'date') {
    inputProperties.dateFormat = field.date_format
    inputProperties.timeFormat = field.time_format
    if (field.with_time) inputProperties.withTime = true
    if (field.date_range) inputProperties.dateRange = true
    if (field.disable_past_dates) inputProperties.disablePastDates = true
    else if (field.disable_future_dates) inputProperties.disableFutureDates = true
  } else if (field.type === 'files' || (field.type === 'url' && field.file_upload)) {
    inputProperties.multiple = (field.multiple !== undefined && field.multiple)
    inputProperties.cameraUpload = (field.camera_upload !== undefined && field.camera_upload)
    let maxFileSize = (form.value?.workspace && form.value?.workspace.max_file_size) ? form.value?.workspace?.max_file_size : 10
    if (field?.max_file_size > 0) maxFileSize = Math.min(field.max_file_size, maxFileSize)
    inputProperties.mbLimit = maxFileSize
    inputProperties.accept = (form.value.is_pro && field.allowed_file_types) ? field.allowed_file_types : ''
  } else if (field.type === 'rating') {
    inputProperties.numberOfStars = parseInt(field.rating_max_value) ?? 5
  } else if (field.type === 'scale') {
    inputProperties.minScale = parseFloat(field.scale_min_value) ?? 1
    inputProperties.maxScale = parseFloat(field.scale_max_value) ?? 5
    inputProperties.stepScale = parseFloat(field.scale_step_value) ?? 1
  } else if (field.type === 'slider') {
    inputProperties.minSlider = parseInt(field.slider_min_value) ?? 0
    inputProperties.maxSlider = parseInt(field.slider_max_value) ?? 50
    inputProperties.stepSlider = parseInt(field.slider_step_value) ?? 5
  } else if (field.type === 'number' || (field.type === 'phone_number' && field.use_simple_text_input)) {
    inputProperties.pattern = '/d*'
  } else if (field.type === 'phone_number' && !field.use_simple_text_input) {
    inputProperties.unavailableCountries = field.unavailable_countries ?? []
  } else if (field.type === 'text' && field.secret_input) {
    inputProperties.nativeType = 'password'
  } else if (field.type === 'payment') {
    inputProperties.direction = form.value.layout_rtl ? 'rtl' : 'ltr'
    inputProperties.currency = field.currency
    inputProperties.amount = field.amount
    inputProperties.oauthProviderId = field.stripe_account_id
    if (props.formManager?.payment) {
      try { inputProperties.paymentData = props.formManager.payment.getPaymentData(field) } catch (e) { console.error(e) }
    }
  }

  return inputProperties
})

const editFieldOptions = () => {
  workingFormStore.openSettingsForField(props.block, true)
}
</script>


