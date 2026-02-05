import { defineStore } from "pinia"
import clonedeep from "clone-deep"
import { generateUUID } from "~/lib/utils.js"

const DEFAULT_FILENAME_PATTERN = '{form_name}-{submission_id}.pdf'

export const useWorkingPdfStore = defineStore("working_pdf", {
  state: () => ({
    // Template content (editable)
    content: null,
    
    // Original template for change detection
    originalTemplate: null,
    
    // Form data
    form: null,
    
    // Editor state
    selectedZoneId: null,
    currentPage: 1,
    showAddZonePopover: false,
    
    // Save state
    saving: false,
  }),

  getters: {
    // Check for unsaved changes
    hasUnsavedChanges() {
      if (!this.content || !this.originalTemplate) return false
      return JSON.stringify(this.content) !== JSON.stringify(this.originalTemplate)
    },

    // Get zones for current page
    currentPageZones() {
      if (!this.content?.zone_mappings) return []
      return this.content.zone_mappings.filter(z => z.page === this.currentPage)
    },

    // Get selected zone object
    selectedZone() {
      if (!this.selectedZoneId || !this.content?.zone_mappings) return null
      return this.content.zone_mappings.find(z => z.id === this.selectedZoneId)
    },

    // Form fields for mapping
    formFields() {
      if (!this.form?.properties) return []
      return this.form.properties
        .filter(p => !p.hidden)
        .map(p => ({
          id: p.id,
          name: p.name,
          type: p.type
        }))
    },

    // Special fields available for mapping
    specialFields() {
      return [
        { id: 'submission_id', name: 'Submission ID' },
        { id: 'submission_date', name: 'Submission Date' },
        { id: 'form_name', name: 'Form Name' },
      ]
    },

    // Combined field options for SelectInput
    fieldOptions() {
      const formOptions = this.formFields.map(f => ({ name: f.name, value: f.id }))
      const specialOptions = this.specialFields.map(f => ({ name: f.name, value: f.id }))
      return [...formOptions, ...specialOptions]
    },

    // Default filename pattern constant
    defaultFilenamePattern() {
      return DEFAULT_FILENAME_PATTERN
    }
  },

  actions: {
    // Initialize store with template data
    set(template) {
      if (template) {
        // Normalize template data
        const normalizedTemplate = {
          ...template,
          name: template.name || template.original_filename || '',
          zone_mappings: template.zone_mappings || [],
          filename_pattern: template.filename_pattern || DEFAULT_FILENAME_PATTERN,
          remove_branding: template.remove_branding || false,
        }
        this.content = clonedeep(normalizedTemplate)
        this.originalTemplate = clonedeep(normalizedTemplate)
        this.currentPage = 1
        this.selectedZoneId = null
      }
    },

    // Set form data
    setForm(form) {
      this.form = form
    },

    // Set current page
    setCurrentPage(page) {
      this.currentPage = page
    },

    // Set selected zone
    setSelectedZone(zoneId) {
      this.selectedZoneId = zoneId
    },

    // Toggle add zone popover
    setShowAddZonePopover(show) {
      this.showAddZonePopover = show
    },

    // Set saving state
    setSaving(saving) {
      this.saving = saving
    },

    // Add a new zone
    addZone(zone) {
      if (!this.content) return
      this.content.zone_mappings = [...this.content.zone_mappings, zone]
    },

    // Remove a zone
    removeZone(zoneId) {
      if (!this.content?.zone_mappings) return
      this.content.zone_mappings = this.content.zone_mappings.filter(z => z.id !== zoneId)
      if (this.selectedZoneId === zoneId) {
        this.selectedZoneId = null
      }
    },

    // Add a new zone. Pass a field to map to that field, or omit/null for static text zone.
    addZoneWithField(field = null) {
      const baseZone = {
        id: generateUUID(),
        page: this.currentPage,
        x: 10,
        y: 10 + (this.currentPageZones.length * 8),
        width: 30,
        height: 5,
        font_size: 12,
        font_color: '#000000',
      }
      const newZone = field
        ? { ...baseZone, field_id: field.id }
        : { ...baseZone, static_text: '' }
      this.addZone(newZone)
      this.selectedZoneId = newZone.id
      this.showAddZonePopover = false
    },

    // Delete selected zone
    deleteSelectedZone() {
      if (this.selectedZoneId) {
        this.removeZone(this.selectedZoneId)
      }
    },

    // Get zone label for display
    getZoneLabel(zone) {
      if (zone.static_text !== undefined) {
        const text = zone.static_text || 'Empty text'
        return text.length > 20 ? text.substring(0, 20) + '...' : text
      }
      const allFields = [...this.formFields, ...this.specialFields]
      const field = allFields.find(f => f.id === zone.field_id)
      return field?.name || zone.field_id || 'Unmapped'
    },

    // Get data for saving
    getSaveData() {
      if (!this.content) return null
      return {
        name: this.content.name,
        zone_mappings: this.content.zone_mappings,
        filename_pattern: this.content.filename_pattern,
        remove_branding: this.content.remove_branding,
      }
    },

    // Reset store
    reset() {
      this.content = null
      this.originalTemplate = null
      this.form = null
      this.selectedZoneId = null
      this.currentPage = 1
      this.showAddZonePopover = false
      this.saving = false
    }
  }
})
