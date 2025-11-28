<template>
  <InputWrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <UPopover
      v-model:open="pickerOpen"
      arrow
      :disabled="props.disabled"
      :content="{ side: 'bottom', align: 'center' }"
    >
      <button
        ref="triggerButton"
        class="overflow-hidden"
        :class="ui.input({ class: props.ui?.slots?.input, isOpen: pickerOpen })"
        :disabled="props.disabled"
        :aria-expanded="pickerOpen"
        :aria-haspopup="true"
        :aria-label="formattedDatePreview ? `Selected date: ${formattedDatePreview}` : 'Select date'"
        @keydown="handleTriggerKeydown"
      >
        <div class="flex items-stretch min-w-0">
          <div
            class="grow min-w-0 flex items-center gap-x-2"
            :class="ui.inner({ class: props.ui?.slots?.inner })"
          >
            <Icon
              name="heroicons:calendar-20-solid"
              class="w-4 h-4 shrink-0"
              dynamic
            />
            <div class="grow truncate overflow-hidden flex items-center">
              <p
                v-if="formattedDatePreview"
                class="grow truncate"
              >
                {{ formattedDatePreview }}
              </p>
              <p
                v-else
                class="text-transparent"
              >
                -
              </p>
            </div>
          </div>
          <button
            v-if="fromDate && !props.disabled"
            :class="ui.clearButton({ class: props.ui?.slots?.clearButton })"
            :aria-label="'Clear date'"
            @click.prevent="clear()"
            @keydown="handleClearKeydown"
          >
            <Icon
              name="heroicons:x-mark-20-solid"
              class="w-5 h-5 text-neutral-500"
              width="2em"
              dynamic
            />
          </button>
        </div>
      </button>

      <template #content>
        <DatePicker
          ref="datePicker"
          class="rounded-md"
          v-if="props.dateRange"
          v-model.range="modeledValue"
          :mode="props.withTime ? 'dateTime' : 'date'"
          :is24hr="props.timeFormat == '24'"
          is-required
          borderless
          :min-date="minDate"
          :max-date="maxDate"
          :is-dark="props.isDark"
          color="form"
          :locale="props.locale"
          @update:model-value="updateModelValue"
          :style="datePickerStyles"
        />
        <DatePicker
          ref="datePicker"
          class="rounded-md"
          v-else
          v-model="modeledValue"
          :mode="props.withTime ? 'dateTime' : 'date'"
          :is24hr="props.timeFormat == '24'"
          is-required
          borderless
          :min-date="minDate"
          :max-date="maxDate"
          :is-dark="props.isDark"
          color="form"
          :locale="props.locale"
          @update:model-value="updateModelValue"
          :style="datePickerStyles"
        />
      </template>
    </UPopover>

    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>
  </InputWrapper>
</template>

<script setup>
import { inputProps, useFormInput } from '../useFormInput.js'
import { getCurrentInstance, nextTick } from 'vue'
import { DatePicker } from 'v-calendar'
import 'v-calendar/dist/style.css'
import { format, startOfDay, endOfDay } from 'date-fns'
import { tailwindcssPaletteGenerator } from '~/lib/colors.js'
import { dateInputTheme } from '~/lib/forms/themes/date-input.theme.js'

const props = defineProps({
  ...inputProps,
  withTime: { type: Boolean, default: false },
  dateRange: { type: Boolean, default: false },
  disablePastDates: { type: Boolean, default: false },
  disableFutureDates: { type: Boolean, default: false },
  dateFormat: { type: String, default: 'dd/MM/yyyy' },
  timeFormat: { type: String, default: '24' },
  outputDateFormat: { type: String, default: 'yyyy-MM-dd\'T\'HH:mm:ssXXX' },
  isDark: { type: Boolean, default: false }
})

const {
  ui,
  compVal,
  inputWrapperProps
} = useFormInput(props, getCurrentInstance(), {
  variants: dateInputTheme
})
const fromDate = ref(null)
const toDate = ref(null)
const triggerButton = ref(null)
const datePicker = ref(null)
const pickerOpen = ref(false)

const twColors = computed(() => {
  return tailwindcssPaletteGenerator(props.color).primary
})

const datePickerStyles = computed(() => {
  if (!twColors.value) {
    return {}
  }
  const styles = {}
  for (const shade in twColors.value) {
    styles[`--vc-accent-${shade}`] = twColors.value[shade]
  }
  return styles
})

const modeledValue = computed({
  get () {
    return props.dateRange ? { start: fromDate.value, end: toDate.value } : fromDate.value
  },
  set (value) {
    if (props.dateRange) {
      fromDate.value = format(value.start, props.outputDateFormat)
      toDate.value = format(value.end, props.outputDateFormat)
    } else {
      fromDate.value = format(value, props.outputDateFormat)
    }
  }
})

const updateModelValue = () => {
  if (!props.withTime && !props.dateRange && modeledValue.value) {
    pickerOpen.value = false
    // Return focus to trigger button after selection
    returnFocusToTrigger()
  }
}


const minDate = computed(() => {
  if (props.disablePastDates) {
    return startOfDay(new Date())
  }
  return undefined
})
const maxDate = computed(() => {
  if (props.disableFutureDates) {
    return endOfDay(new Date())
  }
  return undefined
})
const handleCompValChange = () => {
  if (compVal.value) {
    if (Array.isArray(compVal.value)) {
      fromDate.value = compVal.value[0] ?? null
      toDate.value = compVal.value[1] ?? null
    } else {
      fromDate.value = compVal.value
    }
  }
}

const setInputColor = () => {
  if (triggerButton.value) {
    triggerButton.value.style.setProperty('--tw-ring-color', props.color)
  }
}

const clear = () => {
  fromDate.value = null
  toDate.value = null
  pickerOpen.value = false
  // Return focus to trigger button after clearing
  returnFocusToTrigger()
}

// Focus management helpers
const returnFocusToTrigger = () => {
  nextTick(() => {
    triggerButton.value?.focus()
  })
}

const focusDatePicker = () => {
  // Give v-calendar a moment to render before focusing
  setTimeout(() => {
    // Look for the date picker in the DOM using a more generic approach
    const popoverElement = document.querySelector('[data-headlessui-state], .vc-container')
    if (popoverElement) {
      // Look for the first focusable element within the date picker
      const focusable = popoverElement.querySelector('button:not([disabled]), .vc-day:not([disabled]), .vc-nav-arrow, [tabindex="0"]')
      if (focusable) {
        focusable.focus()
      }
    }
  }, 50)
}

const handleTriggerKeydown = (event) => {
  if (props.disabled) return

  switch (event.key) {
    case 'Escape':
      if (pickerOpen.value) {
        event.preventDefault()
        pickerOpen.value = false
      }
      break
    case 'Enter':
    case ' ':
      if (!pickerOpen.value) {
        event.preventDefault()
        pickerOpen.value = true
      }
      break
    case 'Backspace':
    case 'Delete':
      if (fromDate.value && !pickerOpen.value) {
        event.preventDefault()
        clear()
      }
      break
  }
}

const handleClearKeydown = (event) => {
  if (props.disabled) return

  switch (event.key) {
    case 'Enter':
    case ' ':
      event.preventDefault()
      clear()
      break
  }
}

// Global keydown handler for when picker is open
const handleGlobalKeydown = (event) => {
  if (!pickerOpen.value || props.disabled) return

  if (event.key === 'Escape') {
    event.preventDefault()
    pickerOpen.value = false
    returnFocusToTrigger()
  }
  // Let v-calendar handle Tab and other keys naturally
}

const formattedDate = (value) => {
  if (props.withTime) {
    try {
      return format(new Date(value), props.dateFormat + (props.timeFormat == 12 ? ' p':' HH:mm'))
    } catch {
      console.error('Error formatting date')
      return ''
    }
  }
  try {
    return format(new Date(value), props.dateFormat)
  } catch {
    return ''
  }
}

const formattedDatePreview = computed(() => {
  if (!fromDate.value) return ''
  if (props.dateRange) {
    if (!toDate.value) return formattedDate(fromDate.value)
    return `${formattedDate(fromDate.value)} - ${formattedDate(toDate.value)}`
  }
  return formattedDate(fromDate.value)
})

watch(() => props.color, () => {
  setInputColor()
}, { immediate: true })

watch(() => props.dateRange, () => {
  fromDate.value = null
  toDate.value = null
}, { immediate: true })

watch(() => fromDate.value, (val) => {
  if (props.dateRange) {
    if (!Array.isArray(compVal.value)) compVal.value = []
    compVal.value[0] = val
  } else {
    compVal.value = val
  }
}, { immediate: false })

watch(() => toDate.value, (val) => {
  if (props.dateRange) {
    if (!Array.isArray(compVal.value)) compVal.value = [null]
    compVal.value[1] = val
  } else {
    compVal.value = null
  }
}, { immediate: false })

watch(() => compVal.value, (val, oldVal) => {
  if (!oldVal) handleCompValChange()
}, { immediate: false })

// Watch picker open state to manage global event listeners and focus
watch(pickerOpen, (isOpen) => {
  if (isOpen) {
    document.addEventListener('keydown', handleGlobalKeydown)
    // Focus the date picker when it opens
    focusDatePicker()
  } else {
    document.removeEventListener('keydown', handleGlobalKeydown)
  }
})

onMounted(() => {
  handleCompValChange()
  setInputColor()
})

onUnmounted(() => {
  // Clean up global event listener
  document.removeEventListener('keydown', handleGlobalKeydown)
})
</script>

<style>
.vc-title {
  padding: 0px 4px;
  border-radius: 4px;
}

.vc-title:focus, .vc-title:hover {
  background: var(--vc-header-arrow-hover-bg) !important;
}
</style>