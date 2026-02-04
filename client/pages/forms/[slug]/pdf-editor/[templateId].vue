<template>
  <div class="h-screen flex flex-col bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-4">
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-heroicons-arrow-left"
          @click="goBack"
        >
          Back
        </UButton>
        <div class="h-6 w-px bg-gray-300 dark:bg-gray-600" />
        <div>
          <input
            v-model="templateName"
            type="text"
            class="text-lg font-semibold bg-transparent border-none focus:ring-0 p-0 text-gray-900 dark:text-white"
            placeholder="Template name"
          >
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ template?.original_filename }} •
            {{ template?.page_count }} page{{ template?.page_count > 1 ? 's' : '' }} •
            {{ zoneMappings.length || 0 }} zone{{ zoneMappings.length > 1 ? 's' : '' }}
          </p>
        </div>
      </div>

      <!-- Page Navigation -->
      <div
        v-if="template?.page_count > 1"
        class="flex items-center gap-2"
      >
        <UButton
          icon="i-heroicons-chevron-left"
          variant="ghost"
          size="sm"
          :disabled="currentPage === 1"
          @click="currentPage--"
        />
        <span class="text-sm text-gray-600 dark:text-gray-400 min-w-[80px] text-center">
          Page {{ currentPage }} of {{ template.page_count }}
        </span>
        <UButton
          icon="i-heroicons-chevron-right"
          variant="ghost"
          size="sm"
          :disabled="currentPage === template.page_count"
          @click="currentPage++"
        />
      </div>

      <div class="flex items-center gap-3">
        <UButton
          color="neutral"
          variant="soft"
          icon="i-heroicons-eye"
          @click="previewPdf"
        >
          Preview
        </UButton>
        <UButton
          color="primary"
          icon="i-heroicons-check"
          :loading="saving"
          @click="saveTemplate"
        >
          Save
        </UButton>
      </div>
    </header>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex-1 flex items-center justify-center"
    >
      <Loader class="h-8 w-8 text-blue-600" />
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex-1 flex items-center justify-center"
    >
      <UAlert
        icon="i-heroicons-exclamation-triangle"
        color="error"
        variant="soft"
        title="Error loading template"
        :description="error.message"
      />
    </div>

    <!-- Editor Layout -->
    <div
      v-else
      class="flex-1 flex overflow-hidden"
    >
      <!-- Left: PDF Preview -->
      <div
        class="flex-1 overflow-auto p-6"
        @click.self="selectedZoneId = null"
      >
        <PdfZoneEditor
          v-model="zoneMappings"
          v-model:current-page="currentPage"
          :template="template"
          :form="form"
          :selected-zone-id="selectedZoneId"
          @zone-select="selectedZoneId = $event"
        />
      </div>

      <!-- Right: Sidebar -->
      <div class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
        <!-- Add Zone -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="relative">
            <UButton
              color="primary"
              variant="soft"
              icon="i-heroicons-plus"
              block
              @click="showAddZonePopover = !showAddZonePopover"
            >
              Add Zone
            </UButton>
            
            <!-- Field Selection Popover -->
            <div
              v-if="showAddZonePopover"
              class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-10"
            >
              <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                  Select field to map
                </p>
              </div>
              <div class="max-h-64 overflow-y-auto p-1">
                <!-- Form fields -->
                <template v-if="formFields.length">
                  <p class="text-xs text-gray-400 dark:text-gray-500 px-2 py-1.5 font-medium">
                    Form Fields
                  </p>
                  <button
                    v-for="field in formFields"
                    :key="field.id"
                    class="w-full text-left px-3 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    @click="addZoneWithField(field)"
                  >
                    {{ field.name }}
                  </button>
                </template>
                
                <!-- Special fields -->
                <p class="text-xs text-gray-400 dark:text-gray-500 px-2 py-1.5 font-medium mt-1">
                  Special Fields
                </p>
                <button
                  v-for="field in specialFields"
                  :key="field.id"
                  class="w-full text-left px-3 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-400"
                  @click="addZoneWithField(field)"
                >
                  {{ field.name }}
                </button>
                
                <!-- Static text -->
                <div class="border-t border-gray-100 dark:border-gray-700 mt-1 pt-1">
                  <button
                    class="w-full text-left px-3 py-2 text-sm rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors text-blue-600 dark:text-blue-400 flex items-center gap-2"
                    @click="addStaticTextZone"
                  >
                    <UIcon name="i-heroicons-pencil" class="w-4 h-4" />
                    Static Text
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Zones List -->
        <div class="flex-1 overflow-y-auto">
          <!-- Zone Properties (when selected) -->
          <div
            v-if="selectedZone"
            class="p-4 space-y-4"
          >
            <div class="flex items-center justify-between">
              <h3 class="font-medium text-gray-900 dark:text-white text-sm">
                Zone Properties
              </h3>
              <UButton
                color="error"
                variant="ghost"
                icon="i-heroicons-trash"
                size="xs"
                @click="deleteSelectedZone"
              />
            </div>

            <!-- Field/Static Text -->
            <TextAreaInput
              v-if="selectedZone.static_text !== undefined"
              v-model="selectedZone.static_text"
              name="static_text"
              label="Static Text"
              placeholder="Enter text..."
              size="sm"
            />
            <SelectInput
              v-else
              v-model="selectedZone.field_id"
              name="field_id"
              label="Mapped Field"
              :options="fieldOptions"
              size="sm"
            />

            <!-- Font Size -->
            <TextInput
              v-model="selectedZone.font_size"
              name="font_size"
              label="Font Size (px)"
              native-type="number"
              :min="6"
              :max="72"
              size="sm"
              class="mt-4"
            />

            <!-- Font Color -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Font Color
              </label>
              <div class="flex items-center gap-2">
                <input
                  v-model="selectedZone.font_color"
                  type="color"
                  class="h-9 w-9 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5"
                >
                <TextInput
                  v-model="selectedZone.font_color"
                  name="font_color"
                  placeholder="#000000"
                  size="sm"
                  :hide-field-name="true"
                  wrapper-class="flex-1"
                />
              </div>
            </div>
          </div>

          <!-- No Zone Selected / Zones List -->
          <div v-else class="p-4">
            <div
              v-if="zoneMappings.length === 0"
              class="text-center py-8"
            >
              <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                <UIcon name="i-heroicons-cursor-arrow-ripple" class="w-6 h-6 text-gray-400" />
              </div>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                No zones yet
              </p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Click "Add Zone" to map form fields to PDF locations
              </p>
            </div>
            
            <!-- Zones list -->
            <div v-else class="space-y-2">
              <div
                v-for="zone in currentPageZones"
                :key="zone.id"
                class="p-3 rounded-lg border transition-colors cursor-pointer"
                :class="[
                  selectedZoneId === zone.id
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                ]"
                @click="selectedZoneId = zone.id"
              >
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  {{ getZoneLabel(zone) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Page {{ zone.page }} · {{ zone.font_size }}px
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Settings Section -->
        <div class="p-4 mb-20 border-t border-gray-200 dark:border-gray-700 space-y-4 bg-gray-50 dark:bg-gray-900/50">
          <h3 class="font-medium text-gray-900 dark:text-white text-sm">
            PDF Settings
          </h3>

          <!-- Filename Pattern -->
          <TextInput
            v-model="filenamePattern"
            name="filename_pattern"
            label="Filename Pattern"
            :placeholder="DEFAULT_FILENAME_PATTERN"
            size="sm"
            help="Variables: {form_name}, {submission_id}, {date}"
          />

          <!-- Remove Branding -->
          <div class="flex items-center gap-2">
            <ToggleSwitchInput
              v-model="removeBranding"
              name="remove_branding"
              label="Remove Branding"
              help="Hide 'PDF generated with OpnForm' footer"
              :disabled="!workspace?.is_pro"
              wrapper-class="flex-1"
            />
            <span
              v-if="!workspace?.is_pro"
              class="text-xs bg-gradient-to-r from-blue-500 to-purple-500 text-white px-2 py-0.5 rounded font-medium shrink-0"
            >
              PRO
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Click outside to close popover -->
    <div
      v-if="showAddZonePopover"
      class="fixed inset-0 z-0"
      @click="showAddZonePopover = false"
    />
  </div>
</template>

<script setup>
import { formsApi } from '~/api/forms'
import PdfZoneEditor from '~/components/open/integrations/components/PdfZoneEditor.vue'
import TextInput from '~/components/forms/core/TextInput.vue'
import TextAreaInput from '~/components/forms/core/TextAreaInput.vue'
import SelectInput from '~/components/forms/core/SelectInput.vue'
import ToggleSwitchInput from '~/components/forms/core/ToggleSwitchInput.vue'
import { useQuery } from '@tanstack/vue-query'
import { generateUUID } from '~/lib/utils.js'

definePageMeta({
  layout: false,
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const alert = useAlert()
const { current: workspace } = useCurrentWorkspace()

const slug = route.params.slug
const templateId = route.params.templateId

// Get form
const { detail: formDetail } = useForms()
const { data: form, isLoading: formLoading } = formDetail(slug, {
  usePrivate: true,
  enabled: import.meta.client,
})

// Fetch template
const { data: templateData, isLoading: templateLoading, error } = useQuery({
  queryKey: ['pdf-template', computed(() => form.value?.id), templateId],
  queryFn: () => formsApi.pdfTemplates.get(form.value.id, templateId),
  enabled: computed(() => !!form.value?.id),
})

const template = computed(() => templateData.value?.data)
const isLoading = computed(() => formLoading.value || templateLoading.value)
const DEFAULT_FILENAME_PATTERN = '{form_name}-{submission_id}.pdf'

// Local state
const templateName = ref('')
const zoneMappings = ref([])
const filenamePattern = ref(DEFAULT_FILENAME_PATTERN)
const removeBranding = ref(false)
const selectedZoneId = ref(null)
const currentPage = ref(1)
const showAddZonePopover = ref(false)
const saving = ref(false)

// Initialize from template
watch(template, (t) => {
  if (t) {
    templateName.value = t.name || t.original_filename
    zoneMappings.value = t.zone_mappings || []
    filenamePattern.value = t.filename_pattern || DEFAULT_FILENAME_PATTERN
    removeBranding.value = t.remove_branding || false
  }
}, { immediate: true })

// Check for unsaved changes
const hasUnsavedChanges = computed(() => {
  if (!template.value) return false
  const t = template.value
  
  // Compare name
  const originalName = t.name || t.original_filename
  if (templateName.value !== originalName) return true
  
  // Compare filename pattern
  const originalPattern = t.filename_pattern || DEFAULT_FILENAME_PATTERN
  if (filenamePattern.value !== originalPattern) return true
  
  // Compare branding
  if (removeBranding.value !== (t.remove_branding || false)) return true
  
  // Compare zone mappings
  const originalZones = JSON.stringify(t.zone_mappings || [])
  const currentZones = JSON.stringify(zoneMappings.value)
  if (currentZones !== originalZones) return true
  
  return false
})

// Form fields
const formFields = computed(() => {
  if (!form.value?.properties) return []
  return form.value.properties
    .filter(p => !p.hidden)
    .map(p => ({
      id: p.id,
      name: p.name,
      type: p.type
    }))
})

// Special fields
const specialFields = [
  { id: 'submission_id', name: 'Submission ID' },
  { id: 'submission_date', name: 'Submission Date' },
  { id: 'form_name', name: 'Form Name' },
]

// Combined field options for SelectInput
const fieldOptions = computed(() => {
  const formOptions = formFields.value.map(f => ({ name: f.name, value: f.id }))
  const specialOptions = specialFields.map(f => ({ name: f.name, value: f.id }))
  return [...formOptions, ...specialOptions]
})

// Current page zones
const currentPageZones = computed(() => {
  return zoneMappings.value.filter(z => z.page === currentPage.value)
})

// Selected zone
const selectedZone = computed(() => {
  if (!selectedZoneId.value) return null
  return zoneMappings.value.find(z => z.id === selectedZoneId.value)
})

// Get zone label
const getZoneLabel = (zone) => {
  if (zone.static_text !== undefined) {
    const text = zone.static_text || 'Empty text'
    return text.length > 20 ? text.substring(0, 20) + '...' : text
  }
  const field = [...formFields.value, ...specialFields].find(f => f.id === zone.field_id)
  return field?.name || zone.field_id || 'Unmapped'
}

// Add zone with field
const addZoneWithField = (field) => {
  const newZone = {
    id: generateUUID(),
    page: currentPage.value,
    x: 10,
    y: 10 + (currentPageZones.value.length * 8), // Offset each new zone
    width: 30,
    height: 5,
    field_id: field.id,
    font_size: 12,
    font_color: '#000000',
  }
  zoneMappings.value = [...zoneMappings.value, newZone]
  selectedZoneId.value = newZone.id
  showAddZonePopover.value = false
}

// Add static text zone
const addStaticTextZone = () => {
  const newZone = {
    id: generateUUID(),
    page: currentPage.value,
    x: 10,
    y: 10 + (currentPageZones.value.length * 8),
    width: 30,
    height: 5,
    static_text: '',
    font_size: 12,
    font_color: '#000000',
  }
  zoneMappings.value = [...zoneMappings.value, newZone]
  selectedZoneId.value = newZone.id
  showAddZonePopover.value = false
}

// Delete selected zone
const deleteSelectedZone = () => {
  if (!selectedZoneId.value) return
  zoneMappings.value = zoneMappings.value.filter(z => z.id !== selectedZoneId.value)
  selectedZoneId.value = null
}

// Save template
const saveTemplate = async () => {
  if (!form.value?.id || saving.value) return
  
  saving.value = true
  try {
    const response = await formsApi.pdfTemplates.update(form.value.id, templateId, {
      name: templateName.value,
      zone_mappings: zoneMappings.value,
      filename_pattern: filenamePattern.value,
      remove_branding: removeBranding.value,
    })
    alert.success(response.message)
    goBack()
  } catch (error) {
    const message = error?.data?.message || error?.message || 'Failed to save template.'
    alert.error(message)
  } finally {
    saving.value = false
  }
}

// Preview PDF
const previewPdf = () => {
  if (hasUnsavedChanges.value) {
    alert.warning('You have unsaved changes. Please save changes before previewing.')
    return
  }

  // Open preview in new tab
  window.open(formsApi.pdfTemplates.getPreviewUrl(form.value.id, templateId), '_blank')
}

// Go back
const goBack = () => {
  if(hasUnsavedChanges.value) {
    alert.warning('You have unsaved changes. Please save changes before going back.')
    return
  }
  
  router.push({
    name: 'forms-slug-show-pdf-templates',
    params: { slug }
  })
}

// SEO
useOpnSeoMeta({
  title: computed(() => templateName.value 
    ? `Edit PDF Template - ${templateName.value}`
    : 'Edit PDF Template'
  ),
})
</script>
