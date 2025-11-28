<template>
  <input-wrapper
    v-bind="inputWrapperProps"
  >
    <template #label>
      <slot name="label" />
    </template>

    <div
      ref="phoneInputContainer"
      :id="id ? id : name"
      :name="name"
      :style="inputStyle"
      class="grid items-stretch w-full"
      :class="resolvedTheme === 'minimal' ? 'grid-cols-[auto_0.1rem_minmax(0,1fr)]' : 'grid-cols-[auto_minmax(0,1fr)]'"
    >
      <v-select
        class="min-w-0"
        v-model="selectedCountryCode"
        :input-class="resolvedTheme === 'transparent' ? '!rounded-none' : 'ltr-only:rounded-r-none rtl:rounded-l-none!'"
        :data="countries"
        :disabled="disabled || countries.length===1"
        :searchable="true"
        :search-keys="['name']"
        :option-key="'code'"
        :color="color"
        :has-error="hasError"
        :theme="resolvedTheme"
        :size="resolvedSize"
        :border-radius="resolvedBorderRadius"
        :popover-width="width"
        :ui="{ container: ui.countrySelectWidth() }"
        :placeholder="'Select a country'"
        :uppercase-labels="true"
        @update:model-value="onChangeCountryCode"
      >
        <template #option="props">
          <div class="flex items-center gap-2 max-w-full">
            <country-flag
              size="normal"
              class="-mt-[9px]! rounded"
              :country="props.option.code"
            />
            <span class="truncate" :class="ui.option({ class: props.ui?.slots?.option })">{{ props.option.name }}</span>
            <span class="text-gray-500 whitespace-nowrap" :class="ui.option({ class: props.ui?.slots?.option })">{{ props.option.dial_code && props.option.dial_code.startsWith('+') ? props.option.dial_code : '+' + props.option.dial_code }}</span>
          </div>
        </template>
        <template #selected="props">
          <div
            class="flex items-center gap-2 w-full overflow-hidden ltr-only:pr-1 rtl-only:pl-1"
            :class="[ui.selectedMaxHeight({ class: props.ui?.slots?.selectedMaxHeight }), ui.selected({ class: props.ui?.slots?.selected })]"
          >
            <span class="whitespace-nowrap shrink-0" :class="ui.selected({ class: props.ui?.slots?.selected })">{{ props.option.dial_code && props.option.dial_code.startsWith('+') ? props.option.dial_code : '+' + props.option.dial_code }}</span>
            <country-flag
              :size="countryFlagSize"
              class="rounded-lg! ms-auto shrink-0"
              :class="ui.flag({ class: props.ui?.slots?.flag })"
              :country="props.option.code"
            />
          </div>
        </template>
      </v-select>
      <div
        v-if="resolvedTheme === 'minimal'"
        :class="ui.separator({ class: props.ui?.slots?.separator })"
        class="w-0 self-stretch"
        aria-hidden="true"
      />
      <input
        v-model="inputVal"
        type="text"
        class="w-full min-w-0 ltr-only:border-l-0 ltr-only:!rounded-l-none rtl:border-r-0 rtl:rounded-r-none"
        :disabled="disabled?true:null"
        :class="ui.input({ class: props.ui?.slots?.input })"
        :placeholder="placeholder"
        :style="inputStyle"
        @input="onInput"
        @keydown.enter="onEnterPress"
      >
    </div>

    <template #help>
      <slot name="help" />
    </template>

    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script setup>
import { inputProps, useFormInput } from '../useFormInput.js'
import countryCodes from '~/data/country_codes.json'
import CountryFlag from 'vue-country-flag-next'
import parsePhoneNumber from 'libphonenumber-js'
import { phoneInputTheme } from '~/lib/forms/themes/phone-input.theme.js'
import { useElementSize } from '@vueuse/core'

// Emits
const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'input-filled'])

// Props
const props = defineProps({
  ...inputProps,
  canOnlyCountry: { type: Boolean, default: false },
  unavailableCountries: { type: Array, default: () => [] },
  preventEnter: { type: Boolean, default: true }
})

// Composables
const { compVal, resolvedSize, resolvedTheme, inputStyle, inputWrapperProps, ui } = useFormInput(props, { emit }, {
  variants: phoneInputTheme
})

// Reactive data
const selectedCountryCode = ref(null)
const inputVal = ref(null)

// Computed properties
const countries = computed(() => {
  return countryCodes.filter((item) => {
    return !props.unavailableCountries.includes(item.code)
  })
})

const phoneInputContainer = ref(null)
const { width } = useElementSize(phoneInputContainer)

const countryFlagSize = computed(() => {
  const size = resolvedSize.value
  return (size === 'xs' || size === 'sm') ? 'small' : 'normal'
})

// Methods
const getCountryBy = (code = 'US', type = 'code') => {
  if (!code) code = 'US' // Default US
  return countries.value.find((item) => {
    return item[type] === code
  }) ?? null
}

const onInput = (event) => {
  inputVal.value = event?.target?.value.replace(/[^0-9]/g, '')
}

const onEnterPress = (event) => {
  if (props.preventEnter) {
    event.preventDefault()
  }
  emit('input-filled')
  return false
}

const onChangeCountryCode = () => {
  if (!selectedCountryCode.value && countries.value.length > 0) {
    selectedCountryCode.value = countries.value[0]
  }
  if (props.canOnlyCountry && (inputVal.value === null || inputVal.value === '' || !inputVal.value)) {
    compVal.value = selectedCountryCode.value.code + selectedCountryCode.value.dial_code
  }
}

const initState = () => {
  if (compVal.value === null) {
    return
  }
  if (!compVal.value?.startsWith('+')) {
    // If the user already selected a country, don't override it with inference
    if (!selectedCountryCode.value) {
      selectedCountryCode.value = getCountryBy(compVal.value.substring(2, 0))
    }
  }

  // Parse only the dial part (strip ISO code prefix like "US")
  let toParse = compVal.value
  if (!toParse.startsWith('+')) {
    const plusIndex = toParse.indexOf('+')
    if (plusIndex >= 0) {
      toParse = toParse.substring(plusIndex)
    }
  }
  const phoneObj = parsePhoneNumber(toParse)
  if (phoneObj !== undefined && phoneObj) {
    if (phoneObj.country !== undefined && phoneObj.country) {
      // Sync selection to parsed country when different or not set
      if (!selectedCountryCode.value || selectedCountryCode.value.code !== phoneObj.country) {
        selectedCountryCode.value = getCountryBy(phoneObj.country)
      }
    }
    inputVal.value = phoneObj.nationalNumber
  }
}

// Watchers
watch(inputVal, (val) => {
  if (!selectedCountryCode.value) return
  
  if (val && val.startsWith('0')) {
    val = val.substring(1)
  }
  if (props.canOnlyCountry) {
    compVal.value = (val) ? selectedCountryCode.value.code + selectedCountryCode.value.dial_code + val : selectedCountryCode.value.code + selectedCountryCode.value.dial_code
  } else {
    compVal.value = (val) ? selectedCountryCode.value.code + selectedCountryCode.value.dial_code + val : null
  }
})

watch(() => compVal.value, () => {
  initState()
})

watch(selectedCountryCode, (newVal, oldVal) => {
  if (compVal.value && newVal && oldVal) {
    compVal.value = compVal.value.replace(oldVal.code + oldVal.dial_code, newVal.code + newVal.dial_code)
  }
})

// Lifecycle
onMounted(() => {
  if (compVal.value) {
    initState()
  }
  if (!selectedCountryCode.value) {
    selectedCountryCode.value = getCountryBy()
  }
  if (!selectedCountryCode.value || countries.value.length === 1) {
    selectedCountryCode.value = countries.value[0]
  }
})
</script>