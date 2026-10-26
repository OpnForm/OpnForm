const specialFields = new Set(['submission_id', 'submission_date', 'form_name'])

export function getPdfDestinationFields(form) {
  return [
    ...(form?.properties || []).filter(field => !field.type?.startsWith('nf-')),
    ...(form?.computed_variables || []).map(field => ({ ...field, type: 'computed' })),
  ]
}

export function getPdfTemplateFields(template, form) {
  const fields = getPdfDestinationFields(form)
  const result = new Map()
  for (const zone of template?.zone_mappings || []) {
    if (zone.static_text !== undefined || zone.static_image !== undefined
      || !zone.field_id || specialFields.has(zone.field_id)) continue
    const field = fields.find(field => field.id === zone.field_id)
    result.set(zone.field_id, {
      id: zone.field_id,
      name: field?.name || zone.field_name || 'Removed field',
      type: field?.type,
    })
  }
  return [...result.values()]
}

export function suggestPdfFieldMappings(sourceFields, destinationFields) {
  return Object.fromEntries(sourceFields.map(source => {
    const exact = destinationFields.find(field => field.id === source.id && field.type === source.type)
    const matches = destinationFields.filter(field => field.type === source.type
      && field.name.trim().toLowerCase() === source.name.trim().toLowerCase())
    return [source.id, exact?.id || (matches.length === 1 ? matches[0].id : undefined)]
  }))
}
