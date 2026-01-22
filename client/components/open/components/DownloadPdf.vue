<template>
  <UTooltip
    v-if="pdfIntegrations.length > 0"
    text="Download PDF"
    :content="{ side: 'left' }" 
    arrow
  >
    <UButton
      size="sm"
      color="neutral"
      variant="outline"
      icon="material-symbols:picture-as-pdf-rounded"
      :loading="pdfDownloading"
      @click="isDownloadPDFModalOpen = true"
    />
  </UTooltip>

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
          v-model="pdfIntegrationId"
          label="PDF template"
          :options="pdfIntegrationOptions"
          help="Select the PDF template to download"
        />
        <UButton 
          color="primary" 
          variant="solid" 
          class="mt-4" 
          icon="i-material-symbols-picture-as-pdf-rounded"
          :disabled="!pdfIntegrationId"
          :loading="pdfDownloading"
          @click="downloadPdf"
        >
          Download PDF
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { useFormIntegrations } from "~/composables/query/forms/useFormIntegrations"
import { formsApi } from "~/api/forms"

const props = defineProps({
  form: { type: Object, required: true },
  submissionId: {
    type: Number,
    required: true,
  }
})

const isDownloadPDFModalOpen = ref(false)
const pdfIntegrationId = ref(null)
const pdfDownloading = ref(false)
const alert = useAlert()

const { list: listIntegrations } = useFormIntegrations()
const { data: integrationsData } = listIntegrations(computed(() => props.form?.id), {
  enabled: computed(() => !!props.form?.id)
})

const pdfIntegrations = computed(() => {
  const integrations = integrationsData.value || []
  return integrations.filter(i => i.integration_id === 'pdf' && i.status === 'active')
})

const pdfIntegrationOptions = computed(() => {
  return pdfIntegrations.value.map(i => ({
    name: i.data?.filename_pattern || `PDF Template ${i.id}`,
    value: i.id
  }))
})

// Auto-select first PDF integration if only one exists
watch(pdfIntegrations, (integrations) => {
  if (integrations.length > 0 && !pdfIntegrationId.value) {
    pdfIntegrationId.value = integrations[0].id
  }
}, { immediate: true })

const downloadPdf = async () => {
  if (!pdfIntegrationId.value || pdfDownloading.value) return
  
  pdfDownloading.value = true
  try {
    // Get signed URL from backend (includes token for auth)
    const response = await formsApi.pdfTemplates.getSignedUrl(
      props.form.id,
      props.submissionId,
      pdfIntegrationId.value
    )
    
    // Open signed URL in new tab to trigger download
    window.open(response.url, '_blank')
  } catch (error) {
    console.error('PDF download failed:', error)
    alert.error('Failed to download PDF. Please try again.')
  } finally {
    pdfDownloading.value = false
  }
}
</script>
