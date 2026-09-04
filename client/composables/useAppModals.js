import { ref, watch, computed, readonly, defineAsyncComponent } from 'vue'
import { useRouteQuery } from '@vueuse/router'
import { createSharedComposable } from '@vueuse/core'
// (No need for explicit imports of auto-imported composables)

const UsersSettingsModal = defineAsyncComponent(() => import('~/components/users/settings/Modal.vue'))
const WorkspacesSettingsModal = defineAsyncComponent(() => import('~/components/workspaces/settings/Modal.vue'))
const SubscriptionModal = defineAsyncComponent(() => import('~/components/pages/pricing/SubscriptionModal.vue'))

/**
 * Factory for creating URL-synced modals.
 * It encapsulates the logic for opening, closing, and syncing state with a URL query parameter.
 * @param {object} config - Configuration object.
 * @param {object} config.component - The Vue component for the modal.
 * @param {string} config.queryParam - The URL query parameter key.
 * @param {string} config.defaultTab - The default tab to open.
 * @returns {object} - Reactive state and methods to control the modal.
 */
const createUrlSyncedModal = ({ component, queryParam, defaultTab }) => {
  const { isAuthenticated } = useIsAuthenticated()
  const route = useRoute()
  const router = useRouter()
  const overlay = useOverlay()

  const tab = ref(null)
  const isOpen = ref(false)
  const queryRef = useRouteQuery(queryParam)

  const modal = overlay.create(component, {
    destroyOnClose: false
  })

  const cleanUpUrl = () => {
    const newQuery = { ...route.query }
    if (queryParam in newQuery) {
      delete newQuery[queryParam]
      return router.replace({ query: newQuery })
    }
    return Promise.resolve()
  }

  // URL → State sync
  watch([queryRef, isAuthenticated], ([queryTab, isLoggedIn]) => {
    if (queryTab && isLoggedIn) {
      tab.value = queryTab
      isOpen.value = true
      modal.open({
        activeTab: queryTab,
        'onUpdate:activeTab': (val) => {
          if (isOpen.value) {
            tab.value = val
          }
        },
        onClose: () => {
          tab.value = null
          cleanUpUrl()
        }
      })
    } else if (!queryTab && (isOpen.value || tab.value)) {
      tab.value = null
      isOpen.value = false
      modal.close()
    }
  }, { immediate: true })

  // State → URL sync
  watch(tab, (currentTab) => {
    const newQuery = { ...route.query }
    if (currentTab) {
      newQuery[queryParam] = currentTab
      router.replace({ query: newQuery })
    }
  })

  const open = (openTab = defaultTab, options = {}) => {
    tab.value = openTab
    isOpen.value = true

    // Update URL unless explicitly disabled, which triggers the watcher to open the modal
    if (options.updateUrl !== false) {
      queryRef.value = openTab
    }

    // Also call open directly to return the promise and handle complex cases
    return modal.open({
      activeTab: openTab,
      'onUpdate:activeTab': (val) => {
        if (isOpen.value) {
          tab.value = val
        }
      },
      onClose: () => {
        isOpen.value = false
        tab.value = null
        const newQuery = { ...route.query }
        delete newQuery[queryParam]
        router.replace({ query: newQuery })
      }
    })
  }

  const close = () => {
    modal.close()
    tab.value = null
    return cleanUpUrl()
  }

  const handleClose = () => {
    tab.value = null
  }

  return {
    tab: readonly(tab),
    isOpen: computed(() => tab.value !== null),
    open,
    close,
    handleClose
  }
}

/**
 * Factory for creating simple, non-URL-synced modals.
 * @param {object} config - Configuration object.
 * @param {object} config.component - The Vue component for the modal.
 * @returns {object} - Reactive state and methods to control the modal.
 */
const createSimpleModal = ({ component }) => {
  const overlay = useOverlay()
  const isOpen = ref(false)
  const modal = overlay.create(component, {
    destroyOnClose: false
  })

  const open = (props = {}) => {
    isOpen.value = true
    return modal.open({
      ...props,
      onClose: () => {
        isOpen.value = false
        // Allow consumer to hook into onClose
        if (props.onClose) {
          props.onClose()
        }
      }
    })
  }

  const close = () => {
    isOpen.value = false
    modal.close()
  }

  return {
    isOpen: readonly(isOpen),
    open,
    close
  }
}

export const useAppModals = createSharedComposable(() => {
  const userSettings = createUrlSyncedModal({
    component: UsersSettingsModal,
    queryParam: 'user-settings',
    defaultTab: 'profile'
  })

  const workspaceSettings = createUrlSyncedModal({
    component: WorkspacesSettingsModal,
    queryParam: 'workspace-settings',
    defaultTab: 'general'
  })

  const subscription = createSimpleModal({
    component: SubscriptionModal
  })

  return {
    // State (read-only)
    userSettingsTab: userSettings.tab,
    workspaceSettingsTab: workspaceSettings.tab,
    isUserSettingsOpen: userSettings.isOpen,
    isWorkspaceSettingsOpen: workspaceSettings.isOpen,
    isSubscriptionModalOpen: subscription.isOpen,

    // Actions
    openUserSettings: userSettings.open,
    openWorkspaceSettings: workspaceSettings.open,
    openSubscriptionModal: subscription.open,
    closeUserSettings: userSettings.close,
    closeWorkspaceSettings: workspaceSettings.close,
    closeSubscriptionModal: subscription.close,

    // Event handlers for modal components
    handleUserSettingsClose: userSettings.handleClose,
    handleWorkspaceSettingsClose: workspaceSettings.handleClose
  }
})
