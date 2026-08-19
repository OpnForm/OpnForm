<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex items-center gap-2">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <Icon name="i-heroicons-cpu-chip" class="h-5 w-5" />
          </div>
          <div>
            <h3 class="text-lg font-semibold text-neutral-900">
              MCP & AI agents
            </h3>
            <p class="text-sm text-neutral-500">
              Connect AI assistants directly to this OpnForm instance.
            </p>
          </div>
        </div>
      </div>

      <UButton
        to="https://docs.opnform.com/integrations/mcp"
        target="_blank"
        label="Setup guide"
        icon="i-heroicons-arrow-up-right"
        variant="outline"
        color="neutral"
      />
    </div>

    <div v-if="isLoading" class="space-y-3">
      <USkeleton class="h-28 w-full" />
      <USkeleton class="h-40 w-full" />
    </div>

    <section
      v-else-if="loadError"
      class="rounded-xl border border-red-200 bg-red-50 p-5"
      data-testid="mcp-load-error"
    >
      <div class="flex items-start gap-3">
        <Icon name="i-heroicons-exclamation-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
        <div>
          <h4 class="font-semibold text-red-900">MCP settings could not be loaded</h4>
          <p class="mt-1 text-sm text-red-800">Check the API connection and try again.</p>
          <UButton class="mt-3" label="Retry" color="error" variant="soft" @click="loadSettings" />
        </div>
      </div>
    </section>

    <template v-else-if="settings">
      <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex min-w-0 items-start gap-3">
            <div
              class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
              :class="statusIconClasses"
            >
              <Icon :name="statusIcon" class="h-5 w-5" />
            </div>
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <p class="font-semibold text-neutral-900">
                  {{ statusTitle }}
                </p>
                <UBadge :color="statusBadgeColor" variant="subtle">
                  {{ settings.enabled ? 'Enabled' : 'Disabled' }}
                </UBadge>
              </div>
              <p class="mt-1 max-w-xl text-sm leading-6 text-neutral-600">
                {{ statusDescription }}
              </p>
              <p class="mt-2 text-xs text-neutral-400">
                {{ sourceDescription }}
              </p>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2">
            <span class="text-sm font-medium text-neutral-700">Enable MCP</span>
            <USwitch
              :model-value="settings.enabled"
              :disabled="isSaving || (!settings.ready && !settings.enabled)"
              aria-label="Enable MCP"
              data-testid="mcp-enabled-switch"
              @update:model-value="updateEnabled"
            />
          </div>
        </div>

        <div
          v-if="settings.enabled"
          class="border-t border-amber-200 bg-amber-50 px-5 py-3 text-sm leading-6 text-amber-900"
        >
          Guest draft creation is publicly reachable and protected by your configured MCP rate limits. Account, form, and submission tools still require OAuth and normal workspace permissions.
        </div>
      </section>

      <section
        v-if="!settings.ready"
        class="rounded-xl border border-red-200 bg-red-50 p-5"
        data-testid="mcp-readiness-blockers"
      >
        <div class="flex items-start gap-3">
          <Icon name="i-heroicons-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
          <div>
            <h4 class="font-semibold text-red-900">
              Complete the server setup first
            </h4>
            <p class="mt-1 text-sm leading-6 text-red-800">
              MCP cannot be enabled until OAuth is ready on this instance.
            </p>
            <ul class="mt-3 space-y-2 text-sm text-red-800">
              <li v-for="blocker in settings.blockers" :key="blocker.code" class="flex gap-2">
                <span aria-hidden="true">•</span>
                <span>{{ blocker.message }}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <section class="space-y-4 rounded-xl border border-neutral-200 bg-white p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">
              Connection endpoint
            </p>
            <h4 class="mt-1 font-semibold text-neutral-900">
              Connect your AI assistant
            </h4>
            <p class="mt-1 text-sm leading-6 text-neutral-600">
              Use the server URL directly in ChatGPT, or copy the configuration for your agent client.
            </p>
          </div>
          <UButton
            label="Copy settings link"
            icon="i-heroicons-link"
            variant="soft"
            color="neutral"
            @click="copyValue(settings.settings_url, 'Settings link copied')"
          />
        </div>

        <div>
          <p class="mb-2 text-sm font-medium text-neutral-700">MCP server URL</p>
          <CopyContent :content="settings.server_url" label="Copy URL" />
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
            <div class="flex items-center gap-2 font-medium text-neutral-900">
              <Icon name="i-heroicons-sparkles" class="h-4 w-4" />
              ChatGPT
            </div>
            <p class="mt-2 text-sm leading-6 text-neutral-600">
              In ChatGPT developer mode, create an MCP app and paste the server URL above. OAuth discovery is automatic.
            </p>
          </div>
          <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
            <div class="flex items-center gap-2 font-medium text-neutral-900">
              <Icon name="i-heroicons-command-line" class="h-4 w-4" />
              Codex CLI
            </div>
            <p class="mt-2 text-sm leading-6 text-neutral-600">
              Add the server, then authenticate when you need account-scoped tools.
            </p>
          </div>
        </div>
      </section>

      <section class="space-y-4">
        <McpCodeSnippet
          title="Codex command"
          description="Add this instance as a streamable HTTP MCP server."
          :content="settings.snippets.codex_cli"
          @copy="copyValue(settings.snippets.codex_cli, 'Codex command copied')"
        />
        <McpCodeSnippet
          title="Native Codex / OpenAI configuration"
          description="Use this in a native MCP configuration file."
          :content="settings.snippets.native"
          @copy="copyValue(settings.snippets.native, 'Native configuration copied')"
        />
        <McpCodeSnippet
          title="Portable Agent Plugins configuration"
          description="Use this inside an agent-plugins.org compatible package."
          :content="settings.snippets.portable"
          @copy="copyValue(settings.snippets.portable, 'Portable configuration copied')"
        />
      </section>
    </template>
  </div>
</template>

<script setup>
import { mcpApi } from '~/api'
import CopyContent from '~/components/open/forms/components/CopyContent.vue'
import McpCodeSnippet from '~/components/users/settings/mcp/McpCodeSnippet.vue'

const alert = useAlert()
const { copy } = useClipboard()
const settings = ref(null)
const isLoading = ref(true)
const isSaving = ref(false)
const loadError = ref(false)

const statusTitle = computed(() => {
  if (!settings.value?.ready) return 'Server setup required'
  return settings.value?.enabled ? 'MCP is available' : 'Ready to connect'
})

const statusDescription = computed(() => {
  if (!settings.value?.ready) return 'Resolve the prerequisites below before exposing the MCP endpoint.'
  if (settings.value?.enabled) return 'AI assistants can create guest drafts and connected users can manage forms and read submissions.'
  return 'OAuth is ready. Enable MCP when you want this instance to accept agent connections.'
})

const statusIcon = computed(() => {
  if (!settings.value?.ready) return 'i-heroicons-exclamation-triangle'
  return settings.value?.enabled ? 'i-heroicons-check' : 'i-heroicons-pause'
})

const statusIconClasses = computed(() => {
  if (!settings.value?.ready) return 'bg-red-100 text-red-600'
  return settings.value?.enabled ? 'bg-emerald-100 text-emerald-600' : 'bg-neutral-100 text-neutral-500'
})

const statusBadgeColor = computed(() => settings.value?.enabled ? 'success' : 'neutral')

const sourceDescription = computed(() => {
  return settings.value?.source === 'settings'
    ? 'Controlled from this instance settings.'
    : 'Using MCP_ENABLED from the API container environment until you change this switch.'
})

function loadSettings() {
  isLoading.value = true
  loadError.value = false
  mcpApi.status()
    .then((response) => {
      settings.value = response
    })
    .catch((error) => {
      loadError.value = true
      alert.error(error?.data?.message || 'Failed to load MCP settings.')
    })
    .finally(() => {
      isLoading.value = false
    })
}

function updateEnabled(enabled) {
  isSaving.value = true
  mcpApi.update(enabled)
    .then((response) => {
      settings.value = response
      alert.success(enabled ? 'MCP enabled for this instance.' : 'MCP disabled for this instance.')
    })
    .catch((error) => {
      if (error?.data?.blockers) {
        settings.value = {
          ...settings.value,
          ready: false,
          blockers: error.data.blockers,
        }
      }
      alert.error(error?.data?.message || 'Failed to update MCP settings.')
    })
    .finally(() => {
      isSaving.value = false
    })
}

function copyValue(value, message) {
  copy(value)
  alert.success(message)
}

onMounted(loadSettings)
</script>
