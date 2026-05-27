<template>
  <div class="relative">
    <div
      v-if="showNotionPage || loading"
      class="w-full flex justify-center"
    >
      <div class="w-full md:max-w-3xl md:mx-auto px-4 pt-8 md:pt-16 pb-10">
        <p class="mb-4 text-sm">
          <UButton
            :to="{ name: 'integrations' }"
            variant="ghost"
            color="neutral"
            class="mb-4"
            icon="i-heroicons-arrow-left"
          >
            Other Integrations
          </UButton>
        </p>
        <h1 class="text-3xl mb-2">
          {{ pageTitle }}
        </h1>
        <NotionPage
          :block-map="page.blocks"
          :loading="loading"
          :block-overrides="blockOverrides"
          :map-page-url="mapPageUrl"
        />
        <p class="text-sm">
          <NuxtLink
            :to="{ name: 'integrations' }"
            class="text-blue-500 hover:text-blue-700 inline-block"
          >
            Discover our other Integrations
          </NuxtLink>
        </p>
      </div>
    </div>
    <div
      v-else-if="showFallbackPage"
      class="w-full md:max-w-3xl md:mx-auto px-4 pt-8 md:pt-16 pb-10"
    >
      <p class="mb-4 text-sm">
        <UButton
          :to="{ name: 'integrations' }"
          variant="ghost"
          color="neutral"
          class="mb-4"
          icon="i-heroicons-arrow-left"
        >
          Other Integrations
        </UButton>
      </p>

      <div class="rounded-3xl border border-neutral-200 bg-white p-8 shadow-sm">
        <div class="flex items-start gap-4">
          <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-neutral-200 bg-neutral-50">
            <Icon
              :name="fallbackIntegration.icon"
              class="h-8 w-8"
              dynamic
            />
          </div>

          <div class="min-w-0">
            <p class="text-sm font-medium text-blue-600">
              {{ fallbackIntegration.section_name }}
            </p>
            <h1 class="mt-1 text-3xl font-semibold text-neutral-900">
              {{ fallbackIntegration.name }}
            </h1>
            <p class="mt-3 text-base text-neutral-600">
              {{ fallbackDescription }}
            </p>
          </div>
        </div>

        <div class="mt-8">
          <h2 class="text-lg font-semibold text-neutral-900">
            Setup overview
          </h2>
          <ul class="mt-4 space-y-3">
            <li
              v-for="step in fallbackSteps"
              :key="step"
              class="flex items-start gap-3 text-neutral-700"
            >
              <span class="mt-1 text-green-500">✔</span>
              <span>{{ step }}</span>
            </li>
          </ul>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
          <UButton
            v-if="fallbackIntegration.url"
            :href="fallbackIntegration.url"
            target="_blank"
            external
          >
            Open {{ fallbackIntegration.name }}
          </UButton>

          <UButton
            v-if="fallbackIntegration.crisp_help_page_slug"
            color="neutral"
            variant="outline"
            @click="crisp.openHelpdeskArticle(fallbackIntegration.crisp_help_page_slug)"
          >
            View Help Article
          </UButton>
        </div>
      </div>
    </div>
    <div
      v-else
      class="w-full md:max-w-3xl md:mx-auto px-4 pt-8 md:pt-16 pb-10"
    >
      <h1 class="text-3xl">
        Whoops - Page not found
      </h1>
      <UButton
        :to="{name: 'index'}"
        class="mt-4"
        label="Go Home"
      />
    </div>
    <OpenFormFooter class="border-t" />
  </div>
</template>

<script setup>
import CustomBlock from '~/components/pages/notion/CustomBlock.vue'
import integrationsCatalog from '~/data/forms/integrations.json'
import { useNotionCmsStore } from '~/stores/notion_cms.js'

const blockOverrides = { code: CustomBlock }
const slug = computed(() => useRoute().params.slug)
const dbId = '1eda631bec208005bd8ed9988b380263'

const crisp = useCrisp()
const notionCmsStore = useNotionCmsStore()
const loading = computed(() => notionCmsStore.loading)

await notionCmsStore.loadDatabase(dbId)
await notionCmsStore.loadPageBySlug(slug.value)

const page = notionCmsStore.pageBySlug(slug.value)
const fallbackIntegration = computed(() => integrationsCatalog[slug.value] ?? null)
const showNotionPage = computed(() => {
  return !!(page.value && page.value.blocks && published.value)
})
const showFallbackPage = computed(() => {
  return !!(!showNotionPage.value && fallbackIntegration.value)
})
const published = computed(() => {
  if (!page.value) return false
  return page.value.Published ?? page.value.published ?? false
})
const pageTitle = computed(() => {
  return page.value?.Title
    ?? page.value?.['Integration Name']
    ?? page.value?.Name
    ?? fallbackIntegration.value?.name
    ?? 'Integration'
})

function buildFallbackSteps (integration) {
  if (!integration) return []

  if (integration.is_external) {
    return [
      'Open the integration provider',
      'Connect your OpnForm account',
      'Configure your automation',
      'Test and activate it'
    ]
  }

  return [
    'Open your form integrations',
    'Choose this integration',
    'Configure the connection settings',
    'Save and test it'
  ]
}

function buildFallbackDescription (integration) {
  if (!integration) return ''

  if (integration.section_name === 'Notifications') {
    return 'Send submission alerts to your team in real time.'
  }

  if (integration.section_name === 'Databases') {
    return 'Sync form submissions with your spreadsheet or database tools.'
  }

  return 'Connect OpnForm with your automation tools and workflows.'
}

const fallbackSteps = computed(() => buildFallbackSteps(fallbackIntegration.value))
const fallbackDescription = computed(() => buildFallbackDescription(fallbackIntegration.value))

const mapPageUrl = (pageId) => {
  // Get everything before the ?
  pageId = pageId.split('?')[0]
  const page = notionCmsStore.pages[pageId]
  const slug = page?.slug ?? page?.Slug ?? null

  if (!slug) {
    return useRouter().resolve({ name: 'integrations' }).href
  }

  return useRouter().resolve({ name: 'integrations-slug', params: { slug } }).href
}

defineRouteRules({
  swr: 3600
})
definePageMeta({
  stickyNavbar: true,
  middleware: ['root-redirect','self-hosted']
})

useOpnSeoMeta({
  title: () => pageTitle.value,
  description: () => page.value?.['Summary - SEO description'] ?? fallbackDescription.value ?? 'Create beautiful forms for free. Unlimited fields, unlimited submissions.'
})
</script>
