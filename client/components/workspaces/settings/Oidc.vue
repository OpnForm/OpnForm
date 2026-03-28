<template>
  <div class="space-y-4">

    <UAlert
      :icon="alertConfig.icon"
      :color="alertConfig.color"
      variant="subtle"
      :title="alertConfig.title"
      :description="alertConfig.description"
      :actions="alertConfig.actions"
    />

    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">OIDC Settings</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Configure OpenID Connect (OIDC) single sign-on for your workspace.
        </p>
      </div>

      <UButton
        v-if="canManageConnections && canAccessFeature"
        label="Add Connection"
        icon="i-heroicons-plus"
        @click="showCreateModal = true"
      />
      <UButton
        v-else-if="canManageConnections && !canAccessFeature"
        label="Add Connection"
        icon="i-heroicons-plus"
        @click="openUpgradeModal"
      />
    </div>

    <!-- Connections List -->
    <div v-if="connectionsData && connectionsData.length > 0" class="space-y-3">
      <p class="text-sm text-neutral-500 max-w-xl">
        Each connection can be tied to one verified email domain, which we use to route incoming users to the
        correct workspace when they start login. Manage multiple clients from here and toggle them on or off without
        losing their configuration details.
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <OidcConnectionCard
        v-for="connection in connectionsData"
        :key="connection.id"
          :connection="connection"
          :can-edit="canManageConnections && canAccessFeature"
          @edit="editConnection"
          @delete="deleteConnection"
          />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!isConnectionsLoading" class="text-center py-12">
      <UIcon 
        name="i-heroicons-key" 
        class="w-12 h-12 text-neutral-400 mx-auto mb-4" 
      />
      <h4 class="text-lg font-medium text-neutral-900 mb-2">
        No OIDC connections yet
      </h4>
      <p class="text-neutral-500 mb-4">
        Configure your first OIDC connection to enable single sign-on for your workspace.
      </p>
      <UButton
        v-if="canManageConnections && canAccessFeature"
        label="Add Your First Connection"
        icon="i-heroicons-plus"
        @click="showCreateModal = true"
      />
      <UButton
        v-else-if="canManageConnections && !canAccessFeature"
        label="Add Your First Connection"
        icon="i-heroicons-plus"
        @click="openUpgradeModal"
      />
    </div>

    <!-- Create/Edit Modal -->
    <OidcConnectionModal
      :model-value="showCreateModal"
      :connection="editingConnection"
            :form="connectionForm"
      :is-busy="connectionForm.busy"
      @update:model-value="showCreateModal = $event"
      @save="saveConnection"
      @cancel="cancelEdit"
    />
  </div>
</template>

<script setup>
import { useOidcConnections } from '~/composables/query/useOidcConnections'
import { getOidcRequireStateDefault, getOidcRequireStateForEdit } from "~/lib/oidc/connection-options"
import OidcConnectionCard from './OidcConnectionCard.vue'
import OidcConnectionModal from './OidcConnectionModal.vue'

const { current: workspace } = useCurrentWorkspace()
const alert = useAlert()
const { openSubscriptionModal } = useAppModals()

const workspaceId = computed(() => workspace.value?.id)

const canManageConnections = computed(() => !!workspace.value && workspace.value.is_admin)

// Check if feature is accessible (Pro required for cloud, free for self-hosted)
const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
const billingEnabled = computed(() => useFeatureFlag('billing.enabled'))
const canAccessFeature = computed(() => {
  // Self-hosted: always accessible
  if (isSelfHosted.value) {
    return true
  }
  // Cloud: requires Pro subscription
  return billingEnabled.value && workspace.value?.is_pro
})

const { connections, create, update, remove } = useOidcConnections(workspaceId)

// Allow viewing connections even without Pro (Pro only required for create/update/delete)
const { data: connectionsData, isLoading: isConnectionsLoading } = connections()

const alertConfig = computed(() => {
  // Cloud + Free: Beta alert with upgrade button
  if (!isSelfHosted.value && !canAccessFeature.value) {
    return {
      icon: 'i-heroicons-information-circle',
      color: 'info',
      title: 'Beta Feature - Pro Plan Required',
      description: 'OIDC SSO is currently in beta and requires a Pro plan. This feature will soon be part of our Enterprise plan. Upgrade now to start using it.',
      actions: [
        {
          label: 'Upgrade to Pro',
          onClick: openUpgradeModal
        }
      ]
    }
  }

  // Cloud + Paid: Warning about upcoming Enterprise plan
  if (!isSelfHosted.value && canAccessFeature.value) {
    return {
      icon: 'i-heroicons-exclamation-triangle',
      color: 'warning',
      title: 'Beta Feature - Pricing Change Coming',
      description: 'OIDC SSO is currently in beta and available on Pro plans. In the coming weeks, when we release our new pricing, this feature will move to our Enterprise plan.',
      actions: []
    }
  }

  // Self-hosted: Warning about future enterprise license requirement
  return {
    icon: 'i-heroicons-exclamation-triangle',
    color: 'warning',
    title: 'Beta Feature - Future Changes',
    description: 'OIDC SSO is currently in beta and free for self-hosted installations. Future updates of OpnForm will require an enterprise license to use this feature.',
    actions: []
  }
})

const openUpgradeModal = () => {
  openSubscriptionModal({
    modal_title: 'Upgrade to Pro to use OIDC SSO',
    modal_description: 'OIDC SSO is a Pro feature. Upgrade your plan to configure single sign-on for your workspace.'
  })
}

const showCreateModal = ref(false)
const editingConnection = ref(null)

const connectionForm = useForm({
  name: '',
  slug: '',
  issuer: '',
  client_id: '',
  client_secret: '',
  domain: '',
  enabled: true,
  options: {
    require_state: getOidcRequireStateDefault(),
    field_mappings: {
      email: '',
      name: ''
    },
    group_role_mappings: []
  }
})

// Create mutations following useWorkspaces.js pattern
const createMutation = create()
const deleteMutation = remove()

const saveConnection = () => {
  if (editingConnection.value) {
    // Update existing connection
    const updateMutation = update(editingConnection.value.id)
    connectionForm.mutate(updateMutation)
      .then(() => {
        alert.success('OIDC connection updated successfully')
        showCreateModal.value = false
        cancelEdit()
      })
      .catch((error) => {
        // Form handles validation errors automatically
        if (error.response?.status !== 422) {
          alert.error(error.response?._data?.message ?? 'Failed to update connection')
        }
      })
  } else {
    // Create new connection
    connectionForm.mutate(createMutation)
      .then(() => {
        alert.success('OIDC connection created successfully')
        showCreateModal.value = false
        connectionForm.reset()
      })
      .catch((error) => {
        // Form handles validation errors automatically
        if (error.response?.status !== 422) {
          alert.error(error.response?._data?.message ?? 'Failed to create connection')
        }
      })
  }
}

const editConnection = (connection) => {
  editingConnection.value = connection
  connectionForm.resetAndFill({
    name: connection.name,
    slug: connection.slug,
    issuer: connection.issuer,
    client_id: connection.client_id,
    client_secret: '', // Don't pre-fill secret
    enabled: connection.enabled,
    domain: connection.domain ?? '',
    options: {
      require_state: getOidcRequireStateForEdit(connection.options),
      field_mappings: {
        email: connection.options?.field_mappings?.email ?? '',
        name: connection.options?.field_mappings?.name ?? ''
      },
      group_role_mappings: connection.options?.group_role_mappings ?? []
    }
  })
  showCreateModal.value = true
}

const deleteConnection = (connection) => {
  alert.confirm(
    `Are you sure you want to delete "${connection.name}"?`,
    () => {
      deleteMutation.mutateAsync(connection.id)
        .then(() => {
          alert.success('OIDC connection deleted successfully')
        })
        .catch((error) => {
          alert.error(error.response?._data?.message ?? 'Failed to delete connection')
        })
    }
  )
}

const cancelEdit = () => {
  editingConnection.value = null
  connectionForm.reset()
  showCreateModal.value = false
}
</script>

