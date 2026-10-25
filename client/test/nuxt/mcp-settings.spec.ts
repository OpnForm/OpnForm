import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import McpSettings from '~/components/users/settings/Mcp.vue'

const mocks = vi.hoisted(() => ({
  status: vi.fn(),
  update: vi.fn(),
  success: vi.fn(),
  error: vi.fn(),
  copy: vi.fn(),
}))

vi.mock('~/api', () => ({
  mcpApi: {
    status: mocks.status,
    update: mocks.update,
  },
}))

vi.mock('~/composables/useAlert.js', () => ({
  useAlert: () => ({
    success: mocks.success,
    error: mocks.error,
  }),
}))

const readySettings = {
  self_hosted: true,
  enabled: false,
  available: false,
  configured_value: null,
  source: 'environment',
  ready: true,
  blockers: [],
  server_url: 'https://forms.example.com/mcp',
  settings_url: 'https://forms.example.com/?user-settings=mcp',
  snippets: {
    cursor: '{"mcpServers":{"opnform":{"url":"https://forms.example.com/mcp"}}}',
    claude_code: "claude mcp add --transport http opnform 'https://forms.example.com/mcp'",
    chatgpt: 'Server URL: https://forms.example.com/mcp\nAuthentication: OAuth',
    codex: "codex mcp add opnform --url 'https://forms.example.com/mcp'",
    other: '{"mcpServers":{"opnform":{"type":"http"}}}',
    portable: '{"$schema":"https://agent-plugins.org"}',
  },
  install_urls: {
    cursor: 'cursor://install-opnform',
  },
}

function mountSettings() {
  return mount(McpSettings, {
    global: {
      stubs: {
        Icon: true,
        UBadge: {
          template: '<span><slot /></span>',
          props: ['color', 'variant'],
        },
        UButton: {
          template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>',
          props: ['color', 'disabled', 'icon', 'label', 'target', 'to', 'variant'],
          emits: ['click'],
        },
        USkeleton: true,
        USwitch: {
          template: '<button data-testid="mcp-enabled-switch" :disabled="disabled" @click="$emit(\'update:modelValue\', !modelValue)" />',
          props: ['disabled', 'modelValue'],
          emits: ['update:modelValue'],
        },
        CopyContent: {
          template: '<div data-testid="server-url">{{ content }}</div>',
          props: ['content', 'label'],
        },
        McpCodeSnippet: {
          template: '<div class="snippet"><slot name="selector" /><slot name="instructions" /><pre>{{ content }}</pre></div>',
          props: ['content', 'label'],
          emits: ['copy'],
        },
      },
    },
  })
}

describe('MCP self-hosted settings', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('useAlert', () => ({
      success: mocks.success,
      error: mocks.error,
    }))
    vi.stubGlobal('useClipboard', () => ({ copy: mocks.copy }))
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the generated connection details and enables MCP', async () => {
    mocks.status.mockResolvedValue(readySettings)
    mocks.update.mockResolvedValue({ ...readySettings, enabled: true, available: true, source: 'settings' })

    const wrapper = mountSettings()
    await flushPromises()

    expect(wrapper.text()).toContain('Ready to connect')
    expect(wrapper.find('[data-testid="mcp-connection-settings"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mcp-snippet-settings"]').exists()).toBe(false)

    await wrapper.get('[data-testid="mcp-enabled-switch"]').trigger('click')
    await flushPromises()

    expect(mocks.update).toHaveBeenCalledWith(true)
    expect(wrapper.text()).toContain('MCP is available')
    expect(wrapper.text()).toContain('Connected AI assistants can create and manage forms')
    expect(wrapper.text()).not.toContain('Guest draft creation')
    expect(wrapper.get('[data-testid="server-url"]').text()).toBe('https://forms.example.com/mcp')
    expect(wrapper.get('[data-testid="mcp-snippet-settings"]').text()).toContain(readySettings.snippets.codex)
    expect(mocks.success).toHaveBeenCalledWith('MCP enabled for this instance.')

    await wrapper.get('[data-testid="mcp-snippet-cursor"]').trigger('click')
    expect(wrapper.get('[data-testid="mcp-snippet-settings"]').text()).toContain(readySettings.snippets.cursor)
    expect(wrapper.get('[data-testid="mcp-snippet-settings"]').text()).not.toContain(readySettings.snippets.codex)
    expect(wrapper.text()).toContain('Install in Cursor')

    const copySettingsLink = wrapper.findAll('button').find(button => button.text().includes('Copy settings link'))
    await copySettingsLink!.trigger('click')
    expect(mocks.copy).toHaveBeenCalledWith('https://forms.example.com/?user-settings=mcp&agent=cursor')
    expect(wrapper.get('[data-testid="mcp-portable-config"]').text()).toContain('Portable Agent Plugin configuration')
  })

  it('blocks activation and explains every missing prerequisite', async () => {
    mocks.status.mockResolvedValue({
      ...readySettings,
      ready: false,
      blockers: [
        { code: 'passport_keys_missing', message: 'Passport keys are missing.' },
        { code: 'front_url_invalid', message: 'FRONT_URL must use HTTPS.' },
      ],
    })

    const wrapper = mountSettings()
    await flushPromises()

    expect(wrapper.get('[data-testid="mcp-enabled-switch"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="mcp-readiness-blockers"]').text()).toContain('Passport keys are missing.')
    expect(wrapper.get('[data-testid="mcp-readiness-blockers"]').text()).toContain('FRONT_URL must use HTTPS.')
    expect(mocks.update).not.toHaveBeenCalled()
  })

  it('keeps connection details hidden when an enabled server is not operational', async () => {
    mocks.status.mockResolvedValue({
      ...readySettings,
      enabled: true,
      available: false,
      ready: false,
      blockers: [
        { code: 'passport_keys_missing', message: 'Passport keys are missing.' },
      ],
    })

    const wrapper = mountSettings()
    await flushPromises()

    expect(wrapper.text()).toContain('Server setup required')
    expect(wrapper.text()).toContain('Unavailable')
    expect(wrapper.find('[data-testid="mcp-connection-settings"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mcp-snippet-settings"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="mcp-enabled-switch"]').attributes('disabled')).toBeUndefined()
  })

  it('offers a retry when the settings request fails', async () => {
    mocks.status
      .mockRejectedValueOnce({ data: { message: 'API unavailable' } })
      .mockResolvedValueOnce(readySettings)

    const wrapper = mountSettings()
    await flushPromises()

    expect(wrapper.get('[data-testid="mcp-load-error"]').text()).toContain('MCP settings could not be loaded')
    expect(mocks.error).toHaveBeenCalledWith('API unavailable')

    const retry = wrapper.findAll('button').find(button => button.text().includes('Retry'))
    await retry!.trigger('click')
    await flushPromises()

    expect(mocks.status).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Ready to connect')
  })

  it('shows cloud connection instructions without an availability switch', async () => {
    mocks.status.mockResolvedValue({
      ...readySettings,
      self_hosted: false,
      enabled: true,
      available: true,
      server_url: 'https://api.opnform.com/mcp',
      settings_url: 'https://opnform.com/?user-settings=mcp',
    })

    const wrapper = mountSettings()
    await flushPromises()

    expect(wrapper.text()).toContain('OpnForm MCP is ready')
    expect(wrapper.text()).toContain('Create private drafts without signing in')
    expect(wrapper.text()).toContain('Cloud')
    expect(wrapper.find('[data-testid="mcp-enabled-switch"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="server-url"]').text()).toBe('https://api.opnform.com/mcp')
    expect(wrapper.find('[data-testid="mcp-snippet-settings"]').exists()).toBe(true)
  })
})
