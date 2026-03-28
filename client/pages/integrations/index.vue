<template>
  <div class="relative">
    <div class="mt-2 flex flex-col">
      <div
        v-if="loading"
        class="bg-white py-12 px-4 sm:px-6 lg:px-8"
      >
        <loader class="mx-auto h-6 w-6" />
      </div>
      <div
        v-else
        class="bg-white py-12 px-4 sm:px-6 lg:px-8"
      >
        <div class="max-w-6xl mx-auto">
          <h1 class="text-3xl font-bold text-center text-neutral-900">
            Available Integrations
          </h1>
          <p class="text-center text-neutral-600 mt-2 mb-10">
            Explore our powerful Integrations
          </p>

          <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="integration in integrationsList"
              :key="integration.title"
              class="relative rounded-2xl bg-neutral-50 p-6 shadow-sm border border-neutral-200 hover:shadow-lg transition-all duration-300 hover:bg-white"
            >
              <a
                :href="`/integrations/${integration.slug}`"
                class="absolute inset-0"
              />
              <div
                v-if="integration.popular"
                class="absolute -top-2 -left-3 -rotate-12 bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-sm shadow-sm"
              >
                Most Popular
              </div>
              <div class="flex justify-between items-start">
                <div class="w-10 h-10 bg-white border border-neutral-200 rounded-xl flex items-center justify-center">
                  <Icon
                    :name="integration.icon"
                    class="w-8 h-8"
                    dynamic
                  />
                </div>
                <a
                  href="#"
                  class="text-sm text-blue-500 font-medium hover:underline flex items-center gap-1"
                >
                  Setup Guide
                  <Icon
                    name="heroicons:arrow-top-right-on-square"
                    class="w-4 h-4 flex-shrink-0"
                    dynamic
                  />
                </a>
              </div>

              <h3 class="mt-4 text-lg font-semibold text-neutral-900">
                {{ integration.title }}
              </h3>
              <p class="text-sm text-neutral-500 mt-1">
                {{ integration.description }}
              </p>

              <ul class="mt-4 space-y-2 text-sm text-neutral-700">
                <li
                  v-for="step in integration.steps"
                  :key="step" 
                  class="flex items-center gap-2"
                >
                  <span class="text-green-500">✔</span> {{ step }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>


      <div class="bg-white p-10 max-w-6xl mx-auto">
        <h2 class="text-4xl font-bold text-center text-neutral-900 mb-2">
          Integration General Setup Guides
        </h2>
        <p class="text-center text-neutral-600 mb-12">
          This can be another text
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 text-neutral-800 max-w-6xl mx-auto">
          <div
            v-for="guide in setupGuides"
            :key="guide.title"
          >
            <h2 class="text-xl font-semibold mb-4">
              {{ guide.title }}
            </h2>
            <ol class="space-y-4 text-base">
              <li
                v-for="(step, index) in guide.steps"
                :key="step"
                class="flex items-start gap-3"
              >
                <span class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-700 font-bold">{{ index + 1 }}</span>
                <span v-html="step" />
              </li>
            </ol>
          </div>
        </div>
      </div>

      <div class="bg-[#f4f9ff] max-w-6xl mx-auto rounded-3xl m-10 p-10 flex justify-between items-center">
        <div class="max-w-md">
          <h2 class="text-3xl font-bold text-neutral-900">
            Need help?
          </h2>
          <p class="mt-2 text-neutral-500 text-lg">
            Visit our Help Center for detailed documentation!
          </p>
          <a
            href="#"
                            class="inline-flex items-center gap-2 mt-6 px-4 py-2 bg-blue-500 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-blue-600 transition"
            @click.prevent="crisp.openHelpdesk()"
          >
            Help Center
            <Icon
              name="heroicons:arrow-top-right-on-square"
              class="w-4 h-4 flex-shrink-0"
              dynamic
            />
          </a>
        </div>
        <div class="hidden lg:grid grid-cols-2 gap-4">
          <div class="space-y-4">
            <div class="bg-white p-4 rounded-2xl shadow-sm w-64 h-20">
              <div class="bg-neutral-200 w-24 h-3 mb-2 rounded-sm" />
              <div class="bg-neutral-200 w-full h-6 rounded-full" />
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm w-64 h-20">
              <div class="bg-neutral-200 w-24 h-3 mb-2 rounded-sm" />
              <div class="bg-neutral-200 w-full h-6 rounded-full" />
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm w-64 h-20">
              <div class="bg-neutral-200 w-24 h-3 mb-2 rounded-sm" />
              <div class="bg-neutral-200 w-full h-6 rounded-full" />
            </div>
          </div>
          <div class="space-y-4 pt-8">
            <div class="bg-white p-4 rounded-2xl shadow-sm w-64 h-20">
              <div class="bg-neutral-200 w-24 h-3 mb-2 rounded-sm" />
              <div class="bg-neutral-200 w-full h-6 rounded-full" />
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm w-64 h-20">
              <div class="bg-neutral-200 w-24 h-3 mb-2 rounded-sm" />
              <div class="bg-neutral-200 w-full h-6 rounded-full" />
            </div>
          </div>
        </div>
      </div>
    </div>
    <OpenFormFooter />
  </div>
</template>

<script setup>
import { useNotionCmsStore } from '~/stores/notion_cms.js'

useOpnSeoMeta({
  title: 'Integrations',
  description:
    'Create beautiful forms for free. Unlimited fields, unlimited submissions.'
})
defineRouteRules({
  swr: 3600
})
definePageMeta({
  stickyNavbar: true,
  middleware: ['root-redirect','self-hosted']
})

const crisp = useCrisp()

const dbId = '1eda631bec208005bd8ed9988b380263'
const notionCmsStore = useNotionCmsStore()
const loading = computed(() => notionCmsStore.loading)
await notionCmsStore.loadDatabase(dbId)
const pages = notionCmsStore.databasePages(dbId)

const integrationsList = computed(() => {
  if (!pages.value) return []
  return Object.values(pages.value).filter(page => page.Published).map(page => ({
    title: page['Integration Name'] ?? page.Name,
    description: page.Summary ?? '',
    icon: page.Icon ?? 'i-heroicons-envelope-20-solid',
    slug: page.slug,
    steps: (page.Steps) ? page.Steps.split('\n') : [],
    popular: page['Most Popular'] ?? false
  }))
})


const setupGuides = [
  {
    title: 'Email Integration Setup',
    steps: [
      'Navigate to <b>OpnForm</b> > <b>Integrations</b>.',
      'Select <b>Email</b> and configure SMTP settings.',
      'Set up email rules for notifications.',
      'Save & activate email alerts.'
    ]
  },
  {
    title: 'Slack Integration Setup',
    steps: [
      'Navigate to <b>OpnForm</b> > <b>Integrations</b>.',
      'Select <b>Slack</b> and authorize your workspace.',
      'Choose a channel & customize messages.',
      'Save & activate Slack alerts.'
    ]
  },
  {
    title: 'WebHook Integration Setup',
    steps: [
      'Navigate to <b>OpnForm</b> > <b>Integrations</b>.',
      'Select <b>WebHook</b> and enter your endpoint URL.',
      'Map fields & configure triggers.',
      'Save & activate WebHook alerts.'
    ]
  }
]

</script>

<style lang='scss'>
.integration-page {
  .notion-asset-wrapper {
    max-width: 200px;
  }
}
</style>
