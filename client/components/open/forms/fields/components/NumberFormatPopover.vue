<template>
  <div>
    <UPopover arrow :content="{ align: 'start', side: 'left' }">
      <UButton
        icon="i-heroicons-currency-dollar"
        label="Number Formatting"
        variant="outline"
        color="neutral"
        block
        class="justify-start mt-3"
      />

      <template #content>
        <div class="p-4 w-72">
          <p class="text-sm font-medium mb-3">Number Formatting</p>

          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500 mb-1.5">Decimal separator</p>
              <OptionSelectorInput
                v-model="field.number_decimal_separator"
                name="number_decimal_separator"
                :form="field"
                :options="decimalSeparatorOptions"
                :multiple="false"
                :columns="2"
                @update:model-value="onDecimalSeparatorChange"
              />
            </div>

            <div>
              <p class="text-xs text-gray-500 mb-1.5">Thousands separator</p>
              <OptionSelectorInput
                v-model="field.number_thousands_separator"
                name="number_thousands_separator"
                :form="field"
                :options="thousandsSeparatorOptions"
                :multiple="false"
                :columns="3"
                @update:model-value="onThousandsSeparatorChange"
              />
            </div>

            <div>
              <p class="text-xs text-gray-500 mb-1.5">Formats</p>
              <FlatSelectInput
                v-model="field.number_format"
                name="number_format"
                :form="field"
                :options="formatOptions"
                :clearable="false"
                size="sm"
                @update:model-value="onFormatSelect"
              >
                <template #option="{ option }">
                  <div class="flex items-center justify-between w-full gap-2">
                    <span>{{ option.name }}</span>
                    <span class="text-gray-500 shrink-0 text-xs">{{ option.example }}</span>
                  </div>
                </template>
              </FlatSelectInput>
            </div>

            <div v-if="field.number_format === 'custom'">
              <p class="text-xs text-gray-500 mb-1.5">Customization</p>
              <div class="flex gap-2">
                <text-input
                  name="number_prefix"
                  class="flex-1"
                  :form="field"
                  placeholder="Prefix"
                  size="sm"
                />
                <text-input
                  name="number_suffix"
                  class="flex-1"
                  :form="field"
                  placeholder="Suffix"
                  size="sm"
                />
              </div>
            </div>
          </div>
        </div>
      </template>
    </UPopover>
  </div>
</template>

<script setup>
import OptionSelectorInput from '~/components/forms/core/OptionSelectorInput.vue'
import FlatSelectInput from '~/components/forms/core/FlatSelectInput.vue'
import {
  applyNumberFormatPreset,
  formatNumberValue,
  getNumberFormatConfig,
  getNumberFormatPreset,
} from '~/composables/useNumberFormat.js'

const props = defineProps({
  field: {
    type: Object,
    required: true,
  },
})

const decimalSeparatorOptions = [
  { name: '.', label: '0.1' },
  { name: ',', label: '0,1' },
]

const thousandsSeparatorOptions = [
  { name: 'none', label: '1000' },
  { name: ',', label: '1,000' },
  { name: ' ', label: '1 000' },
]

const FORMAT_SAMPLE_VALUE = '1234.56'

const formatOptions = computed(() => {
  const presets = [
    { value: 'number', name: 'Number' },
    { value: 'percent', name: 'Percent' },
    { value: 'us_dollar', name: 'US Dollar' },
    { value: 'euro', name: 'Euro' },
    { value: 'pound', name: 'Pound' },
    { value: 'custom', name: 'Custom' },
  ]

  return presets.map(({ value, name }) => {
    const config = value === 'custom'
      ? getNumberFormatConfig({
        number_format: 'custom',
        number_prefix: props.field.number_prefix,
        number_suffix: props.field.number_suffix,
        number_decimal_separator: props.field.number_decimal_separator,
        number_thousands_separator: props.field.number_thousands_separator,
      })
      : getNumberFormatPreset(value)

    const example = formatNumberValue(FORMAT_SAMPLE_VALUE, config)

    return {
      value,
      name,
      example,
    }
  })
})

const syncPresetToField = () => {
  const format = props.field.number_format || 'number'
  const preset = getNumberFormatPreset(format)
  if (!preset) return

  const needsSync = (
    props.field.number_decimal_separator !== preset.decimalSeparator
    || props.field.number_thousands_separator !== preset.thousandsSeparator
    || (props.field.number_prefix || '') !== preset.prefix
    || (props.field.number_suffix || '') !== preset.suffix
  )

  if (needsSync) {
    applyNumberFormatPreset(props.field, format)
  }
}

const onFormatSelect = (format) => {
  props.field.number_format = format
  applyNumberFormatPreset(props.field, format)
}

const onDecimalSeparatorChange = (val) => {
  if (val === ',' && props.field.number_thousands_separator === ',') {
    props.field.number_thousands_separator = 'none'
  }
}

const onThousandsSeparatorChange = (val) => {
  if (val === ',' && props.field.number_decimal_separator === ',') {
    props.field.number_decimal_separator = '.'
  }
}

onMounted(() => {
  syncPresetToField()
})

watch(() => props.field.number_format, (format) => {
  if (format && format !== 'custom') {
    applyNumberFormatPreset(props.field, format)
  }
})
</script>
