<template>
  <div class="p-4 flex gap-4 items-center relative border rounded-lg shadow-xs">
    <!-- Icon -->
    <div
      class="flex-shrink-0"
      :class="{
        'text-blue-500': integration.status === 'active',
        'text-neutral-400': integration.status !== 'active',
      }"
    >
      <Icon
        :name="integrationTypeInfo.icon"
        size="32px"
      />
    </div>

    <!-- Title & Status -->
    <div class="items-center truncate relative">
      <div class="font-semibold text-neutral-900 dark:text-white">
        {{ integrationTypeInfo.name }}
      </div>
    </div>

    <div class="grow truncate">
      <UBadge
          variant="subtle"
          size="sm"
          :color="integration.status  ? 'success' : 'neutral'"
          :icon="integration.status ? 'i-heroicons-play-solid' : 'i-heroicons-pause-solid'"
        >
          {{ integration.status ? "Active" : "Paused" }}
        </UBadge>
    </div>


    <!-- Actions -->
    <div class="flex items-center gap-4">
      <Suspense v-if="integrationTypeInfo?.actions_file_name">
        <component
          :is="actionsComponent"
          v-if="actionsComponent"
          :integration="integration"
          :form="form"
        />
        <template #fallback>
          <USkeleton class="h-6 w-24" />
        </template>
      </Suspense>

      <div
        v-if="loadingDelete"
        class="flex items-center justify-center w-8 h-8"
      >
        <Loader class="h-6 w-6" />
      </div>
      <UDropdownMenu
        v-else
        arrow
        :items="dropdownItems"
        :content="{ side: 'bottom', align: 'end' }"
      >
        <UButton
          color="neutral"
          variant="ghost"
          size="md"
          icon="i-heroicons-ellipsis-horizontal"
          class="hover:bg-neutral-200"
        />
      </UDropdownMenu>
    </div>

    <IntegrationModal
      v-if="form && integration && integrationTypeInfo"
      :form="form"
      :integration="integrationTypeInfo"
      :integration-key="integration.integration_id"
      :form-integration-id="integration.id"
      :show="showIntegrationModal"
      @close="showIntegrationModal = false"
    />

    <IntegrationEventsModal
      v-if="form && integration"
      :form="form"
      :form-integration-id="integration.id"
      :show="showIntegrationEventsModal"
      @close="showIntegrationEventsModal = false"
    />
  </div>
</template>

<script setup>
import { computed } from "vue"
import { useComponentRegistry } from "~/composables/components/useComponentRegistry"
import IntegrationModal from "~/components/open/integrations/components/IntegrationModal.vue"
import IntegrationEventsModal from "./IntegrationEventsModal.vue"

const props = defineProps({
  integration: {
    type: Object,
    required: true,
  },
  form: {
    type: Object,
    required: true,
  },
})

const alert = useAlert()
const { availableIntegrations, deleteIntegration } = useFormIntegrations()
const { getActionComponent } = useComponentRegistry()
const integrations = availableIntegrations
const integrationTypeInfo = computed(() =>
  integrations.value.get(props.integration.integration_id),
)

const showIntegrationModal = ref(false)
const showIntegrationEventsModal = ref(false)
const loadingDelete = ref(false)

const actionsComponent = computed(() => {
  if(integrationTypeInfo.value?.actions_file_name || false) {
    return getActionComponent(integrationTypeInfo.value.actions_file_name)
  }

  return null
})

const dropdownItems = computed(() => {
  const items = []
  const isExternal = integrationTypeInfo.value?.is_external === true
  const isEditable = integrationTypeInfo.value?.is_editable !== false

  // Edit option for non-external integrations or editable external ones
  if (!isExternal && isEditable) {
    items.push({
      label: 'Edit',
      icon: 'i-heroicons-pencil',
      onClick: () => {
        showIntegrationModal.value = true
      }
    })
  } else if (integrationTypeInfo.value?.url && isExternal) {
    items.push({
      label: `Edit on ${integrationTypeInfo.value.name}`,
      icon: 'i-heroicons-pencil',
      to: integrationTypeInfo.value.url
    })
  }

  // Past Events option
  items.push({
    label: 'Past Events',
    icon: 'i-heroicons-clock',
    onClick: () => {
      showIntegrationEventsModal.value = true
    }
  })

  // Delete option
  items.push({
    label: 'Delete Integration',
    icon: 'i-heroicons-trash',
    onClick: () => {
      deleteFormIntegration(props.integration.id)
    },
    color: 'error',
  })

  return items
})

const deleteIntegrationMutation = deleteIntegration()

const deleteFormIntegration = (integrationid) => {
  const isExternal = integrationTypeInfo.value?.is_external === true
  let msg = 'Do you really want to delete this form integration?'
  if (isExternal) {
    msg += ' This might be affect on your '+ integrationTypeInfo.value.name +' workflow.'
  }

  alert.confirm(msg, () => {
    loadingDelete.value = true
    deleteIntegrationMutation.mutateAsync({
      formId: props.form.id,
      integrationId: integrationid
    }).then(() => {
      alert.success("Integration deleted successfully!")
      loadingDelete.value = false
    }).catch((error) => {
      alert.error(error.data?.message || "Something went wrong!")
      loadingDelete.value = false
    })
  })
}
</script>
