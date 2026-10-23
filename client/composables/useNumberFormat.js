const NUMBER_FORMAT_PRESETS = {
  number: { prefix: '', suffix: '', decimalSeparator: '.', thousandsSeparator: 'none' },
  percent: { prefix: '', suffix: '%', decimalSeparator: '.', thousandsSeparator: 'none' },
  us_dollar: { prefix: '$', suffix: '', decimalSeparator: '.', thousandsSeparator: ',' },
  euro: { prefix: '€', suffix: '', decimalSeparator: ',', thousandsSeparator: '.' },
  pound: { prefix: '£', suffix: '', decimalSeparator: '.', thousandsSeparator: ',' },
}

export function getNumberFormatPreset (format) {
  return NUMBER_FORMAT_PRESETS[format] || null
}

export function applyNumberFormatPreset (field, format) {
  if (!field || !format) return

  const preset = getNumberFormatPreset(format)
  if (preset) {
    field.number_decimal_separator = preset.decimalSeparator
    field.number_thousands_separator = preset.thousandsSeparator
    field.number_prefix = preset.prefix
    field.number_suffix = preset.suffix
    return
  }

  if (format === 'custom') {
    field.number_prefix = field.number_prefix || ''
    field.number_suffix = field.number_suffix || ''
    return
  }

  if (format === 'number') {
    field.number_decimal_separator = '.'
    field.number_thousands_separator = 'none'
    field.number_prefix = ''
    field.number_suffix = ''
  }
}

export function getNumberFormatConfig (field) {
  const format = field?.number_format || 'number'
  const preset = NUMBER_FORMAT_PRESETS[format]

  const decimalSeparator = field?.number_decimal_separator || preset?.decimalSeparator || '.'
  const thousandsSeparator = field?.number_thousands_separator ?? preset?.thousandsSeparator ?? 'none'

  if (format === 'custom') {
    return {
      prefix: field.number_prefix || '',
      suffix: field.number_suffix || '',
      decimalSeparator,
      thousandsSeparator,
    }
  }

  if (preset) {
    return {
      prefix: preset.prefix,
      suffix: preset.suffix,
      decimalSeparator,
      thousandsSeparator,
    }
  }

  return {
    prefix: '',
    suffix: '',
    decimalSeparator,
    thousandsSeparator,
  }
}

export function hasNumberFormatting (field) {
  if (!field || field.type !== 'number') return false

  const format = field.number_format || 'number'
  if (format !== 'number') return true

  if (field.number_thousands_separator && field.number_thousands_separator !== 'none') return true
  if (field.number_decimal_separator && field.number_decimal_separator !== '.') return true

  return false
}

export function formatNumberValue (rawValue, config) {
  if (rawValue === null || rawValue === undefined || rawValue === '') return ''

  const str = String(rawValue)

  const isNegative = str.startsWith('-')
  const absStr = isNegative ? str.slice(1) : str

  const { decimalSeparator, thousandsSeparator } = config
  const sep = thousandsSeparator === 'none' ? '' : thousandsSeparator

  let intPart, decPart
  if (absStr.includes('.')) {
    ;[intPart, decPart] = absStr.split('.')
  } else {
    intPart = absStr
    decPart = null
  }

  intPart = intPart || '0'

  if (sep) {
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, sep)
  }

  let formatted = isNegative ? '-' : ''
  formatted += config.prefix ? config.prefix : ''
  formatted += intPart

  if (decPart !== null) {
    formatted += decimalSeparator + decPart
  }

  if (config.suffix) {
    formatted += config.suffix
  }

  return formatted
}

export function parseFormattedNumber (displayStr, config) {
  if (!displayStr || typeof displayStr !== 'string') return displayStr

  let cleaned = displayStr

  if (config.prefix) {
    cleaned = cleaned.replace(new RegExp('^' + escapeRegex(config.prefix)), '')
  }
  if (config.suffix) {
    cleaned = cleaned.replace(new RegExp(escapeRegex(config.suffix) + '$'), '')
  }

  cleaned = cleaned.trim()

  const { decimalSeparator, thousandsSeparator } = config
  const sep = thousandsSeparator === 'none' ? '' : thousandsSeparator

  if (sep) {
    cleaned = cleaned.split(sep).join('')
  }

  if (decimalSeparator !== '.') {
    cleaned = cleaned.replace(decimalSeparator, '.')
  }

  cleaned = cleaned.replace(/[^\d.-]/g, '')

  if (cleaned === '' || cleaned === '-' || cleaned === '.') return cleaned

  return cleaned
}

function escapeRegex (str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export function useNumberFormat (field) {
  const config = computed(() => getNumberFormatConfig(field?.value || field))
  const isFormatted = computed(() => hasNumberFormatting(field?.value || field))

  const format = (rawValue) => {
    if (!isFormatted.value) return rawValue
    return formatNumberValue(rawValue, config.value)
  }

  const parse = (displayStr) => {
    if (!isFormatted.value) return displayStr
    return parseFormattedNumber(displayStr, config.value)
  }

  return {
    config,
    isFormatted,
    format,
    parse,
  }
}
