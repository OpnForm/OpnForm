<template>
  <div class="flex flex-col min-h-full">
    <Breadcrumb :path="breadcrumbs" />

    <p
      v-if="industry === null || !industry"
      class="text-center my-4"
    >
      We could not find this industry.
    </p>
    <template v-else>
      <section class="py-12 sm:py-16 bg-neutral-50 border-b border-neutral-200">
        <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
          <div class="text-center mx-auto">
            <div class="font-semibold sm:w-full text-blue-500 mb-3">
              {{ industry.name }}
            </div>
            <h1
              class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-neutral-900"
            >
              {{ industry.meta_title }}
            </h1>
            <p class="max-w-xl mx-auto text-neutral-600 mt-4 text-lg font-normal">
              {{ industry.meta_description }}
            </p>
          </div>
        </div>
      </section>

      <templates-list
        :templates="templates"
        :loading="loading"
        :filter-industries="false"
        :show-industries="false"
      >
        <template #before-lists>
          <section class="py-12 bg-white border-t border-neutral-200 sm:py-16">
            <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
              <p class="text-neutral-600 font-normal">
                {{ industry.description }}
              </p>
            </div>
          </section>
        </template>
      </templates-list>
    </template>

    <open-form-footer class="mt-8 border-t" />
  </div>
</template>

<script setup>
import { computed } from "vue"
import Breadcrumb from "~/components/app/Breadcrumb.vue"
import { useTemplateMeta } from "~/composables/data/useTemplateMeta"

defineRouteRules({
  swr: 3600,
})

const route = useRoute()
const { list } = useTemplates()
const { industries: industriesMap } = useTemplateMeta()

const { data: allTemplates, isLoading: loading, suspense: templatesSuspense } = list()

if (import.meta.server) {
  await templatesSuspense().catch(() => null)
}

const industry = computed(() => industriesMap.get(route.params.slug))

// Computed
const templates = computed(() => {
  if (!allTemplates.value) return []
  return allTemplates.value.filter((item) => {
    return item.industries && item.industries.length > 0
      ? item.industries.includes(route.params.slug)
      : false
  })
})
const breadcrumbs = computed(() => {
  if (!industry.value) {
    return [{ route: { name: "templates" }, label: "Templates" }]
  }
  return [
    { route: { name: "templates" }, label: "Templates" },
    { label: industry.value.name },
  ]
})

useOpnSeoMeta({
  title: () => {
    if (!industry.value) return "Form Templates"
    if (industry.value.meta_title.length > 60) {
      return industry.value.meta_title
    }
    return industry.value.meta_title
  },
  description: () =>
    industry.value
      ? industry.value.meta_description
      : "Our collection of beautiful templates to create your own forms!",
})
useHead({
  titleTemplate: (titleChunk) => {
    // Disable title template for longer titles
    if (
      industry.value &&
      industry.value.meta_title.length < 60 &&
      !industry.value.meta_title.toLowerCase().includes("opnform")
    ) {
      return titleChunk
        ? `${titleChunk} - OpnForm`
        : "Form Templates - OpnForm"
    }
    return titleChunk ? titleChunk : "Form Templates - OpnForm"
  },
})

const templateIndustrySchema = computed(() => {
  if (!industry.value) return null

  return buildSchemaGraph([
    buildCollectionPageSchema({
      name: industry.value.meta_title,
      description: industry.value.meta_description,
      path: `/templates/industries/${industry.value.slug}`,
    }),
    buildBreadcrumbSchema([
      { name: "Home", path: "/" },
      { name: "Templates", path: "/templates" },
      { name: industry.value.name, path: `/templates/industries/${industry.value.slug}` },
    ]),
    buildItemListSchema(
      templates.value.map((template) => ({
        name: template.name,
        path: `/templates/${template.slug}`,
      })),
      {
        path: `/templates/industries/${industry.value.slug}`,
        name: `${industry.value.name} templates`,
      },
    ),
  ])
})

useJsonLd("template-industry-schema", templateIndustrySchema)
</script>

<style lang="scss">
.nf-text {
  @apply space-y-4;
  h2 {
    @apply text-sm font-normal tracking-widest text-neutral-500 uppercase;
  }

  p {
    @apply font-normal leading-7 text-neutral-900 dark:text-neutral-100;
  }

  ol {
    @apply list-decimal list-inside;
  }

  ul {
    @apply list-disc list-inside;
  }
}
</style>
