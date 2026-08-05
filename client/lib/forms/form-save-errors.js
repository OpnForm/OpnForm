const AGGREGATE_MESSAGES = new Set([
  'One or more properties have validation errors.',
  'One or more computed variables have validation errors.',
])

const GENERAL_ERROR_LABELS = {
  properties: 'Form fields',
  computed_variables: 'Computed variables',
}

function normalizeMessages(messages) {
  const values = Array.isArray(messages) ? messages : [messages]

  return values
    .filter(message => message !== null && message !== undefined)
    .map(message => String(message).trim())
    .filter(message => message.length > 0 && !AGGREGATE_MESSAGES.has(message))
}

function addUniqueMessages(target, messages) {
  messages.forEach((message) => {
    if (!target.includes(message)) {
      target.push(message)
    }
  })
}

function humanizeErrorKey(key) {
  if (GENERAL_ERROR_LABELS[key]) {
    return GENERAL_ERROR_LABELS[key]
  }

  const rootKey = key.split('.')[0]
  const label = GENERAL_ERROR_LABELS[rootKey] || rootKey

  return label
    .replaceAll('_', ' ')
    .replace(/^\w/, character => character.toUpperCase())
}

function isEditableItem(item) {
  return Boolean(item && typeof item === 'object' && !Array.isArray(item))
}

function fieldTargetTab(errorPath) {
  return errorPath === 'logic' || errorPath?.startsWith('logic.')
    ? 'logic'
    : 'options'
}

export function normalizeFormSaveErrors(validationResponse, properties = [], computedVariables = []) {
  const fieldGroupsByIndex = new Map()
  const computedVariableGroupsByIndex = new Map()
  const generalErrorsByKey = new Map()
  const responseErrors = validationResponse?.errors

  if (responseErrors && typeof responseErrors === 'object' && !Array.isArray(responseErrors)) {
    Object.entries(responseErrors).forEach(([key, rawMessages]) => {
      const messages = normalizeMessages(rawMessages)
      if (messages.length === 0) {
        return
      }

      const propertyMatch = key.match(/^properties\.(\d+)(?:\.(.+))?$/)
      if (propertyMatch) {
        const fieldIndex = Number(propertyMatch[1])
        const errorPath = propertyMatch[2] || null
        const field = properties[fieldIndex]

        if (!fieldGroupsByIndex.has(fieldIndex)) {
          fieldGroupsByIndex.set(fieldIndex, {
            key: `property:${fieldIndex}`,
            fieldIndex,
            fieldId: field?.id ?? null,
            fieldName: field?.name || `Field ${fieldIndex + 1}`,
            fieldType: field?.type ?? null,
            messages: [],
            targetTab: fieldTargetTab(errorPath),
            canNavigate: isEditableItem(field),
          })
        } else if (fieldTargetTab(errorPath) === 'logic') {
          fieldGroupsByIndex.get(fieldIndex).targetTab = 'logic'
        }

        addUniqueMessages(fieldGroupsByIndex.get(fieldIndex).messages, messages)
        return
      }

      const computedVariableMatch = key.match(/^computed_variables\.(\d+)(?:\.(.+))?$/)
      if (computedVariableMatch) {
        const variableIndex = Number(computedVariableMatch[1])
        const variable = computedVariables[variableIndex]

        if (!computedVariableGroupsByIndex.has(variableIndex)) {
          computedVariableGroupsByIndex.set(variableIndex, {
            key: `computed-variable:${variableIndex}`,
            variableIndex,
            variableId: variable?.id ?? null,
            variableName: variable?.name || `Computed variable ${variableIndex + 1}`,
            messages: [],
            canNavigate: isEditableItem(variable),
          })
        }

        addUniqueMessages(computedVariableGroupsByIndex.get(variableIndex).messages, messages)
        return
      }

      if (!generalErrorsByKey.has(key)) {
        generalErrorsByKey.set(key, {
          key,
          label: humanizeErrorKey(key),
          messages: [],
        })
      }

      addUniqueMessages(generalErrorsByKey.get(key).messages, messages)
    })
  }

  const fieldGroups = Array.from(fieldGroupsByIndex.values())
  const computedVariableGroups = Array.from(computedVariableGroupsByIndex.values())
  const generalErrors = Array.from(generalErrorsByKey.values())
  const issueCount = fieldGroups.length + computedVariableGroups.length + generalErrors.length

  return {
    fieldGroups,
    computedVariableGroups,
    generalErrors,
    issueCount,
    fieldCount: fieldGroups.length,
    computedVariableCount: computedVariableGroups.length,
    fallbackMessage: issueCount === 0
      ? validationResponse?.message || 'An unknown validation error occurred.'
      : null,
  }
}
