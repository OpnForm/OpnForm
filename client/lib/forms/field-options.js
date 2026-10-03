export function getFieldOptions(field) {
  if (!field || !['select', 'multi_select'].includes(field.type)) return []
  const rawOptions = field[field.type]?.options ?? field.options ?? []
  return rawOptions.map(option => ({
    name: option.name,
    value: option.name,
    image: option.image || null,
  }))
}
