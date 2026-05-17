export const conditionInputComponents = {
  text: 'MentionInput',
  rich_text: 'MentionInput',
  number: 'MentionInput',
  rating: 'MentionInput',
  scale: 'MentionInput',
  slider: 'MentionInput',
  select: 'SelectInput',
  multi_select: 'SelectInput',
  date: 'DateInput',
  files: 'FileInput',
  checkbox: 'CheckboxInput',
  url: 'MentionInput',
  email: 'MentionInput',
  phone_number: 'MentionInput',
  matrix: 'MatrixInput',
  computed: 'MentionInput',
}

export function getConditionInputComponent(property) {
  return conditionInputComponents[property?.type]
}

export function getMentionFields(formProperties = [], currentProperty = null) {
  return formProperties.filter(property => property.id !== currentProperty?.id)
}

export function getMentionComputedVariables(formComputedVariables = [], currentProperty = null) {
  return formComputedVariables.filter(variable => variable.id !== currentProperty?.id)
}
