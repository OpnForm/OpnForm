<template>
  <div class="flex-1">
    <!-- HERO -->
    <section class="bg-linear-to-b from-white via-blue-50/40 to-white py-16 sm:py-20 lg:py-28">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="mx-auto text-center">
          <div
            class="inline-flex items-center gap-2 rounded-full bg-blue-50 border border-blue-200 px-4 py-1.5 text-sm font-medium text-blue-700 mb-6"
          >
            <Icon
              name="i-heroicons-bolt-20-solid"
              class="w-4 h-4"
            />
            Model Context Protocol
          </div>

          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-semibold text-neutral-900 tracking-tight">
            Connect your AI to
            <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-600 to-blue-400">OpnForm</span>
          </h1>

          <p class="mt-5 text-lg sm:text-xl leading-8 font-medium text-neutral-500 max-w-2xl mx-auto">
            Create, manage, and monitor forms directly from Claude, Cursor, or any
            MCP-compatible AI client. No dashboard needed.
          </p>

          <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <UButton
              size="lg"
              to="https://docs.opnform.com/mcp/introduction"
              external
              trailing-icon="i-heroicons-arrow-top-right-on-square-20-solid"
              label="Read the Docs"
              class="rounded-xl"
            />
            <UButton
              size="lg"
              variant="outline"
              :to="{ name: authenticated ? 'forms-create' : 'register' }"
              label="Get Started Free"
              class="rounded-xl"
            />
          </div>
        </div>

        <!-- CONFIG SNIPPET -->
        <div class="max-w-2xl mx-auto mt-14">
          <div class="rounded-2xl bg-neutral-900 p-1 shadow-2xl ring-1 ring-white/10">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-neutral-700/50">
              <div class="w-3 h-3 rounded-full bg-red-400/80" />
              <div class="w-3 h-3 rounded-full bg-yellow-400/80" />
              <div class="w-3 h-3 rounded-full bg-green-400/80" />
              <span class="ml-2 text-xs text-neutral-400 font-mono">mcp.json</span>
            </div>
            <pre class="px-5 py-4 text-sm leading-relaxed overflow-x-auto"><code class="text-neutral-300 font-mono"><span class="text-neutral-500">{</span>
  <span class="text-blue-400">"mcpServers"</span><span class="text-neutral-500">:</span> <span class="text-neutral-500">{</span>
    <span class="text-blue-400">"opnform"</span><span class="text-neutral-500">:</span> <span class="text-neutral-500">{</span>
      <span class="text-blue-400">"url"</span><span class="text-neutral-500">:</span> <span class="text-emerald-400">"https://api.opnform.com/mcp"</span><span class="text-neutral-500">,</span>
      <span class="text-blue-400">"headers"</span><span class="text-neutral-500">:</span> <span class="text-neutral-500">{</span>
        <span class="text-blue-400">"Authorization"</span><span class="text-neutral-500">:</span> <span class="text-emerald-400">"Bearer YOUR_API_TOKEN"</span>
      <span class="text-neutral-500">}</span>
    <span class="text-neutral-500">}</span>
  <span class="text-neutral-500">}</span>
<span class="text-neutral-500">}</span></code></pre>
          </div>
        </div>
      </div>
    </section>

    <!-- TOOLS GRID -->
    <section class="bg-white py-16 sm:py-20 lg:py-24">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-3xl mx-auto">
          <h2 class="text-sm font-semibold text-blue-600 uppercase tracking-wide">
            Available Tools
          </h2>
          <p class="text-3xl mt-4 sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">
            Everything your AI agent needs
          </p>
          <p class="text-neutral-500 text-base leading-7 sm:text-lg sm:leading-8 font-medium mt-4">
            9 tools and a field-types resource — your agent can build forms end-to-end.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-12 sm:mt-16">
          <div
            v-for="tool in tools"
            :key="tool.name"
            class="group relative bg-white rounded-2xl border border-neutral-200 p-6 hover:shadow-lg hover:border-blue-200 transition-all duration-200"
          >
            <div class="flex items-center gap-3 mb-3">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center"
                :class="tool.colorClass"
              >
                <Icon
                  :name="tool.icon"
                  class="w-5 h-5"
                />
              </div>
              <code class="text-sm font-semibold text-neutral-900">{{ tool.name }}</code>
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              {{ tool.description }}
            </p>
            <UBadge
              :color="tool.badgeColor"
              variant="subtle"
              size="xs"
              class="mt-3"
              :label="tool.badge"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="bg-neutral-50 py-16 sm:py-20 lg:py-24 border-y border-neutral-200">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-3xl mx-auto">
          <h2 class="text-sm font-semibold text-blue-600 uppercase tracking-wide">
            How It Works
          </h2>
          <p class="text-3xl mt-4 sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">
            Three steps to AI-powered forms
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-12 mt-12 sm:mt-16">
          <div
            v-for="(step, index) in steps"
            :key="step.title"
            class="text-center lg:text-left"
          >
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-700 text-lg font-bold mb-4">
              {{ index + 1 }}
            </div>
            <h3 class="text-xl font-semibold text-neutral-900">
              {{ step.title }}
            </h3>
            <p class="mt-2 text-base text-neutral-500 leading-relaxed">
              {{ step.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- COMPATIBLE CLIENTS -->
    <section class="bg-white py-16 sm:py-20 lg:py-24">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-3xl mx-auto">
          <h2 class="text-sm font-semibold text-blue-600 uppercase tracking-wide">
            Compatible Clients
          </h2>
          <p class="text-3xl mt-4 sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">
            Works with the tools you already use
          </p>
          <p class="text-neutral-500 text-base leading-7 sm:text-lg sm:leading-8 font-medium mt-4">
            Any client that speaks MCP can connect to OpnForm.
          </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6 mt-12 sm:mt-16 max-w-4xl mx-auto">
          <a
            v-for="client in clients"
            :key="client.name"
            :href="client.link"
            :target="client.deepLink ? '_self' : '_blank'"
            :rel="client.deepLink ? undefined : 'noopener noreferrer'"
            class="relative flex flex-col items-center gap-3 p-6 rounded-2xl border border-neutral-200 bg-neutral-50 hover:bg-white hover:shadow-md hover:border-blue-200 transition-all duration-200 no-underline"
          >
            <span
              v-if="client.deepLink"
              class="absolute -top-2.5 right-3 text-[10px] font-semibold bg-blue-600 text-white px-2 py-0.5 rounded-full"
            >
              1-click install
            </span>
            <Icon
              :name="client.icon"
              class="w-8 h-8 text-neutral-700"
            />
            <span class="text-sm font-medium text-neutral-700">{{ client.name }}</span>
          </a>
        </div>
      </div>
    </section>

    <!-- GUEST MODE -->
    <section class="bg-neutral-50 py-16 sm:py-20 lg:py-24 border-y border-neutral-200">
      <div class="px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
        <div class="flex flex-col lg:flex-row items-center gap-10 lg:gap-16">
          <div class="flex-1">
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-medium text-emerald-700 mb-4">
              <Icon
                name="i-heroicons-user-plus-20-solid"
                class="w-3.5 h-3.5"
              />
              No account needed
            </div>
            <h2 class="text-3xl sm:text-4xl font-semibold text-neutral-900 tracking-tight">
              Try without signing up
            </h2>
            <p class="mt-4 text-base sm:text-lg text-neutral-500 leading-relaxed">
              Connect to the same <code class="text-sm bg-neutral-200 px-1.5 py-0.5 rounded">/mcp</code> endpoint
              without a token. Call <code class="text-sm bg-neutral-200 px-1.5 py-0.5 rounded">create-form</code>
              to get a draft JSON. When you're ready, review and save the form inside OpnForm.
            </p>
          </div>

          <div class="flex-1 w-full">
            <div class="rounded-2xl bg-neutral-900 p-1 shadow-xl ring-1 ring-white/10">
              <div class="flex items-center gap-2 px-4 py-3 border-b border-neutral-700/50">
                <div class="w-3 h-3 rounded-full bg-red-400/80" />
                <div class="w-3 h-3 rounded-full bg-yellow-400/80" />
                <div class="w-3 h-3 rounded-full bg-green-400/80" />
                <span class="ml-2 text-xs text-neutral-400 font-mono">No token needed</span>
              </div>
              <pre class="px-5 py-4 text-sm leading-relaxed overflow-x-auto"><code class="text-neutral-300 font-mono"><span class="text-neutral-500">{</span>
  <span class="text-blue-400">"mcpServers"</span><span class="text-neutral-500">:</span> <span class="text-neutral-500">{</span>
    <span class="text-blue-400">"opnform"</span><span class="text-neutral-500">:</span> <span class="text-neutral-500">{</span>
      <span class="text-blue-400">"url"</span><span class="text-neutral-500">:</span> <span class="text-emerald-400">"https://api.opnform.com/mcp"</span>
    <span class="text-neutral-500">}</span>
  <span class="text-neutral-500">}</span>
<span class="text-neutral-500">}</span></code></pre>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="bg-linear-to-b from-white to-neutral-50 py-16 sm:py-20 lg:py-24">
      <div class="px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto text-center">
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">
          Start building forms with AI
        </h2>
        <p class="mt-4 text-base sm:text-lg text-neutral-500 leading-relaxed">
          Create your free account, generate an API token, and point your AI
          client at the MCP endpoint. You'll have your first form in under a minute.
        </p>

        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
          <UButton
            size="lg"
            :to="{ name: authenticated ? 'forms-create' : 'register' }"
            trailing-icon="i-heroicons-arrow-right-20-solid"
            label="Get Started Free"
            class="rounded-xl"
          />
          <UButton
            size="lg"
            variant="outline"
            to="https://docs.opnform.com/mcp/introduction"
            external
            trailing-icon="i-heroicons-arrow-top-right-on-square-20-solid"
            label="View Documentation"
            class="rounded-xl"
          />
        </div>

        <ul
          class="flex mt-10 text-sm font-medium text-neutral-900 items-center justify-center flex-wrap gap-x-6 gap-y-3"
        >
          <li class="flex items-center gap-2">
            <Icon
              name="i-heroicons-check-20-solid"
              class="w-5 h-5 text-emerald-500"
            />
            Free forever
          </li>
          <li class="flex items-center gap-2">
            <Icon
              name="i-heroicons-check-20-solid"
              class="w-5 h-5 text-emerald-500"
            />
            Unlimited forms
          </li>
          <li class="flex items-center gap-2">
            <Icon
              name="i-heroicons-check-20-solid"
              class="w-5 h-5 text-emerald-500"
            />
            Open source
          </li>
        </ul>
      </div>
    </section>

    <OpenFormFooter class="border-t border-neutral-200" />
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'default',
  middleware: ['root-redirect', 'self-hosted'],
})

useOpnSeoMeta({
  title: 'MCP Server — Build Forms with AI Agents',
  description:
    'Connect Claude, Cursor, or any MCP-compatible AI client to OpnForm. Create, manage, and monitor forms programmatically through the Model Context Protocol.',
})

defineRouteRules({
  swr: 3600,
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const tools = [
  {
    name: 'List Workspaces',
    description: 'List all workspaces the authenticated user belongs to. The starting point for any operation.',
    icon: 'i-heroicons-building-office-20-solid',
    colorClass: 'bg-violet-100 text-violet-600',
    badge: 'Read',
    badgeColor: 'neutral',
  },
  {
    name: 'List Forms',
    description: 'List all forms in a workspace with submission counts, view stats, and share URLs.',
    icon: 'i-heroicons-document-text-20-solid',
    colorClass: 'bg-blue-100 text-blue-600',
    badge: 'Read',
    badgeColor: 'neutral',
  },
  {
    name: 'Get Form',
    description: 'Retrieve full form details — fields, settings, theme, and customization options.',
    icon: 'i-heroicons-eye-20-solid',
    colorClass: 'bg-blue-100 text-blue-600',
    badge: 'Read',
    badgeColor: 'neutral',
  },
  {
    name: 'Create Form',
    description: 'Create a form draft JSON. With authentication, drafts can be validated against workspace capabilities.',
    icon: 'i-heroicons-plus-circle-20-solid',
    colorClass: 'bg-emerald-100 text-emerald-600',
    badge: 'Draft / Guest',
    badgeColor: 'success',
  },
  {
    name: 'Update Form',
    description: 'Update any form attribute — title, fields, theme, colors, or visibility.',
    icon: 'i-heroicons-pencil-square-20-solid',
    colorClass: 'bg-amber-100 text-amber-600',
    badge: 'Write',
    badgeColor: 'warning',
  },
  {
    name: 'Delete Form',
    description: 'Soft-delete a form. Can be recovered by an admin if needed.',
    icon: 'i-heroicons-trash-20-solid',
    colorClass: 'bg-red-100 text-red-600',
    badge: 'Destructive',
    badgeColor: 'error',
  },
  {
    name: 'Duplicate Form',
    description: 'Create an exact copy of any form with a new slug and "Copy of" title prefix.',
    icon: 'i-heroicons-document-duplicate-20-solid',
    colorClass: 'bg-emerald-100 text-emerald-600',
    badge: 'Write',
    badgeColor: 'success',
  },
  {
    name: 'List Submissions',
    description: 'Paginated submission data with status filtering. See every response your form collected.',
    icon: 'i-heroicons-inbox-stack-20-solid',
    colorClass: 'bg-blue-100 text-blue-600',
    badge: 'Read',
    badgeColor: 'neutral',
  },
  {
    name: 'Get Submission',
    description: 'Fetch a single submission with all field responses and metadata.',
    icon: 'i-heroicons-document-magnifying-glass-20-solid',
    colorClass: 'bg-blue-100 text-blue-600',
    badge: 'Read',
    badgeColor: 'neutral',
  },
]

const steps = [
  {
    title: 'Create an API token',
    description:
      'Sign up or log in to OpnForm, then go to Settings and create a personal API token.',
  },
  {
    title: 'Configure your AI client',
    description:
      'Add the MCP server URL and your Bearer token to your client\'s mcp.json or settings file.',
  },
  {
    title: 'Start building',
    description:
      'Ask your AI to create forms, check submissions, or duplicate templates — all through natural language.',
  },
]

const mcpServerUrl = 'https://api.opnform.com/mcp'

function buildCursorLink() {
  const config = JSON.stringify({
    url: mcpServerUrl,
    headers: { Authorization: 'Bearer YOUR_API_TOKEN' },
  })
  return `cursor://anysphere.cursor-deeplink/mcp/install?name=opnform&config=${btoa(config)}`
}

function buildVscodeLink() {
  const config = JSON.stringify({
    name: 'opnform',
    type: 'streamable_http',
    url: mcpServerUrl,
    headers: { Authorization: 'Bearer YOUR_API_TOKEN' },
  })
  return `vscode:mcp/install?${encodeURIComponent(config)}`
}

const clients = [
  { name: 'Cursor', icon: 'i-simple-icons-cursor', link: buildCursorLink(), deepLink: true },
  { name: 'VS Code', icon: 'i-simple-icons-visualstudiocode', link: buildVscodeLink(), deepLink: true },
  { name: 'Claude Desktop', icon: 'i-simple-icons-anthropic', link: 'https://claude.ai/download' },
  { name: 'Windsurf', icon: 'i-heroicons-globe-alt-20-solid', link: 'https://docs.windsurf.com/windsurf/cascade/mcp' },
  { name: 'Any MCP Client', icon: 'i-heroicons-puzzle-piece-20-solid', link: 'https://modelcontextprotocol.io/clients' },
]
</script>
