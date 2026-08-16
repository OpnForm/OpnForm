<template>
  <div class="flex flex-col min-h-full">
    <Breadcrumb :path="breadcrumbs" />

    <p
      v-if="type === null || !type"
      class="text-center my-4"
    >
      We could not find this type.
    </p>
    <template v-else>
      <section class="py-12 sm:py-16 bg-neutral-50 border-b border-neutral-200">
        <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
          <div class="text-center mx-auto">
            <div class="font-semibold sm:w-full text-blue-500 mb-3">
              {{ type.name }}
            </div>
            <h1
              class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-neutral-900"
            >
              {{ type.meta_title }}
            </h1>
            <p class="max-w-xl mx-auto text-neutral-600 mt-4 text-lg font-normal">
              {{ type.meta_description }}
            </p>
          </div>
        </div>
      </section>

      <templates-list
        :templates="templates"
        :loading="loading"
        :filter-types="false"
        :show-industries="false"
      >
        <template #before-lists>
          <section class="py-12 bg-white border-t border-neutral-200 sm:py-16">
            <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
              <p class="text-neutral-600 font-normal">
                {{ type.description }}
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
import OpenFormFooter from "../../../components/pages/OpenFormFooter.vue"
import Breadcrumb from "~/components/app/Breadcrumb.vue"
import { useTemplateMeta } from "~/composables/data/useTemplateMeta"

defineRouteRules({
  swr: 3600,
})

const route = useRoute()
const { list } = useTemplates()
const { types: typesMap } = useTemplateMeta()

const { data: allTemplates, isLoading: loading, suspense: templatesSuspense } = list()

if (import.meta.server) {
  await templatesSuspense().catch(() => null)
}

const type = computed(() => typesMap.get(route.params.slug))

// Computed
const templates = computed(() => {
  if (!allTemplates.value) return []
  return allTemplates.value.filter((item) => {
    return item.types && item.types.length > 0
      ? item.types.includes(route.params.slug)
      : false
  })
})
const breadcrumbs = computed(() => {
  if (!type.value) {
    return [{ route: { name: "templates" }, label: "Templates" }]
  }
  return [
    { route: { name: "templates" }, label: "Templates" },
    { label: type.value.name },
  ]
})

useOpnSeoMeta({
  title: () => {
    if (!type.value) return "Form Templates"
    if (type.value.meta_title.length > 60) {
      return type.value.meta_title
    }
    return type.value.meta_title
  },
  description: () =>
    type.value
      ? type.value.meta_description
      : "Our collection of beautiful templates to create your own forms!",
})
useHead({
  titleTemplate: (titleChunk) => {
    // Disable title template for longer titles
    if (
      type.value &&
      type.value.meta_title.length < 60 &&
      !type.value.meta_title.toLowerCase().includes("opnform")
    ) {
      return titleChunk
        ? `${titleChunk} - OpnForm`
        : "Form Templates - OpnForm"
    }
    return titleChunk ? titleChunk : "Form Templates - OpnForm"
  },
})

const templateTypeSchema = computed(() => {
  if (!type.value) return null

  return buildSchemaGraph([
    buildCollectionPageSchema({
      name: type.value.meta_title,
      description: type.value.meta_description,
      path: `/templates/types/${type.value.slug}`,
    }),
    buildBreadcrumbSchema([
      { name: "Home", path: "/" },
      { name: "Templates", path: "/templates" },
      { name: type.value.name, path: `/templates/types/${type.value.slug}` },
    ]),
    buildItemListSchema(
      templates.value.map((template) => ({
        name: template.name,
        path: `/templates/${template.slug}`,
      })),
      {
        path: `/templates/types/${type.value.slug}`,
        name: `${type.value.name} templates`,
      },
    ),
  ])
})

useJsonLd("template-type-schema", templateTypeSchema)
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
