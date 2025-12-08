<template>
  <UModal 
    v-model:open="isOpen" 
    :dismissible="false" 
    :ui="{ content: 'sm:max-w-2xl' }"
  >
    <template #header>
      <div class="flex items-center w-full gap-4 px-2">
        <Icon
          :name="integration?.icon"
          class="text-blue-500"
          size="40px"
        />
        <h2 class="text-lg font-semibold">
          {{ integration?.name }}
          <pro-tag v-if="integration?.is_pro === true" />
        </h2>
      </div>
      <UButton
        color="neutral"
        variant="outline"
        icon="i-heroicons-question-mark-circle"
        size="sm"
        @click="openHelp"
      >
        Help
      </UButton>
    </template>

    <template #body>
      <div class="overflow-y-scroll px-2">
        <VForm size="sm">
          <Suspense v-if="integration">
            <component
              :is="component"
              v-if="component"
              :form="form"
              :integration="integration"
              :integration-data="integrationData"
            />
            <template #fallback>
              <div class="flex items-center justify-center p-8">
                <USkeleton class="h-32 w-full" />
              </div>
            </template>
          </Suspense>
        </VForm>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-center w-full gap-x-2">
        <UButton
          class="px-8"
          :loading="loading"
          @click.prevent="save"
        >
          Save
        </UButton>
        <UButton
          color="neutral"
          variant="outline"
          class="px-8"
          @click.prevent="isOpen = false"
        >
          Close
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { computed, toValue } from "vue"
import { useComponentRegistry } from "~/composables/components/useComponentRegistry"
import ProTag from "~/components/app/ProTag.vue"

const props = defineProps({
  show: { type: Boolean, required: true },
  form: { type: Object, required: true },
  integrationKey: { type: String, required: true },
  integration: { type: Object, required: true },
  formIntegrationId: { type: Number, required: false, default: null }
})

const alert = useAlert()
const emit = defineEmits(["close"])

const { createIntegration, updateIntegration } = useFormIntegrations()

// Create mutations with reactive IDs
const formId = computed(() => props.form.id)
const formIntegrationId = computed(() => props.formIntegrationId)

const createIntegrationMutation = createIntegration(formId)
const updateIntegrationMutation = updateIntegration(formId, formIntegrationId)

const loading = computed(
  () =>
    createIntegrationMutation.isPending.value ||
    updateIntegrationMutation.isPending.value,
)

// Computed property to handle show/hide logic for UModal
const isOpen = computed({
  get: () => props.show,
  set: (value) => {
    if (!value) {
      emit('close')
    }
  }
})

const crisp = useCrisp()
const openHelp = () => {
  if (props.integration && props.integration?.crisp_help_page_slug) {
    crisp.openHelpdeskArticle(props.integration?.crisp_help_page_slug)
    return
  }
  crisp.openHelpdesk()
}

// Use query composables to get form integrations
const { list } = useFormIntegrations()
const { data: formIntegrationsData } = list(computed(() => props.form.id))

// Get the specific form integration by ID
const formIntegration = computed(() => {
  if (!props.formIntegrationId || !formIntegrationsData.value) return null
  return formIntegrationsData.value.find(integration => integration.id === props.formIntegrationId)
})

const { getIntegrationComponent } = useComponentRegistry()

const component = computed(() => {
  if (!props.integration) return null
  return getIntegrationComponent(props.integration.id)
})

let integrationData = null

watch(
  () => props.integrationKey,
  () => {
    initIntegrationData()
  },
)

const initIntegrationData = () => {
  integrationData = useForm({
    integration_id: props.formIntegrationId && formIntegration.value
      ? formIntegration.value.integration_id
      : props.integrationKey,
    status: props.formIntegrationId && formIntegration.value
      ? formIntegration.value.status
      : 'active',
    data: props.formIntegrationId && formIntegration.value 
      ? formIntegration.value.data ?? {} 
      : {},
    logic: props.formIntegrationId && formIntegration.value
      ? !Array.isArray(formIntegration.value.logic) &&
        formIntegration.value.logic
        ? formIntegration.value.logic
        : null
      : null,
    oauth_id: formIntegration.value?.oauth_id ?? null,
  })
}
initIntegrationData()

const save = () => {
  if (loading.value) return

  const isUpdating = !!toValue(formIntegrationId)
  const mutation = isUpdating ? updateIntegrationMutation : createIntegrationMutation

  // Use Form's mutate which handles data extraction and error handling
  integrationData.mutate(mutation)
    .then((result) => {
      alert.success(result.message)
      emit('close')
    })
    .catch((error) => {
      try {
        alert.error(error.data.message)
      }
      catch {
        alert.error('An error occurred while saving the integration')
      }
    })
}
</script>
