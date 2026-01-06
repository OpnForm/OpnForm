<template>
  <SettingsModal
    v-model="isOpen"
    v-model:active-tab="localActiveTab"
    @close="closeModal"
    v-if="workspace"
  >
    <!-- Settings Pages - Auto-register themselves -->
    <SettingsModalPage
      id="information"
      label="Information"
      icon="i-heroicons-information-circle"
    >
      <LazyWorkspacesSettingsInformation />
    </SettingsModalPage>

    <SettingsModalPage
      id="members"
      label="Members"
      icon="i-heroicons-user-group"
    >
      <LazyWorkspacesSettingsMembers />
    </SettingsModalPage>

    <SettingsModalPage
      v-if="workspace && workspace.is_admin"
      id="domains"
      label="Domains"
      icon="i-heroicons-globe-alt"
    >
      <LazyWorkspacesSettingsDomains />
    </SettingsModalPage>
    
    <SettingsModalPage
      v-if="workspace && workspace.is_admin"
      id="emails"
      label="Emails"
      icon="i-heroicons-envelope"
    >
      <LazyWorkspacesSettingsEmails />
    </SettingsModalPage>

    <SettingsModalPage
      v-if="workspace && workspace.is_admin"
      id="sso"
      label="SSO"
      icon="i-heroicons-shield-check"
    >
      <LazyWorkspacesSettingsSso />
    </SettingsModalPage>

    <SettingsModalPage
      v-if="workspace && workspace.is_admin"
      id="custom-code"
      label="Custom Code"
      icon="i-heroicons-code-bracket"
    >
      <LazyWorkspacesSettingsCustomCode />
    </SettingsModalPage>
  </SettingsModal>
</template>

<script setup>
import { computed } from 'vue'
import SettingsModal from '~/components/pages/settings/SettingsModal.vue'
import SettingsModalPage from '~/components/pages/settings/SettingsModalPage.vue'
import LazyWorkspacesSettingsSso from './sso/index.vue'
import LazyWorkspacesSettingsCustomCode from './CustomCode.vue'

const emit = defineEmits(['update:activeTab'])

const props = defineProps({
  activeTab: {
    type: String,
    default: null
  }
})

const { current: workspace } = useCurrentWorkspace()

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