<template>
  <SettingsModal
    v-model="isOpen"
    v-model:active-tab="localActiveTab"
    @close="closeModal"
  >
    <!-- Settings Pages - Auto-register themselves -->
    <SettingsModalPage
      id="account"
      label="Account"
      icon="i-heroicons-user"
    >
      <LazyUsersSettingsAccount />
    </SettingsModalPage>

    <SettingsModalPage
      id="security"
      label="Security"
      icon="i-heroicons-shield-check"
    >
      <LazyUsersSettingsSecurity />
    </SettingsModalPage>

    <SettingsModalPage
      v-if="workspace && !workspace.is_readonly"
      id="connections"
      label="Connections"
      icon="i-heroicons-link"
    >
      <LazyUsersSettingsConnections />
    </SettingsModalPage>

    <SettingsModalPage
      v-if="workspace && !workspace.is_readonly"
      id="access-tokens"
      label="Access Tokens"
      icon="i-heroicons-key"
    >
      <LazyUsersSettingsAccessTokens />
    </SettingsModalPage>

    <SettingsModalPage
      v-if="user && (user.has_customer_id || user.active_license)"
      id="billing"
      label="Billing"
      icon="i-heroicons-credit-card"
    >
      <LazyUsersSettingsBilling />
    </SettingsModalPage>
  </SettingsModal>
</template>

<script setup>
import { computed } from 'vue'
import SettingsModal from '~/components/pages/settings/SettingsModal.vue'
import SettingsModalPage from '~/components/pages/settings/SettingsModalPage.vue'

const emit = defineEmits(['update:activeTab'])

const props = defineProps({
  activeTab: {
    type: String,
    default: null
  }
})

const { current: workspace } = useCurrentWorkspace()
const { data: user } = useAuth().user()

// Modal state is now derived from the presence of an active tab
const isOpen = computed({
  get: () => !!props.activeTab,
  set: (value) => {
    // When the modal is closed (e.g., via v-model), we signal this by nullifying the active tab.
    if (!value) {
      emit('update:activeTab', null)
    }
  }
})

// Two-way binding for the active tab with the parent
const localActiveTab = computed({
  get: () => props.activeTab,
  set: (value) => emit('update:activeTab', value)
})

// The @close event from SettingsModal triggers this
const closeModal = () => {
  emit('update:activeTab', null)
}
</script> 