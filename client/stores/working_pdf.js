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

    // Logical page numbers removed this session (sent on save so server can rebuild PDF)
    removedPagesForSave: [],

    // Page count from PDF file when template was loaded (never changed by add/remove until next load)
    originalPageCount: null,
  }),

  getters: {
    // Explicit list of logical page numbers 1..page_count for stable v-for
    pageList() {
      const n = this.content?.page_count || 0
      if (n <= 0) return []
      return Array.from({ length: n }, (_, i) => i + 1)
    },
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
          new_pages: template.new_pages || [],
        }
        this.content = clonedeep(normalizedTemplate)
        this.originalTemplate = clonedeep(normalizedTemplate)
        this.currentPage = 1
        this.selectedZoneId = null
        this.removedPagesForSave = []
        this.originalPageCount = template.page_count ?? (normalizedTemplate.zone_mappings?.length ? 1 : 1)
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

    // Add a blank page after the given page number (1-based). Client-side only; persisted on save.
    addPageAfter(afterPageNum) {
      if (!this.content) return
      const after = Number(afterPageNum)
      if (!Number.isInteger(after) || after < 0) return
      const insertAt = after + 1
      this.content.page_count = (this.content.page_count || 1) + 1
      this.content.new_pages = this.content.new_pages || []
      this.content.new_pages.push(insertAt)
      this.content.new_pages.sort((a, b) => a - b)
      // Renumber zones: any zone on page >= insertAt moves to page+1
      this.content.zone_mappings = (this.content.zone_mappings || []).map((z) => {
        if (z.page >= insertAt) return { ...z, page: z.page + 1 }
        return z
      })
      if (this.currentPage >= insertAt) {
        this.currentPage = this.currentPage + 1
      }
      this.setCurrentPage(insertAt)
    },

    // Remove a page (and all zones on it). Client-side only; server rebuilds PDF on save. Call after confirm.
    removePage(pageNum) {
      if (!this.content) return
      const num = Number(pageNum)
      if (!Number.isInteger(num) || num < 1) return
      const total = this.content.page_count || 1
      if (total <= 1) return
      this.removedPagesForSave = [...(this.removedPagesForSave || []), num]
      this.content.page_count = total - 1
      this.content.new_pages = (this.content.new_pages || []).filter((p) => p !== num).map((p) => (p > num ? p - 1 : p))
      this.content.zone_mappings = (this.content.zone_mappings || [])
        .filter((z) => z.page !== num)
        .map((z) => (z.page > num ? { ...z, page: z.page - 1 } : z))
      if (this.selectedZoneId) {
        const zone = this.content.zone_mappings.find((z) => z.id === this.selectedZoneId)
        if (!zone) this.selectedZoneId = null
      }
      if (this.currentPage === num) {
        this.currentPage = Math.max(1, num - 1)
      } else if (this.currentPage > num) {
        this.currentPage = this.currentPage - 1
      }
    },

    // Whether a logical page number is a new (inserted) page
    isNewPage(logicalPageNum) {
      const n = Number(logicalPageNum)
      return (this.content?.new_pages || []).includes(n)
    },

    // Map logical page (1-based) to physical page in the PDF file. Returns null if logical is a new (blank) page.
    getPhysicalPageNumber(logicalPageNum) {
      if (!this.content) return null
      const logical = Number(logicalPageNum)
      const newPages = this.content.new_pages || []
      if (newPages.includes(logical)) return null
      const orig = this.originalPageCount ?? this.content.page_count ?? 1
      let pagesToKeep = Array.from({ length: orig }, (_, i) => i + 1)
      for (const r of this.removedPagesForSave || []) {
        const idx = Number(r) - 1
        if (idx >= 0 && idx < pagesToKeep.length) {
          pagesToKeep = pagesToKeep.slice(0, idx).concat(pagesToKeep.slice(idx + 1))
        }
      }
      let physicalIndex = 0
      for (let L = 1; L < logical; L++) {
        if (!newPages.includes(L)) physicalIndex++
      }
      return pagesToKeep[physicalIndex] ?? null
    },

    // Get data for saving (new_pages/removed_pages used by server to rebuild PDF file only; not stored in DB)
    getSaveData() {
      if (!this.content) return null
      return {
        name: this.content.name,
        zone_mappings: this.content.zone_mappings,
        filename_pattern: this.content.filename_pattern,
        remove_branding: this.content.remove_branding,
        page_count: this.content.page_count,
        new_pages: this.content.new_pages || [],
        removed_pages: this.removedPagesForSave || [],
      }
    },

    // Call after a successful save so hasUnsavedChanges becomes false; clear removedPagesForSave
    markSaved() {
      if (this.content) {
        this.originalTemplate = clonedeep(this.content)
      }
      this.removedPagesForSave = []
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
      this.removedPagesForSave = []
      this.originalPageCount = null
    }
  }
})
