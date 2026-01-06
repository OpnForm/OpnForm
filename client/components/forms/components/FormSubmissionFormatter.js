import { format, parseISO } from 'date-fns'

export class FormSubmissionFormatter {
  constructor(form, formData) {
    this.form = form
    this.formData = formData
    this.createLinks = false
    this.outputStringsOnly = false
    this.showGeneratedIds = false
    this.datesIsoFormat = false
  }

  setCreateLinks() {
    this.createLinks = true
    return this
  }

  setShowGeneratedIds() {
    this.showGeneratedIds = true
    return this
  }

  setOutputStringsOnly() {
    this.outputStringsOnly = true
    return this
  }

  setUseIsoFormatForDates() {
    this.datesIsoFormat = true
    return this
  }

  getFormattedData() {
    const formattedData = {}

    this.form.properties.forEach(field => {
      if (!this.formData[field.id] && !this.fieldGeneratesId(field)) {
        return
      }

      const value = this.formatFieldValue(field, this.formData[field.id])
      formattedData[field.id] = value
    })

    return formattedData
  }

  formatFieldValue(field, value) {
    switch (field.type) {
      case 'url':
        return this.createLinks ? `<a href="${value}">${value}</a>` : value
      case 'email':
        return this.createLinks ? `<a href="mailto:${value}">${value}</a>` : value
      case 'checkbox':
        return value ? 'Yes' : 'No'
      case 'date':
        return this.formatDateValue(field, value)
      case 'people':
        return this.formatPeopleValue(value)
      case 'multi_select':
        return this.outputStringsOnly && Array.isArray(value) ? value.join(', ') : value
      case 'relation':
        return this.formatRelationValue(value)
      default:
        return Array.isArray(value) && this.outputStringsOnly ? value.join(', ') : value
    }
  }

  formatDateValue(field, value) {
    if (this.datesIsoFormat) {
      return Array.isArray(value) 
        ? { start_date: value[0], end_date: value[1] || null }
        : value
    }

    const dateFormat = (field.date_format || 'dd/MM/yyyy') === 'dd/MM/yyyy' ? 'dd/MM/yyyy' : 'MM/dd/yyyy'
    const timeFormat = field.with_time ? (field.time_format === 24 ? 'HH:mm' : 'h:mm a') : ''
    const fullFormat = `${dateFormat}${timeFormat ? ' ' + timeFormat : ''}`

    if (Array.isArray(value)) {
      const start = format(parseISO(value[0]), fullFormat)
      const end = value[1] ? format(parseISO(value[1]), fullFormat) : null
      return end ? `${start} - ${end}` : start
    }

    return format(parseISO(value), fullFormat)
  }

  formatPeopleValue(value) {
    if (!value) return []
    const people = Array.isArray(value) ? value : [value]
    return this.outputStringsOnly ? people.map(p => p.name).join(', ') : people
  }

  formatRelationValue(value) {
    if (!value) return []
    const relations = Array.isArray(value) ? value : [value]
    const formatted = relations.map(r => r.title || 'Untitled')
    return this.outputStringsOnly ? formatted.join(', ') : formatted
  }

  fieldGeneratesId(field) {
    return this.showGeneratedIds && (field.generates_auto_increment_id || field.generates_uuid)
  }
}
