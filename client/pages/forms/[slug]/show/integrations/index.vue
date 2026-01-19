<template>
  <div class="p-4">
    <div class="w-full max-w-4xl mx-auto">
      <VTransition name="fade">
        <div
          v-if="isIntegrationsLoading || !isSuccess"
          class="my-6 space-y-2"
        >
          <IntegrationCardSkeleton />
          <IntegrationCardSkeleton />
          <IntegrationCardSkeleton />
        </div>
        <div
          v-else-if="formIntegrationsList.length"
          class="my-6 space-y-2"
        >
          <IntegrationCard
            v-for="row in formIntegrationsList"
            :key="row.id"
            :integration="row"
            :form="form"
          />
        </div>
        <div
          v-else
          class="text-center py-12 px-6 bg-neutral-50 dark:bg-neutral-900/50 rounded-lg border-2 border-dashed border-neutral-300 dark:border-neutral-700 mt-6"
        >
          <UIcon
            name="i-heroicons-puzzle-piece-20-solid"
            class="mx-auto h-12 w-12 text-neutral-400"
          />
          <h3 class="mt-2 text-lg font-semibold text-neutral-900 dark:text-white">
            No integrations yet
          </h3>
          <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Get started by connecting your form to a third-party app below.
          </p>
        </div>
      </VTransition>

      <h1
        id="add-integration-title"
        class="font-semibold mt-8 text-xl"
      >
        Add a new integration
      </h1>
      <div
        v-for="(section, sectionName) in sectionsList"
        :key="sectionName"
        class="mb-8"
      >
        <h3 class="text-neutral-500">
          {{ sectionName }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 mt-2">
          <IntegrationListOption
            v-for="(sectionItem, sectionItemKey) in section"
            :key="sectionItemKey"
            :integration="sectionItem"
            @select="openIntegration"
          />
        </div>
      </div>
      <IntegrationModal
        v-if="form && selectedIntegrationKey && selectedIntegration"
        :form="form"
        :integration="selectedIntegration"
        :integration-key="selectedIntegrationKey"
        :show="showIntegrationModal"
        @close="closeIntegrationModal"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue"
import IntegrationModal from "~/components/open/integrations/components/IntegrationModal.vue"
import IntegrationCard from "~/components/open/integrations/components/IntegrationCard"
import IntegrationCardSkeleton from '~/components/open/integrations/components/IntegrationCardSkeleton.vue'
import IntegrationListOption from '~/components/open/integrations/components/IntegrationListOption.vue'

const props = defineProps({
  form: { type: Object, required: true },
})

definePageMeta({
  middleware: ["auth", "readonly-block"],
})

useOpnSeoMeta({
  title: computed(() => props.form 
    ? `Form Integrations - ${props.form.title}`
    : "Form Integrations"
  ),
})

const alert = useAlert()
const route = useRoute()
const router = useRouter()

const { list, availableIntegrations, integrationsBySection } = useFormIntegrations()

// Reactive form ID for proper dependency tracking
const formId = computed(() => props.form?.id)

const { 
  data: formIntegrationsData, 
  isLoading: isIntegrationsLoading,
  isSuccess
} = list(formId, {
  enabled: import.meta.client,
})

// Get available integrations and sections from the composable
const integrations = availableIntegrations
const sectionsList = integrationsBySection

// Get form integrations list from the query data
const formIntegrationsList = computed(() => formIntegrationsData.value || [])

const showIntegrationModal = ref(false)
const selectedIntegrationKey = ref(null)
const selectedIntegration = ref(null)

// Define openIntegration first (before the watch that uses it)
const openIntegration = (itemKey) => {
  if (!itemKey || !integrations.value.has(itemKey)) {
    return alert.error("Integration not found")
  }

  const integration = integrations.value.get(itemKey)

  if (integration.coming_soon) {
    return alert.warning("This integration is not available yet")
  }

  if (integration.is_external && integration.url) {
    window.open(integration.url, '_blank')
    return
  }

  selectedIntegrationKey.value = itemKey
  selectedIntegration.value = integrations.value.get(selectedIntegrationKey.value)
  showIntegrationModal.value = true
}

// Handle integration query parameter to auto-open modal
const handleIntegrationQueryParam = () => {
  const integrationParam = route.query.integration
  if (integrationParam && integrations.value.has(integrationParam)) {
    openIntegration(integrationParam)
    // Clear the query param after opening
    router.replace({ query: {} })
  }
}

// Watch for integrations to be loaded, then check query param
watch(
  () => integrations.value.size,
  (size) => {
    if (size > 0) {
      handleIntegrationQueryParam()
    }
  },
  { immediate: true }
)

const closeIntegrationModal = () => {
  showIntegrationModal.value = false
  nextTick(() => {
    selectedIntegrationKey.value = null
    selectedIntegration.value = null
  })
}
</script>
