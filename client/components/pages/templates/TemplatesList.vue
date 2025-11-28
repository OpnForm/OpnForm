<template>
  <div>
    <section class="bg-white">
        <VForm
          size="sm"
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 relative z-20"
        >
          <div class="flex items-center gap-2">
            <div class="flex-1 sm:flex-none">
              <select-input
                v-if="filterTypes"
                v-model="selectedType"
                name="type"
                :options="typesOptions"
                class="w-full sm:w-auto md:min-w-48"
              />
            </div>
            <div class="flex-1 sm:flex-none">
              <select-input
                v-if="filterIndustries"
                v-model="selectedIndustry"
                name="industry"
                :options="industriesOptions"
                class="w-full sm:w-auto md:min-w-48"
              />
            </div>
          </div>
          <div class="flex-1 w-full md:max-w-xs">
            <text-input
              v-model="search"
              autocomplete="off"
              name="search"
              placeholder="Search..."
            />
          </div>
        </VForm>

        <div
          v-if="loading"
          class="relative z-10"
        >
          <div
            class="grid gap-8 sm:gap-y-12"
            :class="gridClasses"
          >
            <div
              v-for="i in 8"
              :key="i"
              class="w-full"
            >
              <!-- Template Card Skeleton -->
              <div class="w-full">
                <!-- Image Skeleton -->
                <USkeleton class="aspect-[4/3] rounded-lg w-full" />
                
                <!-- Title Skeleton -->
                <USkeleton class="h-6 mt-4 mb-2 w-full" />
                
                <!-- Description Skeleton -->
                <div class="space-y-2 mt-2 mb-4">
                  <USkeleton class="h-4 w-full" />
                  <USkeleton class="h-4 w-3/4" />
                </div>
                
                <!-- Tags Skeleton -->
                <div class="flex flex-wrap gap-2 mt-4">
                  <USkeleton class="h-6 rounded-full w-16" />
                  <USkeleton class="h-6 rounded-full w-20" />
                  <USkeleton class="h-6 rounded-full w-14" />
                </div>
              </div>
            </div>
          </div>
        </div>
        <p
          v-else-if="enrichedTemplates.length === 0"
          class="text-center mt-4"
        >
          No templates found.
        </p>
        <div
          v-else
          class="relative z-10"
        >
          <div
            class="grid gap-8 sm:gap-y-12"
            :class="gridClasses"
          >
            <single-template
              v-for="template in enrichedTemplates"
              :key="template.id"
              :template="template"
            />
          </div>
        </div>
    </section>

    <slot name="before-lists" />

    <section
      v-if="showTypes"
      class="py-12 bg-white border-t border-neutral-200 sm:py-16"
    >
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="flex items-center justify-between">
          <h4
            class="text-xl font-bold tracking-tight text-neutral-900 sm:text-2xl"
          >
            All Types
          </h4>
          <UButton
            v-if="$route.name !== 'templates'"
            :to="{ name: 'templates' }"
            color="neutral"
            size="sm"
            trailing-icon="i-heroicons-arrow-right"
            label="View All Templates"
          />
        </div>

        <div
          class="grid grid-cols-1 gap-x-8 gap-y-4 mt-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
          <NuxtLink
            v-for="row in types"
            :key="row.slug"
            :to="{ params: { slug: row.slug }, name: 'templates-types-slug' }"
            :title="row.name"
            class="text-neutral-600 dark:text-neutral-400 transition-colors duration-300 hover:text-blue-500"
          >
            {{ row.name }}
          </NuxtLink>
        </div>
      </div>
    </section>

    <section
      v-if="showIndustries"
      class="py-12 bg-white border-t border-neutral-200 sm:py-16"
    >
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="flex items-center justify-between">
          <h4
            class="text-xl font-bold tracking-tight text-neutral-900 sm:text-2xl"
          >
            All Industries
          </h4>
          <UButton
            v-if="$route.name !== 'templates'"
            :to="{ name: 'templates' }"
            color="neutral"
            size="sm"
            trailing-icon="i-heroicons-arrow-right"
            label="View All Templates"
          />
        </div>

        <div
          class="grid grid-cols-1 gap-x-8 gap-y-4 mt-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
          <NuxtLink
            v-for="row in industries"
            :key="row.slug"
            :to="{
              params: { slug: row.slug },
              name: 'templates-industries-slug',
            }"
            :title="row.name"
            class="text-neutral-600 dark:text-neutral-400 transition-colors duration-300 hover:text-blue-500"
          >
            {{ row.name }}
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { useFuse } from '@vueuse/integrations/useFuse'
import SingleTemplate from "./SingleTemplate.vue"
import { refDebounced } from "@vueuse/core"
import { useTemplateMeta } from "~/composables/data/useTemplateMeta"

const props = defineProps({
  templates: {
    type: Array,
    default: () => [],
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  showTypes: {
    type: Boolean,
    default: true,
  },
  filterTypes: {
    type: Boolean,
    default: true,
  },
  showIndustries: {
    type: Boolean,
    default: true,
  },
  filterIndustries: {
    type: Boolean,
    default: true,
  },
  gridClasses: {
    type: String,
    default: "grid-cols-1 mt-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4",
  },
})

const { industries: industriesMap, types: typesMap } = useTemplateMeta()

const industries = computed(() => [...industriesMap.values()])
const types = computed(() => [...typesMap.values()])

const search = ref("")
const debouncedSearch = refDebounced(search, 500)

const selectedType = ref("all")
const selectedIndustry = ref("all")

const industriesOptions = computed(() => {
  return [{ name: "All Industries", value: "all" }].concat(
    industries.value.map((industry) => ({
      name: industry.name,
      value: industry.slug,
    })),
  )
})

const typesOptions = computed(() => {
  return [{ name: "All Types", value: "all" }].concat(
    types.value.map((type) => ({
      name: type.name,
      value: type.slug,
    })),
  )
})

const filteredBase = computed(() => {
  let list = props.templates

  if (props.filterTypes && selectedType.value && selectedType.value !== "all") {
    list = list.filter((item) => {
      return item.types && item.types.length > 0
        ? item.types.includes(selectedType.value)
        : false
    })
  }

  if (props.filterIndustries && selectedIndustry.value && selectedIndustry.value !== "all") {
    list = list.filter((item) => {
      return item.industries && item.industries.length > 0
        ? item.industries.includes(selectedIndustry.value)
        : false
    })
  }

  return list
})

const { results: fuseResults } = useFuse(
  debouncedSearch,
  filteredBase,
  {
    fuseOptions: {
      keys: ["name", "slug", "description", "short_description"],
      threshold: 0.3,
      ignoreLocation: true,
      includeScore: false,
    },
    matchAllWhenSearchEmpty: true,
  }
)

const enrichedTemplates = computed(() => {
  const base = filteredBase.value
  const results = fuseResults.value
  return results && results.length > 0 ? results.map(r => r.item) : base
})
</script>
