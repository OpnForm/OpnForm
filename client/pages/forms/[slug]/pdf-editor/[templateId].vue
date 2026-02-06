<template>
  <div class="h-screen flex flex-col bg-gray-50 dark:bg-gray-900">
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

    <!-- Editor Layout (only when loaded) -->
    <template v-else>
      <PdfEditorNavbar
        @go-back="goBack"
        @save-pdf-template="saveTemplate"
      >
        <template #before-save>
          <slot name="before-save" />
        </template>
      </PdfEditorNavbar>

      <div class="flex-1 flex overflow-hidden">
        <PdfLeftSidebar />

        <div
          class="flex-1 overflow-auto"
          @click.self="pdfStore.setSelectedZone(null)"
        >
          <PdfZoneEditor />
        </div>

        <PdfRightSidebar />
      </div>

      <!-- Click outside to close popover -->
      <div
        v-if="showAddZonePopover"
        class="fixed inset-0 z-0"
        @click="pdfStore.setShowAddZonePopover(false)"
      />
    </template>
  </div>
</template>

<script setup>
import { formsApi } from '~/api/forms'
import PdfEditorNavbar from '~/components/open/pdf-editor/PdfEditorNavbar.vue'
import PdfLeftSidebar from '~/components/open/pdf-editor/PdfLeftSidebar.vue'
import PdfRightSidebar from '~/components/open/pdf-editor/PdfRightSidebar.vue'
import PdfZoneEditor from '~/components/open/pdf-editor/PdfZoneEditor.vue'
import { useQuery } from '@tanstack/vue-query'

definePageMeta({
  layout: false,
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const alert = useAlert()
const pdfStore = useWorkingPdfStore()

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

const isLoading = computed(() => formLoading.value || templateLoading.value)

// Initialize store from template and form
watch([() => templateData.value?.data, form], ([t, f]) => {
  if (t) {
    pdfStore.set(t)
  }
  if (f) {
    pdfStore.setForm(f)
  }
}, { immediate: true })

// Cleanup on unmount
onUnmounted(() => {
  pdfStore.reset()
})

// Store state bindings using storeToRefs for reactivity
const { 
  content: pdfTemplate,
  showAddZonePopover,
} = storeToRefs(pdfStore)

// Save template
const saveTemplate = async () => {
  if (!form.value?.id || pdfStore.saving) return
  
  pdfStore.setSaving(true)
  try {
    const response = await formsApi.pdfTemplates.update(form.value.id, templateId, pdfStore.getSaveData())
    pdfStore.markSaved()
    alert.success(response.message)
    goBack()
  } catch (err) {
    const message = err?.data?.message || err?.message || 'Failed to save template.'
    alert.error(message)
  } finally {
    pdfStore.setSaving(false)
  }
}

// Go back
const goBack = () => {  
  router.push({
    name: 'forms-slug-show-pdf-templates',
    params: { slug }
  })
}

// SEO
useOpnSeoMeta({
  title: computed(() => pdfTemplate.value?.name 
    ? `Edit PDF Template - ${pdfTemplate.value.name}`
    : 'Edit PDF Template'
  ),
})

onBeforeRouteLeave((to, from, next) => {
  if (pdfStore.hasUnsavedChanges) {
      if (window.confirm('Changes you made may not be saved. Are you sure want to leave?')) {
        window.onbeforeunload = null
        next()
      } else {
        next(false)
      }
    }
  next()
})
</script>
