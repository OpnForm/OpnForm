<template>
  <PageContainer spacing="lg">
    <PageSection
      title="Integrations"
      description="Connect your form to third-party apps and services."
    >
      <DashboardLoadingBlock :loading="isIntegrationsLoading || !isSuccess">
        <template #skeleton>
          <div class="space-y-4">
            <IntegrationCardSkeleton />
            <IntegrationCardSkeleton />
            <IntegrationCardSkeleton />
          </div>
        </template>

        <div
          v-if="formIntegrationsList.length"
          class="space-y-4"
        >
          <IntegrationCard
            v-for="row in formIntegrationsList"
            :key="row.id"
            :integration="row"
            :form="form"
          />
        </div>
        <DashboardEmptyState
          v-else
          icon="i-heroicons-puzzle-piece-20-solid"
          title="No integrations yet"
          description="Get started by connecting your form to a third-party app below."
        />
      </DashboardLoadingBlock>
    </PageSection>

    <PageSection
      title="Add a new integration"
      description="Choose from the available integrations below to connect your form."
    >
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
    </PageSection>

    <IntegrationModal
      v-if="form && selectedIntegrationKey && selectedIntegration"
      :form="form"
      :integration="selectedIntegration"
      :integration-key="selectedIntegrationKey"
      :show="showIntegrationModal"
      @close="closeIntegrationModal"
    />
  </PageContainer>
</template>

<script setup>
import { computed } from "vue"
import PageContainer from "~/components/dashboard/PageContainer.vue"
import PageSection from "~/components/dashboard/PageSection.vue"
import DashboardLoadingBlock from "~/components/dashboard/states/DashboardLoadingBlock.vue"
import DashboardEmptyState from "~/components/dashboard/states/DashboardEmptyState.vue"
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
