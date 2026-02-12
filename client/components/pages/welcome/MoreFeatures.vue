<template>
  <section class="bg-white py-8 sm:py-12">
    <Integrations />
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
        <div class="grid gap-6 lg:grid-cols-2 max-w-5xl mx-auto">
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
                :class="card.size === 'large' ? 'lg:col-span-3' : 'sm:col-span-5'"
              >
                <div class="h-14 w-14 rounded-2xl ring-1 flex items-center justify-center bg-white shadow-xs ring-neutral-200/70">
                  <UIcon :name="card.icon" class="h-7 w-7 text-blue-600" />
                </div>
                <div class="text-xl font-semibold text-neutral-900 ">
                  {{ card.title }}
                </div>
              </div>

              <div :class="card.size === 'large' ? 'lg:col-span-9' : 'sm:col-span-7'">
                <img
                  :src="card.imageSrc"
                  :alt="card.title"
                  class="w-full -mb-4"
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
      <div class="mt-6 text-center">
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <UButton
            size="lg"
            :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
            trailing-icon="i-heroicons-arrow-up-right-20-solid"
            label="Get started. It's FREE!"
          />
        </div>
      </div>
    </div>
  </section>

  <section class="bg-white py-8 sm:py-12">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="rounded-[2.5rem] bg-neutral-50 border border-neutral-200/60 overflow-hidden">
        <div class="relative px-6 py-12 sm:px-12 sm:py-16">
          <div class="grid lg:grid-cols-2 gap-12">
            <div class="flex flex-col items-start gap-2">
              <h3 class="text-3xl sm:text-4xl font-semibold text-neutral-900 tracking-tight">
                Open-source. Secure.
                <br>
                Yours to host anywhere.
              </h3>
              <p class="mt-2 text-base font-medium text-neutral-600">
                OpnForm is fully open-source and built for privacy-first organizations.
              </p>
              <p class="mt-1 text-base font-medium text-neutral-600">
                Use our EU-based cloud or deploy on-premise to keep full control of your data.
              </p>

              <UButton
                :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
                size="lg"
                class="mt-8"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Create a Form"
              />

              <div class="mt-8 flex flex-wrap gap-x-10 gap-y-4 text-sm font-semibold text-blue-600">
                <a
                  class="inline-flex items-center gap-2 hover:no-underline hover:text-blue-700"
                  :href="opnformConfig.links.github_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  View GitHub
                  <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
                </a>
                <a
                  class="inline-flex items-center gap-2 hover:no-underline hover:text-blue-700"
                  :href="opnformConfig.links.self_hosting"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Self-host OpnForm
                  <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
                </a>
                <a
                  class="inline-flex items-center gap-2 hover:no-underline hover:text-blue-700"
                  :href="opnformConfig.links.tech_docs"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Open-source Benefits
                  <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
                </a>
              </div>
            </div>
            <div class="flex flex-col gap-3">
              <div
                v-for="pill in securityPills"
                :key="pill.label"
                class="flex items-center gap-2 w-fit rounded-full bg-white border border-neutral-200/80 py-1.5 pl-2 pr-4 text-sm font-semibold text-neutral-900 shadow-sm"
              >
                <div class="h-8 w-8 shrink-0 rounded-full flex items-center justify-center" :class="pill.iconWrapClass">
                  <UIcon :name="pill.icon" class="h-5 w-5" :class="pill.iconClass" />
                </div>
                <span>{{ pill.label }}</span>
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
          Flexible for individuals —
          <br>
          reliable for teams.
        </h3>
        <p class="mt-4 text-base sm:text-lg font-medium text-neutral-500 leading-7">
          Manage forms at scale with powerful collaboration and compliance features.
        </p>
      </div>

      <div class="mt-12 max-w-6xl mx-auto space-y-8">
        <div class="grid gap-8 grid-cols-1 sm:grid-cols-2">
          <div
            v-for="block in teamFeatureBlocks.slice(0, 2)"
            :key="block.title"
            class="overflow-hidden rounded-3xl bg-neutral-50 p-8 shadow-sm ring-1 ring-neutral-200"
          >
            <h4 class="text-xl font-semibold text-neutral-900">
              {{ block.title }}
            </h4>
            <p class="mt-4 text-base font-medium text-neutral-500 leading-7">
              {{ block.description }}
            </p>
            <div class="mt-6 -mx-8 -mb-8">
              <img
                :src="block.image"
                :alt="block.title"
                class="w-full rounded-t-xl object-cover"
                loading="lazy"
              >
            </div>
          </div>
        </div>
        <div class="grid gap-8 sm:grid-cols-1 lg:grid-cols-3">
          <div
            v-for="block in teamFeatureBlocks.slice(2)"
            :key="block.title"
            class="overflow-hidden rounded-3xl bg-neutral-50 p-8 shadow-sm ring-1 ring-neutral-200"
          >
            <h4 class="text-xl font-semibold text-neutral-900">
              {{ block.title }}
            </h4>
            <p class="mt-4 text-base font-medium text-neutral-500 leading-7">
              {{ block.description }}
            </p>
            <div class="mt-6 -mx-8 -mb-8">
              <img
                :src="block.image"
                :alt="block.title"
                class="w-full rounded-t-xl object-cover"
                loading="lazy"
              >
            </div>
          </div>
        </div>
      </div>

      <div class="mt-10 text-center">
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <UButton
            size="lg"
            :to="{ name: 'enterprise' }"
            trailing-icon="i-heroicons-arrow-up-right-20-solid"
            label="Learn about Enterprise"
          />
          <UButton
            :to="{ name: 'pricing' }"
            label="View Pricing"
            variant="outline"
            color="neutral"
            size="lg"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import opnformConfig from '~/opnform.config.js'

const { isAuthenticated: authenticated } = useIsAuthenticated()

const shareCards = [
  {
    key: 'link',
    size: 'large',
    title: 'Share with a simple link',
    icon: 'i-heroicons-link',
    imageSrc: '/img/pages/welcome/share-1.png'
  },
  {
    key: 'embed',
    size: 'small',
    title: 'Embed on your website or app',
    icon: 'i-heroicons-code-bracket-square',
    imageSrc: '/img/pages/welcome/share-2.png'
  },
  {
    key: 'notion',
    size: 'small',
    title: 'Add forms directly into Notion',
    icon: 'i-simple-icons-notion',
    imageSrc: '/img/pages/welcome/share-3.png'
  },
  {
    key: 'domain',
    size: 'small',
    title: 'Use your own custom domain',
    icon: 'i-heroicons-globe-alt',
    imageSrc: '/img/pages/welcome/share-4.png'
  },
  {
    key: 'qr',
    size: 'small',
    title: 'Generate QR codes for offline collection',
    icon: 'i-heroicons-qr-code',
    imageSrc: '/img/pages/welcome/share-5.png'
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

const teamFeatureBlocks = [
  {
    title: 'Collaboration & Management',
    description: 'Coordinate work across teams with shared workspaces, clear roles, and full audit visibility.',
    image: '/img/pages/welcome/team-1.png',
  },
  {
    title: 'Security & Compliance',
    description: 'Stay aligned with internal requirements through SSO access, domain control, and elevated support.',
    image: '/img/pages/welcome/team-2.png',
  },
  {
    title: 'Branding Control',
    description: 'Fully brand your forms or white-label them for clients.',
    image: '/img/pages/welcome/team-3.png',
  },
  {
    title: 'Audit History',
    description: 'Full visibility into form and submission activity.',
    image: '/img/pages/welcome/team-4.png',
  },
  {
    title: 'Priority Support',
    description: 'Get help fast with dedicated enterprise support.',
    image: '/img/pages/welcome/team-5.png',
  }
]
</script>
