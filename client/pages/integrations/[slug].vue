<template>
  <div class="relative">
    <div
      v-if="(page && page.blocks && published) || loading"
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
          {{ page.Title }}
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
import { useNotionCmsStore } from '~/stores/notion_cms.js'

const blockOverrides = { code: CustomBlock }
const slug = computed(() => useRoute().params.slug)
const dbId = '1eda631bec208005bd8ed9988b380263'

const notionCmsStore = useNotionCmsStore()
const loading = computed(() => notionCmsStore.loading)

await notionCmsStore.loadDatabase(dbId)
await notionCmsStore.loadPageBySlug(slug.value)

const page = notionCmsStore.pageBySlug(slug.value)
const published = computed(() => {
  if (!page.value) return false
  return page.value.Published ?? page.value.published ?? false
})

const mapPageUrl = (pageId) => {
  // Get everything before the ?
  pageId = pageId.split('?')[0]
  const page = notionCmsStore.pages[pageId]
  const slug = page.slug ?? page.Slug ?? null
  return useRouter().resolve({ name: 'integrations', params: { slug } }).href
}

defineRouteRules({
  swr: 3600
})
definePageMeta({
  stickyNavbar: true,
  middleware: ['root-redirect','self-hosted']
})

useOpnSeoMeta({
  title: () => page.value.Name,
  description: () => page.value['Summary - SEO description'] ?? 'Create beautiful forms for free. Unlimited fields, unlimited submissions.'
})
</script>
