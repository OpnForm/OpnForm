<template>
  <div class="p-4">
    <div class="w-full max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            PDF Templates
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Create PDF documents from your form submissions.
          </p>
        </div>
        <UButton
          color="primary"
          icon="i-heroicons-plus"
          :loading="uploading"
          @click="triggerUpload"
        >
          Upload Template
        </UButton>
        <input
          ref="fileInput"
          type="file"
          accept=".pdf"
          class="hidden"
          @change="handleFileUpload"
        >
      </div>

      <div
        v-if="isLoading"
        class="space-y-4"
      >
        <div
          v-for="i in 3"
          :key="i"
          class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
        >
          <div class="animate-pulse flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded" />
              <div>
                <div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-2" />
                <div class="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded" />
              </div>
            </div>
            <div class="h-8 w-20 bg-gray-200 dark:bg-gray-700 rounded" />
          </div>
        </div>
      </div>

      <div
        v-else-if="templates.length"
        class="space-y-4"
      >
        <div
          v-for="template in templates"
          :key="template.id"
          class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 bg-neutral-100 dark:bg-neutral-900/30 rounded flex items-center justify-center">
                <UIcon
                  name="material-symbols:picture-as-pdf-rounded"
                  class="h-5 w-5 text-primary-600 dark:text-primary-400"
                />
              </div>
              <div>
                <h3 class="font-medium text-gray-900 dark:text-white">
                  {{ template.name || template.original_filename }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ template.page_count }} page{{ template.page_count > 1 ? 's' : '' }} •
                  {{ template.zone_mappings?.length || 0 }} zone{{ template.zone_mappings?.length >= 1 ? 's' : '' }}
                </p>
              </div>
            </div>
            <div class="relative z-20">
              <UDropdownMenu
                :items="getTemplateMenuItems(template)"
                :content="{ side: 'bottom', align: 'end' }"
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
        </div>
      </div>

      <div
        v-else
        class="text-center py-12 px-6 bg-gray-50 dark:bg-gray-900/50 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700"
      >
        <UIcon
          name="i-heroicons-document-arrow-down"
          class="mx-auto h-12 w-12 text-gray-400"
        />
        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
          No PDF templates yet
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          Upload a PDF template and map form fields to create customized documents from submissions.
        </p>
        <UButton
          class="mt-4"
          color="primary"
          icon="i-heroicons-plus"
          :loading="uploading"
          @click="triggerUpload"
        >
          Upload PDF Template
        </UButton>
      </div>

      <div 
        v-if="!workspace?.is_pro"
        class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800"
      >
        <div class="flex items-start gap-3">
          <UIcon
            name="i-heroicons-information-circle"
            class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0"
          />
          <div>
            <h4 class="font-medium text-blue-900 dark:text-blue-100">
              PDF Branding
            </h4>
            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
              Free accounts include <b>"PDF generated with OpnForm"</b> footer on all pages.
              <UButton
                color="primary"
                variant="soft"
                @click="onUpgradeClick"
              >
                Upgrade to Pro
              </UButton> for removing this branding.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formsApi } from '~/api/forms'
import { useQuery } from '@tanstack/vue-query'

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
const { current: workspace } = useCurrentWorkspace()
const { openSubscriptionModal } = useAppModals()

// Refs
const fileInput = ref(null)
const uploading = ref(false)
const deletingId = ref(null)

// Fetch templates
const { data: templatesData, isLoading, refetch } = useQuery({
  queryKey: ['pdf-templates', computed(() => props.form?.id)],
  queryFn: () => formsApi.pdfTemplates.list(props.form.id),
  enabled: computed(() => !!props.form?.id),
})

const templates = computed(() => templatesData.value?.data || [])

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

    const response = await formsApi.pdfTemplates.upload(props.form.id, formData)
    editTemplate(response.data)
    alert.success(response.message)
    refetch()
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
const previewTemplate = (template) => {
  window.open(formsApi.pdfTemplates.getPreviewUrl(props.form.id, template.id), '_blank')
}

// Delete template
const confirmDelete = (template) => {
  alert.confirm(
    'Are you sure you want to delete this PDF template? This action cannot be undone.',
    async () => {
      deletingId.value = template.id
      try {
        const response = await formsApi.pdfTemplates.delete(props.form.id, template.id)
        alert.success(response.message)
        refetch()
      } catch (error) {
        alert.error(error?.data?.message || error?.message || 'Failed to delete template.')
      } finally {
        deletingId.value = null
      }
    }
  )
}

const onUpgradeClick = () => {
  openSubscriptionModal({
    modal_title: 'Upgrade to remove PDF branding'
  })
}
</script>
