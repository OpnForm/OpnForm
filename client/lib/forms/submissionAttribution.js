export const ATTRIBUTION_MAX_VALUE_LENGTH = 2048

export const ATTRIBUTION_PARAMETERS = Object.freeze([
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_id',
  'utm_term',
  'utm_content',
  'utm_source_platform',
  'utm_creative_format',
  'utm_marketing_tactic',
  'gclid',
  'gbraid',
  'wbraid',
  'dclid',
  'fbclid',
  'ttclid',
  'msclkid',
])

export function attributionColumnId(parameter) {
  return `meta.attribution.${parameter}`
}

export function sanitizeAttribution(parameters) {
  if (!parameters || typeof parameters !== 'object' || Array.isArray(parameters)) return {}

  return ATTRIBUTION_PARAMETERS.reduce((attribution, parameter) => {
    const value = parameters[parameter]
    if (typeof value !== 'string' || value.trim() === '' || value.length > ATTRIBUTION_MAX_VALUE_LENGTH) {
      return attribution
    }

    attribution[parameter] = value
    return attribution
  }, {})
}

export function extractAttribution(searchParams) {
  let params
  try {
    params = searchParams instanceof URLSearchParams
      ? searchParams
      : new URLSearchParams(searchParams || '')
  } catch {
    return {}
  }

  return ATTRIBUTION_PARAMETERS.reduce((attribution, parameter) => {
    const value = params.getAll(parameter).find(candidate => (
      candidate.trim() !== '' && candidate.length <= ATTRIBUTION_MAX_VALUE_LENGTH
    ))

    if (value !== undefined) attribution[parameter] = value

    return attribution
  }, {})
}

export function mergeAttribution(iframeAttribution, parentAttribution) {
  return {
    ...sanitizeAttribution(parentAttribution),
    ...sanitizeAttribution(iframeAttribution),
  }
}
