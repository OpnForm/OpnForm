<template>
  <div class="space-y-4">
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">SAML Settings</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Configure SAML single sign-on for your workspace.
        </p>
      </div>

      <UButton
        v-if="canManageConnections"
        label="Add Connection"
        icon="i-heroicons-plus"
        @click="openContactChat"
      />
    </div>

    <!-- Empty State -->
    <div class="text-center py-12">
      <UIcon 
        name="i-heroicons-shield-check" 
        class="w-12 h-12 text-neutral-400 mx-auto mb-4" 
      />
      <h4 class="text-lg font-medium text-neutral-900 mb-2">
        No SAML connections yet
      </h4>
      <p class="text-neutral-500 mb-6 max-w-md mx-auto">
        Configure your first SAML connection to enable single sign-on for your workspace.
      </p>
      <UButton
        v-if="canManageConnections"
        label="Add Your First Connection"
        icon="i-heroicons-plus"
        @click="openContactChat"
      />
    </div>
  </div>
</template>

<script setup>
const { current: workspace } = useCurrentWorkspace()
const { openAndShowChat } = useCrisp()

const canManageConnections = computed(() => !!workspace.value && workspace.value.is_admin)

const openContactChat = () => {
  const message = "Hi! I'd like to set up SAML SSO for my workspace. Can you help me configure it?"
  openAndShowChat(message)
}
</script>
