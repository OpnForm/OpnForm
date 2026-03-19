import { defineStore } from "pinia"
import clonedeep from "clone-deep"
import { generateUUID } from "../lib/utils.js"

const DEFAULT_FILENAME_PATTERN = '<span mention="true" mention-field-id="form_name" mention-field-name="Form Name" mention-fallback="" contenteditable="false" class="mention-item">Form Name</span>-<span mention="true" mention-field-id="submission_id" mention-field-name="Submission ID" mention-fallback="" contenteditable="false" class="mention-item">Submission ID</span>'
const DEFAULT_ZOOM_SCALE = 1.5
const MIN_ZOOM_SCALE = 0.5
const MAX_ZOOM_SCALE = 3
const ZOOM_STEP = 0.25
const PAGE_TYPE_SOURCE = 'source'
const PAGE_TYPE_BLANK = 'blank'

const createDefaultManifest = (pageCount = 1) => {
  const count = Number(pageCount) > 0 ? Number(pageCount) : 1
  return Array.from({ length: count }, (_, i) => ({
    id: generateUUID(),
    type: PAGE_TYPE_SOURCE,
    source_page: i + 1,
  }))
}

export const useWorkingPdfStore = defineStore("working_pdf", {
  state: () => ({
    content: null,
    originalTemplate: null,
    form: null,
    selectedZoneId: null,
    lastAddedZoneId: null,
    currentPage: 1,
    zoomScale: DEFAULT_ZOOM_SCALE,
    saving: false,
  }),

  getters: {
    pageManifest() {
      if (!this.content) return []
      if (Array.isArray(this.content.page_manifest) && this.content.page_manifest.length > 0) {
        return this.content.page_manifest
      }
      return createDefaultManifest(this.content.page_count || 1)
    },

    pageList() {
      const n = this.pageManifest.length
      if (n <= 0) return []
      return Array.from({ length: n }, (_, i) => i + 1)
    },

    hasUnsavedChanges() {
      if (!this.content || !this.originalTemplate) return false
      return JSON.stringify(this.content) !== JSON.stringify(this.originalTemplate)
    },

    currentPageZones() {
      if (!this.content?.zone_mappings) return []
      return this.content.zone_mappings.filter(z => z.page === this.currentPage)
    },

    zonesForPage() {
      return (pageNum) => {
        if (!this.content?.zone_mappings) return []
        return this.content.zone_mappings.filter(z => z.page === pageNum)
      }
    },

    selectedZone() {
      if (!this.selectedZoneId || !this.content?.zone_mappings) return null
      return this.content.zone_mappings.find(z => z.id === this.selectedZoneId)
    },

    formFields() {
      if (!this.form?.properties) return []
      return this.form.properties
        .filter(p => !p.hidden && !p.type.startsWith('nf-'))
        .map(p => ({
          id: p.id,
          name: p.name,
          type: p.type
        }))
    },

    specialFields() {
      return [
        { id: 'submission_id', name: 'Submission ID' },
        { id: 'submission_date', name: 'Submission Date' },
        { id: 'form_name', name: 'Form Name' },
      ]
    },

    fieldOptions() {
      const formOptions = this.formFields.map(f => ({ name: f.name, value: f.id }))
      const specialOptions = this.specialFields.map(f => ({ name: f.name, value: f.id }))
      return [...formOptions, ...specialOptions]
    },

    defaultFilenamePattern() {
      return DEFAULT_FILENAME_PATTERN
    }
  },

  actions: {
    set(template) {
      if (!template) return

      const normalizedManifest = Array.isArray(template.page_manifest) && template.page_manifest.length > 0
        ? template.page_manifest.map((entry, index) => ({
          id: entry.id || generateUUID(),
          type: entry.type === PAGE_TYPE_BLANK ? PAGE_TYPE_BLANK : PAGE_TYPE_SOURCE,
          source_page: entry.type === PAGE_TYPE_BLANK ? null : Number(entry.source_page ?? index + 1),
        }))
        : createDefaultManifest(template.page_count || 1)

      const normalizedTemplate = {
        ...template,
        name: template.name || template.original_filename || '',
        zone_mappings: template.zone_mappings || [],
        filename_pattern: template.filename_pattern || DEFAULT_FILENAME_PATTERN,
        remove_branding: template.remove_branding || false,
        page_manifest: normalizedManifest,
        page_count: normalizedManifest.length,
      }

      this.content = clonedeep(normalizedTemplate)
      this.originalTemplate = clonedeep(normalizedTemplate)
      this.currentPage = 1
      this.selectedZoneId = null
      this.lastAddedZoneId = null
      this.zoomScale = DEFAULT_ZOOM_SCALE
      this.syncZonePageReferences()
    },

    setForm(form) {
      this.form = form
    },

    setCurrentPage(page) {
      this.currentPage = page
    },

    setSelectedZone(zoneId) {
      this.selectedZoneId = zoneId
    },

    clearLastAddedZone() {
      this.lastAddedZoneId = null
    },

    setSaving(saving) {
      this.saving = saving
    },

    setZoomScale(scale) {
      const nextScale = Number(scale)
      if (!Number.isFinite(nextScale)) return
      this.zoomScale = Math.min(MAX_ZOOM_SCALE, Math.max(MIN_ZOOM_SCALE, nextScale))
    },

    zoomIn() {
      this.setZoomScale(this.zoomScale + ZOOM_STEP)
    },

    zoomOut() {
      this.setZoomScale(this.zoomScale - ZOOM_STEP)
    },

    resetZoom() {
      this.zoomScale = DEFAULT_ZOOM_SCALE
    },

    addZone(zone) {
      if (!this.content) return
      this.content.zone_mappings = [...this.content.zone_mappings, zone]
    },

    removeZone(zoneId) {
      if (!this.content?.zone_mappings) return
      this.content.zone_mappings = this.content.zone_mappings.filter(z => z.id !== zoneId)
      if (this.selectedZoneId === zoneId) {
        this.selectedZoneId = null
      }
    },

    addZoneWithField(field = null, staticFieldKey = null) {
      const currentEntry = this.pageManifest[this.currentPage - 1]
      if (!currentEntry) return

      const baseZone = {
        id: generateUUID(),
        page: this.currentPage,
        page_id: currentEntry.id,
        x: 10,
        y: 10 + (this.currentPageZones.length * 8),
        width: 30,
        height: 5,
        font_size: 12,
        font_color: '#000000',
      }
      const newZone = field ? { ...baseZone, field_id: field.id } : { ...baseZone, [staticFieldKey]: '' }
      this.addZone(newZone)
      this.selectedZoneId = newZone.id
      this.lastAddedZoneId = newZone.id
    },

    deleteSelectedZone() {
      if (this.selectedZoneId) {
        this.removeZone(this.selectedZoneId)
      }
    },

    getZoneLabel(zone) {
      if (zone.static_text !== undefined) return 'Static Text'
      if (zone.static_image !== undefined) return 'Image'
      const allFields = [...this.formFields, ...this.specialFields]
      const field = allFields.find(f => f.id === zone.field_id)
      return field?.name || zone.field_id || 'Unmapped'
    },

    addPageAfter(afterPageNum) {
      if (!this.content) return
      const after = Number(afterPageNum)
      if (!Number.isInteger(after) || after < 0) return

      const manifest = [...this.pageManifest]
      manifest.splice(after, 0, {
        id: generateUUID(),
        type: PAGE_TYPE_BLANK,
        source_page: null,
      })
      this.content.page_manifest = manifest
      this.content.page_count = manifest.length
      this.syncZonePageReferences()
      this.setCurrentPage(after + 1)
    },

    removePage(pageNum) {
      if (!this.content) return
      const num = Number(pageNum)
      if (!Number.isInteger(num) || num < 1 || num > this.pageManifest.length) return
      if (this.pageManifest.length <= 1) return

      const manifest = [...this.pageManifest]
      const removed = manifest[num - 1]
      manifest.splice(num - 1, 1)
      this.content.page_manifest = manifest
      this.content.page_count = manifest.length
      this.content.zone_mappings = (this.content.zone_mappings || []).filter((z) => z.page_id !== removed?.id)
      this.syncZonePageReferences()

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

    duplicatePage(pageNum) {
      if (!this.content) return
      const num = Number(pageNum)
      if (!Number.isInteger(num) || num < 1 || num > this.pageManifest.length) return
      const sourceEntry = this.pageManifest[num - 1]
      if (!sourceEntry) return

      const duplicatedEntry = {
        ...sourceEntry,
        id: generateUUID(),
      }
      const manifest = [...this.pageManifest]
      manifest.splice(num, 0, duplicatedEntry)
      this.content.page_manifest = manifest
      this.content.page_count = manifest.length

      const sourceZones = (this.content.zone_mappings || []).filter((zone) => zone.page_id === sourceEntry.id)
      const clonedZones = sourceZones.map((zone) => ({
        ...clonedeep(zone),
        id: generateUUID(),
        page_id: duplicatedEntry.id,
      }))

      this.content.zone_mappings = [...(this.content.zone_mappings || []), ...clonedZones]
      this.syncZonePageReferences()
      this.currentPage = num + 1
      if (clonedZones.length > 0) {
        this.selectedZoneId = clonedZones[0].id
      }
    },

    reorderPages(fromPageNum, toPageNum) {
      if (!this.content) return
      const from = Number(fromPageNum)
      const to = Number(toPageNum)
      if (!Number.isInteger(from) || !Number.isInteger(to)) return
      if (from < 1 || to < 1 || from > this.pageManifest.length || to > this.pageManifest.length) return
      if (from === to) return

      const manifest = [...this.pageManifest]
      const [moved] = manifest.splice(from - 1, 1)
      manifest.splice(to - 1, 0, moved)
      this.content.page_manifest = manifest
      this.content.page_count = manifest.length
      this.syncZonePageReferences()
      this.currentPage = to
    },

    isNewPage(logicalPageNum) {
      const logical = Number(logicalPageNum)
      if (!Number.isInteger(logical) || logical < 1) return false
      return this.pageManifest[logical - 1]?.type === PAGE_TYPE_BLANK
    },

    getPageId(logicalPageNum) {
      const logical = Number(logicalPageNum)
      if (!Number.isInteger(logical) || logical < 1) return null
      return this.pageManifest[logical - 1]?.id || null
    },

    getPageNumberById(pageId) {
      if (!pageId) return null
      const idx = this.pageManifest.findIndex((entry) => entry.id === pageId)
      return idx === -1 ? null : idx + 1
    },

    getSourcePageNumber(logicalPageNum) {
      const logical = Number(logicalPageNum)
      if (!Number.isInteger(logical) || logical < 1) return null
      const entry = this.pageManifest[logical - 1]
      if (!entry || entry.type === PAGE_TYPE_BLANK) return null
      return Number(entry.source_page) || null
    },

    syncZonePageReferences() {
      if (!this.content) return
      const manifest = this.pageManifest
      const fallbackPageId = manifest[0]?.id || null
      const indexByPageId = new Map(manifest.map((entry, idx) => [entry.id, idx + 1]))

      this.content.zone_mappings = (this.content.zone_mappings || []).map((zone) => {
        let pageId = zone.page_id
        if (!pageId || !indexByPageId.has(pageId)) {
          const byLegacyPage = Number(zone.page)
          pageId = Number.isInteger(byLegacyPage) && byLegacyPage >= 1 && byLegacyPage <= manifest.length
            ? manifest[byLegacyPage - 1]?.id
            : fallbackPageId
        }
        const logicalPage = pageId && indexByPageId.has(pageId) ? indexByPageId.get(pageId) : 1
        return {
          ...zone,
          page_id: pageId,
          page: logicalPage,
        }
      })
    },

    getSaveData() {
      if (!this.content) return null
      return {
        name: this.content.name,
        zone_mappings: this.content.zone_mappings,
        filename_pattern: this.content.filename_pattern,
        remove_branding: this.content.remove_branding,
        page_count: this.pageManifest.length,
        page_manifest: this.pageManifest,
      }
    },

    markSaved() {
      if (this.content) {
        this.originalTemplate = clonedeep(this.content)
      }
    },

    reset() {
      this.content = null
      this.originalTemplate = null
      this.form = null
      this.selectedZoneId = null
      this.lastAddedZoneId = null
      this.currentPage = 1
      this.zoomScale = DEFAULT_ZOOM_SCALE
      this.saving = false
    }
  }
})
