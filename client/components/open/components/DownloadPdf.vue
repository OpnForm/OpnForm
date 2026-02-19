<template>
  <UModal
    v-model:open="isDownloadPDFModalOpen"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="grow w-full">
          <h3 class="text-base font-semibold leading-6 text-neutral-900 dark:text-white">
            Download PDF
          </h3>
          <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Download the PDF version of this submission
          </p>
        </div>
        <UButton color="neutral" variant="ghost" icon="i-heroicons-x-mark-20-solid" class="-my-1" @click="isDownloadPDFModalOpen = false" />
      </div>
    </template>

    <template #body>
      <div class="flow-root">
        <select-input
          v-model="pdfTemplateId"
          label="PDF template"
          :options="pdfTemplateOptions"
          help="Select the PDF template to download"
        />
        <UButton 
          color="primary" 
          variant="solid" 
          class="mt-4" 
          icon="i-material-symbols-picture-as-pdf-rounded"
          :disabled="!pdfTemplateId"
          :loading="pdfDownloading"
          @click="downloadPdf(pdfTemplateId)"
        >
          Download PDF
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { formsApi } from "~/api/forms"
import { usePdfTemplates } from '~/composables/query/forms/usePdfTemplates'

const props = defineProps({
  form: { type: Object, required: true },
  submissionId: {
    type: [Number, String],
    required: true,
  }
})

const isDownloadPDFModalOpen = ref(false)
const pdfTemplateId = ref(null)
const pdfDownloading = ref(false)
const alert = useAlert()

// Fetch PDF templates for this form
const { list } = usePdfTemplates()
const { data: templatesData } = list(() => props.form?.id)

const pdfTemplates = computed(() => templatesData.value?.data || [])

const pdfTemplateOptions = computed(() => {
  return pdfTemplates.value.map(t => ({
    name: t.name || t.original_filename,
    value: t.id
  }))
})

const downloadPdf = async (templateId = null) => {
  if (!templateId || pdfDownloading.value) return
  
  pdfDownloading.value = true
  try {
    // Get signed URL from backend
    const response = await formsApi.pdfTemplates.getSubmissionSignedUrl(
      props.form.id,
      templateId,
      props.submissionId
    )
    
    // Open signed URL in new tab to trigger download
    window.open(response.url, '_blank')
    isDownloadPDFModalOpen.value = false
  } catch (error) {
    console.error('PDF download failed:', error)
    alert.error('Failed to download PDF. Please try again.')
  } finally {
    pdfDownloading.value = false
  }
}

// Public function to handle download logic
const handleDownload = async () => {
  // Wait for templates to load if needed (with timeout)
  if (templatesData.value === undefined) {
    try {
      await Promise.race([
        new Promise(resolve => {
          const unwatch = watch(templatesData, (data) => {
            if (data !== undefined) {
              unwatch()
              resolve()
            }
          }, { immediate: true })
        }),
        new Promise(resolve => setTimeout(resolve, 2000)) // 2 second timeout
      ])
    } catch {
      // Timeout or error - continue anyway
    }
  }

  // No templates - show message
  if (pdfTemplates.value.length === 0) {
    alert.warning('Please create a PDF template first.')
    return
  }

  // Single template - download directly
  if (pdfTemplates.value.length === 1) {
    await downloadPdf(pdfTemplates.value[0].id)
    return
  }

  // Multiple templates - show modal
  isDownloadPDFModalOpen.value = true
}

// Expose function for parent components
defineExpose({
  handleDownload
})
</script>
