<template>
  <section class="bg-white py-8 sm:py-12">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="rounded-[2.5rem] bg-gradient-to-br from-neutral-950 via-neutral-900 to-neutral-950 border border-white/10 shadow-2xl overflow-hidden">
        <div class="grid gap-10 lg:grid-cols-2 items-center px-8 py-12 sm:px-12 sm:py-16">
          <div class="max-w-xl">
            <div class="text-blue-400 text-xs font-semibold tracking-[0.18em]">
              INTEGRATIONS
            </div>
            <h3 class="mt-4 text-4xl sm:text-5xl font-semibold text-white tracking-tight leading-tight">
              Connect OpnForm to
              <br>
              your workflow.
            </h3>
            <p class="mt-5 text-base sm:text-lg font-medium text-neutral-300 leading-8">
              Send responses instantly to your tools. Automate notifications. Sync data across your stack with one click.
            </p>

            <div class="mt-10">
              <UButton
                :to="{ name: 'integrations' }"
                label="Explore All Integrations"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="rounded-xl px-6 py-3 text-base font-semibold"
              />
            </div>
          </div>

          <div class="flex justify-center lg:justify-end">
            <div class="grid grid-cols-5 gap-4">
              <div
                v-for="app in integrationApps"
                :key="app.icon"
                class="h-20 w-20 rounded-2xl bg-white/5 ring-1 ring-white/10 flex items-center justify-center backdrop-blur-sm"
              >
                <UIcon :name="app.icon" class="h-9 w-9" :class="app.iconClass" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  
  <section class="bg-white py-8 sm:py-12">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="max-w-3xl mx-auto text-center">
        <h3 class="text-4xl sm:text-5xl font-semibold text-neutral-900 tracking-tight">
          Share your form anywhere.
        </h3>
        <p class="mt-4 text-base sm:text-lg font-medium text-neutral-500 leading-7">
          Choose the distribution method that fits your workflow.
        </p>
      </div>

      <div class="mt-8 grid gap-6">
        <div class="grid gap-6 lg:grid-cols-2">
          <div
            v-for="card in shareCards"
            :key="card.key"
            class="rounded-3xl bg-neutral-50 border border-neutral-200/70 p-4"
            :class="card.size === 'large' ? 'lg:col-span-2' : ''"
          >
            <div
              class="grid gap-6 items-start"
              :class="card.size === 'large' ? 'lg:grid-cols-12' : 'sm:grid-cols-12'"
            >
              <div
                class="flex flex-col items-start gap-4"
                :class="card.size === 'large' ? 'lg:col-span-4' : 'sm:col-span-5'"
              >
                <div class="h-14 w-14 rounded-2xl ring-1 flex items-center justify-center bg-white shadow-xs ring-neutral-200/70">
                  <UIcon :name="card.icon" class="h-7 w-7 text-blue-600" />
                </div>
                <div class="text-xl font-semibold text-neutral-900 ">
                  {{ card.title }}
                </div>
              </div>

              <div :class="card.size === 'large' ? 'lg:col-span-8' : 'sm:col-span-7'">
                <img
                  :src="card.imageSrc"
                  :alt="card.imageAlt"
                  class="w-full -mb-8"
                  loading="lazy"
                >
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-10 text-center text-base font-medium text-neutral-500">
        Your forms are accessible wherever your audience is.
      </div>
    </div>
  </section>

  <section class="bg-white py-8 sm:py-12">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="rounded-[2.5rem] bg-neutral-50 border border-neutral-200/60 overflow-hidden">
        <div class="relative px-6 py-12 sm:px-12 sm:py-16">
          <div
            class="pointer-events-none absolute inset-x-0 bottom-0 h-[60%] opacity-60 bg-[radial-gradient(circle,rgba(148,163,184,0.55)_1px,transparent_1px)] [background-size:14px_14px]"
          />
          <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[60%] bg-gradient-to-t from-neutral-50 to-transparent" />

          <div class="relative max-w-3xl mx-auto text-center">
            <h3 class="text-4xl sm:text-5xl font-semibold text-neutral-900 tracking-tight">
              Open-source. Secure.
              <br>
              Yours to host anywhere.
            </h3>
            <p class="mt-6 text-base sm:text-lg font-medium text-neutral-600">
              OpnForm is fully open-source and built for privacy-first organizations.
            </p>
            <p class="mt-4 text-base sm:text-lg font-medium text-neutral-600">
              Use our EU-based cloud or deploy on-premise to keep full control of your data.
            </p>
          </div>

          <div class="relative mt-12 flex flex-wrap items-center justify-center gap-4">
            <div
              v-for="pill in securityPills"
              :key="pill.label"
              class="flex items-center gap-3 rounded-full bg-white border border-neutral-200/80 px-5 py-1 text-sm font-semibold text-neutral-900 shadow-sm"
            >
              <div class="h-9 w-9 rounded-full flex items-center justify-center" :class="pill.iconWrapClass">
                <UIcon :name="pill.icon" class="h-5 w-5" :class="pill.iconClass" />
              </div>
              <span>{{ pill.label }}</span>
            </div>
          </div>

          <div class="relative mt-12 flex flex-wrap justify-center gap-x-10 gap-y-4 text-sm font-semibold text-blue-600">
            <a
              class="inline-flex items-center gap-2 hover:no-underline hover:text-blue-700"
              href="https://github.com/OpnForm/OpnForm"
              target="_blank"
              rel="noopener noreferrer"
            >
              View GitHub
              <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
            </a>
            <a
              class="inline-flex items-center gap-2 hover:no-underline hover:text-blue-700"
              href="https://docs.opnform.com/deployment/docker"
              target="_blank"
              rel="noopener noreferrer"
            >
              Self-host OpnForm
              <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
            </a>
            <a
              class="inline-flex items-center gap-2 hover:no-underline hover:text-blue-700"
              href="https://docs.opnform.com/contributing/getting-started"
              target="_blank"
              rel="noopener noreferrer"
            >
              Open-source Benefits
              <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="bg-white py-8 sm:py-12">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="max-w-3xl mx-auto text-center">
        <h3 class="text-4xl sm:text-5xl font-semibold text-neutral-900 tracking-tight">
          Flexible for individuals —
          <br>
          reliable for teams.
        </h3>
        <p class="mt-4 text-base sm:text-lg font-medium text-neutral-500 leading-7">
          Manage forms at scale with powerful collaboration and compliance features.
        </p>
      </div>

      <div class="mt-12 grid gap-12 lg:grid-cols-2 max-w-6xl mx-auto">
        <div>
          <h4 class="text-2xl font-semibold text-neutral-900">
            Collaboration &amp; Management
          </h4>
          <p class="mt-4 text-base font-medium text-neutral-500 leading-7 max-w-xl">
            Coordinate work across teams with shared workspaces, clear roles, and full audit visibility.
          </p>

          <div class="mt-8 space-y-6">
            <div v-for="item in collaborationItems" :key="item.label" class="flex items-start gap-4">
              <div class="h-9 w-9 rounded-xl bg-blue-50 ring-1 ring-blue-100 flex items-center justify-center">
                <UIcon :name="item.icon" class="h-5 w-5 text-blue-600" />
              </div>
              <div class="text-base font-semibold text-neutral-700">
                {{ item.label }}
              </div>
            </div>
          </div>
        </div>

        <div>
          <h4 class="text-2xl font-semibold text-neutral-900">
            Security &amp; Compliance
          </h4>
          <p class="mt-4 text-base font-medium text-neutral-500 leading-7 max-w-xl">
            Stay aligned with internal requirements through SSO access, domain control, and elevated support.
          </p>

          <div class="mt-10 space-y-6">
            <div v-for="item in securityItems" :key="item.label" class="flex items-start gap-4">
              <div class="h-9 w-9 rounded-xl bg-blue-50 ring-1 ring-blue-100 flex items-center justify-center">
                <UIcon :name="item.icon" class="h-5 w-5 text-blue-600" />
              </div>
              <div class="text-base font-semibold text-neutral-700">
                {{ item.label }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-10 flex justify-center">
        <UButton
          size="md"
          :to="{ name: 'pricing' }"
          label="Learn about Enterprise"
          trailing-icon="i-heroicons-arrow-up-right-20-solid"
          class="rounded-xl px-7 py-3 text-base font-semibold"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
const integrationApps = [
  { icon: 'i-simple-icons-zendesk', iconClass: 'text-white' },
  { icon: 'i-simple-icons-dropbox', iconClass: 'text-[#0061FF]' },
  { icon: 'i-simple-icons-mailchimp', iconClass: 'text-[#FFE01B]' },
  { icon: 'i-simple-icons-zapier', iconClass: 'text-[#FF4A00]' },
  { icon: 'i-simple-icons-freshdesk', iconClass: 'text-[#25C16F]' },

  { icon: 'i-simple-icons-clickup', iconClass: 'text-white' },
  { icon: 'i-simple-icons-slack', iconClass: 'text-white' },
  { icon: 'i-simple-icons-asana', iconClass: 'text-white' },
  { icon: 'i-simple-icons-zoom', iconClass: 'text-[#2D8CFF]' },
  { icon: 'i-simple-icons-linear', iconClass: 'text-white' },

  { icon: 'i-simple-icons-evernote', iconClass: 'text-[#00A82D]' },
  { icon: 'i-simple-icons-notion', iconClass: 'text-white' },
  { icon: 'i-simple-icons-airtable', iconClass: 'text-[#FFBF00]' },
  { icon: 'i-simple-icons-meta', iconClass: 'text-[#0A66FF]' },
  { icon: 'i-simple-icons-mondaydotcom', iconClass: 'text-white' },
]

const shareCards = [
  {
    key: 'link',
    size: 'large',
    title: 'Share with a simple link',
    icon: 'i-heroicons-link',
    imageSrc: '/img/pages/welcome/share-1.png',
    imageAlt: 'Share link preview',
  },
  {
    key: 'embed',
    size: 'small',
    title: 'Embed on your website or app',
    icon: 'i-heroicons-code-bracket-square',
    imageSrc: '/img/pages/welcome/share-2.png',
    imageAlt: 'Embed preview',
  },
  {
    key: 'notion',
    size: 'small',
    title: 'Add forms directly into Notion',
    icon: 'i-simple-icons-notion',
    imageSrc: '/img/pages/welcome/share-3.png',
    imageAlt: 'Notion embed preview',
  },
  {
    key: 'domain',
    size: 'small',
    title: 'Use your own custom domain',
    icon: 'i-heroicons-globe-alt',
    imageSrc: '/img/pages/welcome/share-4.png',
    imageAlt: 'Custom domain preview',
  },
  {
    key: 'qr',
    size: 'small',
    title: 'Generate QR codes for offline collection',
    icon: 'i-heroicons-qr-code',
    imageSrc: '/img/pages/welcome/share-5.png',
    imageAlt: 'QR code preview',
  },
]

const securityPills = [
  {
    label: '100% open-source & auditable',
    icon: 'i-heroicons-code-bracket-square',
    iconWrapClass: 'bg-blue-50',
    iconClass: 'text-blue-600',
  },
  {
    label: 'EU hosting & GDPR-friendly architecture',
    icon: 'i-heroicons-globe-europe-africa',
    iconWrapClass: 'bg-red-50',
    iconClass: 'text-red-600',
  },
  {
    label: 'Self-hosting / on-premise deployments',
    icon: 'i-heroicons-server-stack',
    iconWrapClass: 'bg-pink-50',
    iconClass: 'text-pink-600',
  },
  {
    label: 'SSO & SAML support',
    icon: 'i-heroicons-lock-closed',
    iconWrapClass: 'bg-violet-50',
    iconClass: 'text-violet-600',
  },
  {
    label: 'Form & submission audit logs',
    icon: 'i-heroicons-clipboard-document-check',
    iconWrapClass: 'bg-sky-50',
    iconClass: 'text-sky-600',
  },
  {
    label: 'SOC2 compliance underway',
    icon: 'i-heroicons-shield-check',
    iconWrapClass: 'bg-orange-50',
    iconClass: 'text-orange-600',
  },
  {
    label: 'Two-factor authentication (2FA) support',
    icon: 'i-heroicons-key',
    iconWrapClass: 'bg-fuchsia-50',
    iconClass: 'text-fuchsia-600',
  },
  {
    label: 'End-to-end data encryption',
    icon: 'i-heroicons-lock-closed',
    iconWrapClass: 'bg-emerald-50',
    iconClass: 'text-emerald-600',
  },
]

const collaborationItems = [
  { label: 'Team workspaces & user roles', icon: 'i-heroicons-users' },
  { label: 'Advanced permissions', icon: 'i-heroicons-adjustments-horizontal' },
  { label: 'Audit history for forms & submissions', icon: 'i-heroicons-clock' },
  { label: 'Branding and theme control', icon: 'i-heroicons-paint-brush' },
]

const securityItems = [
  { label: 'Custom domains & full white-labeling', icon: 'i-heroicons-globe-alt' },
  { label: 'Priority support', icon: 'i-heroicons-lifebuoy' },
  { label: 'SSO / SAML authentication', icon: 'i-heroicons-lock-closed' },
]
</script>
