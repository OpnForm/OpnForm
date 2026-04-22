<template>
  <div class="of-page flex flex-col min-h-full">
    <Breadcrumb :path="breadcrumbs" />

    <p
      v-if="industry === null || !industry"
      class="text-center my-4"
    >
      We could not find this industry.
    </p>
    <template v-else>
      <section class="py-12 sm:py-16 bg-of-surface-muted border-b border-of-border">
        <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
          <div class="text-center mx-auto">
            <div class="of-eyebrow sm:w-full mb-3">
              {{ industry.name }}
            </div>
            <h1
              class="of-heading text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight"
            >
              {{ industry.meta_title }}
            </h1>
            <p class="of-copy max-w-xl mx-auto mt-4 text-lg font-normal">
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
          <section class="py-12 bg-of-surface border-t border-of-border sm:py-16">
            <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
              <p class="of-copy font-normal">
                {{ industry.description }}
              </p>
            </div>
          </section>
        </template>
      </templates-list>
    </template>

    <open-form-footer class="mt-8 border-t border-of-border" />
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

const { data: allTemplates, isLoading: loading } = list()

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
</script>

<style lang="scss">
.nf-text {
  @apply space-y-4;
  h2 {
    @apply text-sm font-normal tracking-widest text-of-subtle uppercase;
  }

  p {
    @apply font-normal leading-7 text-of-ink dark:text-neutral-100;
  }

  ol {
    @apply list-decimal list-inside;
  }

  ul {
    @apply list-disc list-inside;
  }
}
</style>
