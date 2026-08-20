import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
  currentWorkspace: { id: 1, is_admin: false, is_readonly: false },
  isSelfHosted: true,
  workspaces: [] as Array<{ id: number, is_admin: boolean, is_readonly: boolean }>,
}))

vi.mock('#imports', async () => {
  const vue = await import('vue')

  return {
    useAuth: () => ({
      user: () => ({ data: vue.ref({}) }),
    }),
    useCurrentWorkspace: () => ({
      current: vue.computed(() => mocks.currentWorkspace),
      workspaces: vue.computed(() => mocks.workspaces),
    }),
    useFeatureFlag: (key: string) => key === 'self_hosted' && mocks.isSelfHosted,
  }
})

vi.mock('~/composables/query/useAuth.js', async () => {
  const vue = await import('vue')

  return {
    useAuth: () => ({
      user: () => ({ data: vue.ref({}) }),
    }),
  }
})

vi.mock('~/composables/query/useCurrentWorkspace.js', async () => {
  const vue = await import('vue')

  return {
    useCurrentWorkspace: () => ({
      current: vue.computed(() => mocks.currentWorkspace),
      workspaces: vue.computed(() => mocks.workspaces),
    }),
  }
})

vi.mock('~/composables/useFeatureFlag.js', () => ({
  useFeatureFlag: (key: string) => key === 'self_hosted' && mocks.isSelfHosted,
}))

import UserSettingsModal from '~/components/users/settings/Modal.vue'

function mountModal() {
  return mount(UserSettingsModal, {
    props: {
      activeTab: 'mcp',
    },
    global: {
      stubs: {
        SettingsModal: {
          template: '<div><slot /></div>',
          props: ['modelValue', 'activeTab'],
          emits: ['close', 'update:modelValue', 'update:activeTab'],
        },
        SettingsModalPage: {
          template: '<section :data-page-id="id" />',
          props: ['icon', 'id', 'label'],
        },
        LazyUsersSettingsAccessTokens: true,
        LazyUsersSettingsAccount: true,
        LazyUsersSettingsBilling: true,
        LazyUsersSettingsConnections: true,
        LazyUsersSettingsLicense: true,
        LazyUsersSettingsMcp: true,
        LazyUsersSettingsSecurity: true,
      },
    },
  })
}

describe('User settings instance administration', () => {
  beforeEach(() => {
    mocks.currentWorkspace = { id: 1, is_admin: false, is_readonly: false }
    mocks.isSelfHosted = true
    mocks.workspaces = []
  })

  it('shows instance settings when the user administers another workspace', () => {
    mocks.workspaces = [
      mocks.currentWorkspace,
      { id: 2, is_admin: true, is_readonly: false },
    ]

    const wrapper = mountModal()

    expect(wrapper.find('[data-page-id="license"]').exists()).toBe(true)
    expect(wrapper.find('[data-page-id="mcp"]').exists()).toBe(true)
  })

  it('hides instance settings when the user administers no workspace', () => {
    mocks.workspaces = [mocks.currentWorkspace]

    const wrapper = mountModal()

    expect(wrapper.find('[data-page-id="license"]').exists()).toBe(false)
    expect(wrapper.find('[data-page-id="mcp"]').exists()).toBe(false)
  })
})
