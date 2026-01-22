<template>
  <IntegrationWrapper
    v-model="props.integrationData"
    :integration="props.integration"
    :form="form"
  >
    <!-- Step 1: PDF Template Upload -->
    <div class="mt-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        PDF Template
      </label>
      
      <PdfTemplateUpload
        v-if="!selectedTemplate"
        :form-id="form.id"
        @uploaded="onTemplateUploaded"
      />
      
      <div
        v-else
        class="border border-gray-200 rounded-lg p-4 bg-gray-50"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Icon
              name="material-symbols:picture-as-pdf-rounded"
              class="w-8 h-8 text-blue-600"
            />
            <div>
              <p class="font-medium text-gray-900">
                {{ selectedTemplate.original_filename }}
              </p>
              <p class="text-sm text-gray-500">
                {{ selectedTemplate.page_count }} page{{ selectedTemplate.page_count > 1 ? 's' : '' }}
              </p>
            </div>
          </div>
          <UButton
            icon="i-heroicons-trash"
            color="error"
            variant="ghost"
            size="sm"
            @click="removeTemplate"
          />
        </div>
      </div>
    </div>

    <!-- Step 2: Zone Mapping Editor -->
    <div
      v-if="selectedTemplate"
      class="mt-6 border-t pt-6"
    >
      <div class="flex flex-col gap-6 lg:flex-row">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Map Form Fields to PDF Zones
          </label>
          <p class="text-xs text-gray-500 mb-4">
            Draw rectangles on the PDF preview to define where each form field should land.
          </p>

          <div class="border border-gray-200 rounded-lg bg-white shadow-sm">
            <PdfZoneEditor
              v-model="zoneMappings"
              :template="selectedTemplate"
              :form="form"
            />
          </div>
        </div>

      </div>
    </div>

    <!-- Filename Pattern -->
    <div
      v-if="selectedTemplate"
      class="mt-6 border-t pt-6"
    >
      <text-input
        :form="integrationData"
        name="data.filename_pattern"
        label="PDF Filename"
        help="Use {form_name}, {submission_id}, {date}, {timestamp} for dynamic values"
        placeholder="{form_name}-{submission_id}.pdf"
        required
      />
    </div>
  </IntegrationWrapper>
</template>

<script setup>
import IntegrationWrapper from "./components/IntegrationWrapper.vue"
import PdfTemplateUpload from "./components/PdfTemplateUpload.vue"
import PdfZoneEditor from "./components/PdfZoneEditor.vue"

const props = defineProps({
  integration: { type: Object, required: true },
  form: { type: Object, required: true },
  integrationData: { type: Object, required: true },
  formIntegrationId: { type: Number, required: false, default: null },
})

// Initialize data structure if needed
onMounted(() => {
  if (!props.integrationData.data) {
    props.integrationData.data = {}
  }
  if (!props.integrationData.data.filename_pattern) {
    props.integrationData.data.filename_pattern = '{form_name}-{submission_id}.pdf'
  }
  if (!props.integrationData.data.zone_mappings) {
    props.integrationData.data.zone_mappings = []
  }
})

// Selected template
const selectedTemplate = computed(() => {
  if (!props.integrationData?.data?.template_id) return null
  return props.integrationData.data._template || null
})

// Zone mappings
const zoneMappings = computed({
  get: () => props.integrationData?.data?.zone_mappings || [],
  set: (value) => {
    if (props.integrationData?.data) {
      props.integrationData.data.zone_mappings = value
    }
  }
})

const onTemplateUploaded = (template) => {
  if (props.integrationData?.data) {
    props.integrationData.data.template_id = template.id
    props.integrationData.data._template = template
    props.integrationData.data.zone_mappings = []
  }
}

const removeTemplate = () => {
  useAlert().confirm(
    'Are you sure you want to change this PDF template? All zone mappings will be lost.',
    () => {
      if (props.integrationData?.data) {
        props.integrationData.data.template_id = null
        props.integrationData.data._template = null
        props.integrationData.data.zone_mappings = []
      }
    }
  )
}
</script>
