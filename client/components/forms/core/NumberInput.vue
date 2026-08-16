<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <input
      v-if="hasNumberFormat"
      :id="id ? id : name"
      v-model="displayValue"
      :disabled="disabled ? true : null"
      type="text"
      inputmode="decimal"
      :style="inputStyle"
      :class="ui.input({ class: props.ui?.slots?.input })"
      :name="name"
      :placeholder="placeholder"
      :maxlength="maxCharLimit"
      @input="onFormattedInput"
      @blur="onFormattedBlur"
      @focus="onFormattedFocus"
      @keydown.enter="onEnterPress"
    >
    <input
      v-else
      :id="id ? id : name"
      v-model="compVal"
      :disabled="disabled ? true : null"
      :type="nativeType"
      :autocomplete="autocomplete"
      :pattern="pattern"
      :style="inputStyle"
      :class="ui.input({ class: props.ui?.slots?.input })"
      :name="name"
      :accept="accept"
      :placeholder="placeholder"
      :min="min"
      :max="max"
      :maxlength="maxCharLimit"
      @change="onChange"
      @keydown.enter="onEnterPress"
      @focus="onFocus"
      @blur="onBlur"
    >

    <template
      v-if="$slots.help"
      #help
    >
      <slot name="help" />
    </template>

    <template
      v-if="maxCharLimit && showCharLimit"
      #bottom_after_help
    >
      <small :class="ui.help({ class: props.ui?.slots?.help })">
        {{ charCount }}/{{ maxCharLimit }}
      </small>
    </template>

    <template
      v-if="$slots.error"
      #error
    >
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import { inputProps, useFormInput } from '../useFormInput.js'
import { textInputTheme } from '~/lib/forms/themes/text-input.theme.js'
import { formatNumberValue, getNumberFormatConfig, hasNumberFormatting } from '~/composables/useNumberFormat.js'

export default {
  name: 'NumberInput',
  components: {},

  props: {
    ...inputProps,
    nativeType: { type: String, default: 'text' },
    accept: { type: String, default: null },
    min: { type: Number, required: false, default: null },
    max: { type: Number, required: false, default: null },
    autocomplete: { type: [Boolean, String, Object], default: null },
    maxCharLimit: { type: Number, required: false, default: null },
    pattern: { type: String, default: null },
    preventEnter: { type: Boolean, default: true },
    numberFormat: { type: String, default: 'number' },
    numberDecimalSeparator: { type: String, default: '.' },
    numberThousandsSeparator: { type: String, default: 'none' },
    numberPrefix: { type: String, default: '' },
    numberSuffix: { type: String, default: '' },
  },

  setup(props, context) {
    const formInput = useFormInput(
      props,
      context,
      {
        formPrefixKey: props.nativeType === 'file' ? 'file-' : null,
        variants: textInputTheme
      },
    )

    const hasNumberFormat = computed(() => {
      return hasNumberFormatting({
        type: 'number',
        number_format: props.numberFormat,
        number_decimal_separator: props.numberDecimalSeparator,
        number_thousands_separator: props.numberThousandsSeparator,
        number_prefix: props.numberPrefix,
        number_suffix: props.numberSuffix,
      })
    })

    const numberConfig = computed(() => {
      if (!hasNumberFormat.value) return null
      return getNumberFormatConfig({
        number_format: props.numberFormat,
        number_decimal_separator: props.numberDecimalSeparator,
        number_thousands_separator: props.numberThousandsSeparator,
        number_prefix: props.numberPrefix,
        number_suffix: props.numberSuffix,
      })
    })

    const displayValue = ref('')

    const updateDisplay = () => {
      if (!hasNumberFormat.value || !numberConfig.value) return
      const raw = formInput.compVal.value
      if (raw === null || raw === undefined || raw === '') {
        displayValue.value = ''
        return
      }
      displayValue.value = formatNumberValue(raw, numberConfig.value)
    }

    watch(() => formInput.compVal.value, () => {
      updateDisplay()
    }, { immediate: true })

    watch(numberConfig, () => {
      updateDisplay()
    })

    const cleanFormattedInput = (raw) => {
      const config = numberConfig.value
      let cleaned = raw

      if (config.prefix) {
        cleaned = cleaned.replace(new RegExp('^' + config.prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), '')
      }
      if (config.suffix) {
        cleaned = cleaned.replace(new RegExp(config.suffix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'), '')
      }

      const decSep = config.decimalSeparator
      const thousandSep = config.thousandsSeparator !== 'none' ? config.thousandsSeparator : ''

      if (thousandSep) {
        cleaned = cleaned.split(thousandSep).join('')
      }
      if (decSep !== '.') {
        cleaned = cleaned.replace(decSep, '.')
      }

      cleaned = cleaned.replace(/[^\d.-]/g, '')

      const dotCount = (cleaned.match(/\./g) || []).length
      if (dotCount > 1) {
        const parts = cleaned.split('.')
        cleaned = parts[0] + '.' + parts.slice(1).join('')
      }

      return cleaned
    }

    const onFormattedInput = (event) => {
      const raw = event.target.value
      const cleaned = cleanFormattedInput(raw)

      if (cleaned === '' || cleaned === '-' || cleaned === '.' || cleaned === '-.') {
        formInput.compVal.value = cleaned === '' ? null : cleaned
        displayValue.value = raw
        return
      }

      formInput.compVal.value = cleaned
    }

    const onFormattedFocus = (event) => {
      const raw = formInput.compVal.value
      if (raw !== null && raw !== undefined && raw !== '') {
        displayValue.value = String(raw)
      }
      formInput.onFocus(event)
    }

    const onFormattedBlur = (event) => {
      updateDisplay()
      formInput.onBlur(event)
    }

    const onChange = (event) => {
      if (props.nativeType !== 'file') return

      const file = event.target.files[0]

      props.form[props.name] = file
    }

    const onEnterPress = (event) => {
      if (props.preventEnter) {
        event.preventDefault()
      }
      context.emit('input-filled')
      return false
    }

    return {
      ...formInput,
      onEnterPress,
      onChange,
      hasNumberFormat,
      displayValue,
      onFormattedInput,
      onFormattedFocus,
      onFormattedBlur,
      props
    }
  },

  computed: {
    charCount () {
      return this.compVal ? this.compVal.length : 0
    }
  }
}
</script>
