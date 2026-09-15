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
      <UModal v-model:open="isObsoleteZonesModalOpen" :ui="{ content: 'sm:max-w-lg' }">
        <template #header>
          <div>
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">
              Obsolete PDF field mappings
            </h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
              These mappings point to fields that are no longer present in this form and can leave PDF output blank.
            </p>
          </div>
        </template>

        <template #body>
          <ul class="space-y-2">
            <li
              v-for="zone in obsoleteFieldZones"
              :key="zone.id"
              class="rounded-md border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700"
            >
              <p class="font-medium text-neutral-900 dark:text-white">
                {{ pdfStore.getObsoleteZoneLabel(zone) }}
              </p>
              <p class="mt-0.5 text-neutral-500 dark:text-neutral-400">
                Page {{ zone.page }}
              </p>
            </li>
          </ul>
        </template>

        <template #footer>
          <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <UButton color="neutral" variant="ghost" @click="keepAndReviewObsoleteZones">
              Keep and review
            </UButton>
            <UButton color="primary" @click="removeObsoleteZones">
              Remove obsolete zones
            </UButton>
          </div>
        </template>
      </UModal>

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
          ref="centerScrollContainer"
          class="flex-1 overflow-x-auto overflow-y-auto pdf-editor-scroll-container"
          @click.self="pdfStore.setSelectedZone(null)"
        >
          <PdfZoneEditor />
        </div>

        <PdfRightSidebar />
      </div>
    </template>
  </div>
</template>

<script setup>
import { usePdfTemplates } from '~/composables/query/forms/usePdfTemplates'
import PdfEditorNavbar from '~/components/open/pdf-editor/PdfEditorNavbar.vue'
import PdfLeftSidebar from '~/components/open/pdf-editor/PdfLeftSidebar.vue'
import PdfRightSidebar from '~/components/open/pdf-editor/PdfRightSidebar.vue'
import PdfZoneEditor from '~/components/open/pdf-editor/PdfZoneEditor.vue'

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
const { detail, update } = usePdfTemplates()
const { data: templateData, isLoading: templateLoading, error } = detail(
  () => form.value?.id,
  () => templateId
)
const updateTemplate = update(
  () => form.value?.id,
  () => templateId
)

const isLoading = computed(() => formLoading.value || templateLoading.value)
const isObsoleteZonesModalOpen = ref(false)
const hasCheckedObsoleteZones = ref(false)

// Initialize store from template and form
watch([() => templateData.value?.data, form, isLoading], ([t, f, loading]) => {
  if (t) {
    pdfStore.set(t)
  }
  if (f) {
    pdfStore.setForm(f)
  }
  if (!loading && t && f && !hasCheckedObsoleteZones.value) {
    hasCheckedObsoleteZones.value = true
    isObsoleteZonesModalOpen.value = pdfStore.obsoleteFieldZones.length > 0
  }
}, { immediate: true })

const { obsoleteFieldZones } = storeToRefs(pdfStore)

const keepAndReviewObsoleteZones = () => {
  isObsoleteZonesModalOpen.value = false
}

const removeObsoleteZones = () => {
  pdfStore.removeObsoleteFieldZones()
  isObsoleteZonesModalOpen.value = false
}

// Cleanup on unmount
onUnmounted(() => {
  pdfStore.reset()
})

// Store state bindings using storeToRefs for reactivity
const { 
  content: pdfTemplate
} = storeToRefs(pdfStore)

// Save template
const saveTemplate = async () => {
  if (!form.value?.id || pdfStore.saving) return
  
  pdfStore.setSaving(true)
  try {
    const response = await updateTemplate.mutateAsync(pdfStore.getSaveData())
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
      return next()
    }
    return next(false)
  }

  return next()
})
</script>
