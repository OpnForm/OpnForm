<template>
  <PageContainer spacing="md">
    <PageSection
      title="PDF Templates"
      description="Create PDF documents from your form submissions."
    >
      <template #actions>
        <UDropdownMenu
          :items="createMenuItems"
          :content="{ side: 'bottom', align: 'end' }"
          arrow
        >
          <UButton
            color="primary"
            icon="i-heroicons-plus"
            trailing-icon="i-heroicons-chevron-down"
            :loading="uploading || creatingFromScratch"
          >
            Add template
          </UButton>
        </UDropdownMenu>
        <input
          ref="fileInput"
          type="file"
          accept=".pdf"
          class="hidden"
          @change="handleFileUpload"
        >
      </template>

      <DashboardLoadingBlock :loading="isLoading">
        <template #skeleton>
          <div class="space-y-4">
            <DashboardPanel
              v-for="i in 3"
              :key="i"
              padding="sm"
            >
              <div class="animate-pulse flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <div class="h-10 w-10 bg-neutral-200 rounded" />
                  <div>
                    <div class="h-4 w-32 bg-neutral-200 rounded mb-2" />
                    <div class="h-3 w-24 bg-neutral-200 rounded" />
                  </div>
                </div>
                <div class="h-8 w-20 bg-neutral-200 rounded" />
              </div>
            </DashboardPanel>
          </div>
        </template>

        <div
          v-if="templates.length"
          class="space-y-4"
        >
          <DashboardPanel
            v-for="template in templates"
            :key="template.id"
            padding="sm"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="h-10 w-10 bg-neutral-100 rounded flex items-center justify-center">
                  <UIcon
                    name="material-symbols:picture-as-pdf-rounded"
                    class="h-5 w-5 text-primary-600"
                  />
                </div>
                <div>
                  <h3 class="font-medium text-neutral-900">
                    {{ template.name }}
                  </h3>
                  <p class="text-sm text-neutral-500">
                    {{ template.original_filename }} •
                    {{ template.page_count }} page{{ template.page_count > 1 ? 's' : '' }} •
                    {{ template.zone_mappings?.length || 0 }} zone{{ template.zone_mappings?.length >= 1 ? 's' : '' }}
                  </p>
                </div>
              </div>
              <div class="relative z-20">
                <UDropdownMenu
                  :items="getTemplateMenuItems(template)"
                  :content="{ side: 'bottom', align: 'end' }"
                  arrow
                >
                  <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-heroicons-ellipsis-horizontal"
                    size="md"
                    :loading="deletingId === template.id"
                  />
                </UDropdownMenu>
              </div>
            </div>
          </DashboardPanel>
        </div>

        <DashboardEmptyState
          v-else
          icon="i-heroicons-document-arrow-down"
          title="No PDF templates yet"
          description="Upload a PDF template or create one from scratch, then map form fields to create customized documents from submissions."
        >
          <template #action>
            <UDropdownMenu
              :items="createMenuItems"
              :content="{ side: 'bottom', align: 'center' }"
              arrow
            >
              <UButton
                color="primary"
                icon="i-heroicons-plus"
                trailing-icon="i-heroicons-chevron-down"
                :loading="uploading || creatingFromScratch"
              >
                Add template
              </UButton>
            </UDropdownMenu>
          </template>
        </DashboardEmptyState>
      </DashboardLoadingBlock>
    </PageSection>
  </PageContainer>
</template>

<script setup>
import PageContainer from '~/components/dashboard/PageContainer.vue'
import PageSection from '~/components/dashboard/PageSection.vue'
import DashboardPanel from '~/components/dashboard/DashboardPanel.vue'
import DashboardLoadingBlock from '~/components/dashboard/states/DashboardLoadingBlock.vue'
import DashboardEmptyState from '~/components/dashboard/states/DashboardEmptyState.vue'
import { formsApi } from '~/api/forms'
import { usePdfTemplates } from '~/composables/query/forms/usePdfTemplates'

const props = defineProps({
  form: { type: Object, required: true },
})

definePageMeta({
  middleware: ['auth', 'readonly-block'],
})

useOpnSeoMeta({
  title: computed(() => props.form 
    ? `PDF Templates - ${props.form.title}`
    : 'PDF Templates'
  ),
})

const alert = useAlert()
const router = useRouter()

// Refs
const fileInput = ref(null)
const uploading = ref(false)
const creatingFromScratch = ref(false)
const deletingId = ref(null)

// Fetch templates
const { list, upload, createFromScratch, remove } = usePdfTemplates()
const { data: templatesData, isLoading } = list(() => props.form?.id)
const uploadTemplate = upload(() => props.form?.id)
const createFromScratchTemplate = createFromScratch(() => props.form?.id)
const deleteTemplate = remove(() => props.form?.id)

const templates = computed(() => templatesData.value?.data || [])

// Create menu items (Upload PDF, Create from scratch)
const createMenuItems = [
  [
    {
      label: 'Upload PDF',
      icon: 'i-heroicons-document-arrow-up',
      onClick: () => triggerUpload()
    },
    {
      label: 'Create from scratch',
      icon: 'i-heroicons-document-plus',
      onClick: () => handleCreateFromScratch()
    }
  ]
]

// Upload handling
const triggerUpload = () => {
  fileInput.value?.click()
}

const handleFileUpload = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)

    const response = await uploadTemplate.mutateAsync(formData)
    editTemplate(response.data)
    alert.success(response.message)
  } catch (error) {
    alert.error(error?.data?.message || error?.message || 'Failed to upload PDF template.')
  } finally {
    uploading.value = false
    // Reset input
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  }
}

const handleCreateFromScratch = async () => {
  creatingFromScratch.value = true
  try {
    const response = await createFromScratchTemplate.mutateAsync({})
    editTemplate(response.data)
    alert.success(response.message)
  } catch (error) {
    alert.error(error?.data?.message || error?.message || 'Failed to create PDF template.')
  } finally {
    creatingFromScratch.value = false
  }
}

// Get menu items for template dropdown
const getTemplateMenuItems = (template) => {
  return [
    [
      {
        label: 'Preview',
        icon: 'i-heroicons-eye',
        onClick: () => previewTemplate(template)
      },
      {
        label: 'Edit',
        icon: 'i-heroicons-pencil-square-20-solid',
        onClick: () => editTemplate(template)
      }
    ],
    [
      {
        label: 'Delete template',
        icon: 'i-heroicons-trash',
        onClick: () => confirmDelete(template),
        class: 'text-red-800 hover:bg-red-50 hover:text-red-600 group',
        iconClass: 'text-red-900 group-hover:text-red-800'
      }
    ]
  ]
}

// Edit template
const editTemplate = (template) => {
  router.push({
    name: 'forms-slug-pdf-editor-templateId',
    params: { slug: props.form.slug, templateId: template.id }
  })
}

// Preview template (opens PDF in new tab - works even without submissions)
const previewTemplate = async (template) => {
  try {
    const response = await formsApi.pdfTemplates.getPreviewSignedUrl(props.form.id, template.id)
    window.open(response.url, '_blank')
  } catch (error) {
    alert.error(error?.data?.message || error?.message || 'Failed to open PDF preview.')
  }
}

// Delete template
const confirmDelete = (template) => {
  alert.confirm(
    'Are you sure you want to delete this PDF template? This action cannot be undone.',
    async () => {
      deletingId.value = template.id
      try {
        const response = await deleteTemplate.mutateAsync(template.id)
        alert.success(response.message)
      } catch (error) {
        alert.error(error?.data?.message || error?.message || 'Failed to delete template.')
      } finally {
        deletingId.value = null
      }
    }
  )
}

</script>
