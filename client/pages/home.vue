<template>
  <div v-if="workspace" class="flex flex-col h-full bg-white">

    <div class="sticky top-0 z-50 bg-white h-[49px] border-b border-neutral-200 p-2 sm:px-4">
      <div class="max-w-4xl mx-auto flex items-center justify-between flex-shrink-0 gap-2 px-2 sm:px-0">

      <VTransition name="fade">
      <div v-if="(forms?.length > 0) || isFilteringForms" class="flex items-center gap-2">

        <UInput
          v-model="search"
          placeholder="Search forms..."
          icon="i-heroicons-magnifying-glass-solid"

        />

        <USelectMenu
          v-if="allTags.length > 0"
          v-model="selectedTags"
          :items="tagOptions"
          multiple
          placeholder="Tags"
          class="hidden sm:block"
          :ui="{ content: 'min-w-fit' }"
        />

        <UButton
          v-if="isFilteringForms"
          label="Clear"
          variant="ghost"
          color="neutral"
          @click="clearFilters"
        />
      </div>
      <div class="grow" v-else />
      </VTransition>

      <TrackClick name="home_top_bar_create_form_click">
        <UButton
          v-if="!workspace?.is_readonly"
          icon="i-heroicons-plus"
          label="Create Form"
          :to="{ name: 'forms-create' }"
        />
      </TrackClick>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4">
      <div class="max-w-4xl mx-auto">
        <ClientOnly>
          <VTransition name="fade">
            <div v-if="isFetched && !isFormsLoading && (forms?.length === 0)" class="text-center py-16 px-4">
              <UIcon name="i-heroicons-document-plus" class="h-12 w-12 text-neutral-400 mx-auto" />
              <h3 class="mt-4 text-lg font-semibold text-neutral-900">
                Create your first form
              </h3>
              <p class="mt-1 text-sm text-neutral-500">
                Get started by creating a new form to collect responses.
              </p>
              <UButton
                v-if="!workspace?.is_readonly"
                class="mt-6"
                icon="i-heroicons-plus"
                label="Create Form"
                :to="{ name: 'forms-create' }"
              />
            </div>

            <div v-if="isFetched && !isFormsLoading && (forms?.length > 0) && enrichedForms.length === 0" class="text-center py-16 px-4">
                <UIcon name="i-heroicons-magnifying-glass" class="h-12 w-12 text-neutral-400 mx-auto" />
                <h3 class="mt-4 text-lg font-semibold text-neutral-900">
                  No forms found
                </h3>
                <p class="mt-1 text-sm text-neutral-500">
                  Your search and filter criteria did not match any forms.
                </p>
                <UButton
                  v-if="isFilteringForms"
                  class="mt-6"
                  label="Clear Filters"
                  variant="soft"
                  @click="clearFilters"
                />
            </div>

            <div v-if="enrichedForms.length > 0" class="mb-10">
              <div class="flex flex-col gap-2 lg:mt-6">
                <FormCard
                  v-for="form in enrichedForms"
                  :key="form.id"
                  :form="form"
                />
              </div>

              <div v-if="isLoadingMore" class="flex flex-col gap-2 mt-2">
                <FormCardSkeleton />
                <FormCardSkeleton class="opacity-60" />
                <FormCardSkeleton class="opacity-30" />
              </div>
              
              <div v-else-if="!isComplete && totalPages > 1" class="flex justify-center items-center py-4">
                <div class="text-sm text-neutral-500">
                  Loaded {{ currentPage }} of {{ totalPages }} pages
                </div>
              </div>
              
              <UpgradeBanner v-if="enrichedForms.length >= 2" class="mt-2" />
            </div>

            <div v-if="isFormsLoading" class="flex flex-col gap-2">
              <FormCardSkeleton />
              <FormCardSkeleton />
              <FormCardSkeleton />
            </div>
          </VTransition>
          <template #fallback>
            <div class="flex flex-col gap-2">
              <FormCardSkeleton />
              <FormCardSkeleton />
              <FormCardSkeleton />
            </div>
          </template>
        </ClientOnly>
      </div>
    </div>
    <div id="home-portals" class="z-20" />

    <YearlyUpgradeModal />
  </div>
</template>

<script setup>
import { useFuse } from '@vueuse/integrations/useFuse'
import FormCard from '~/components/pages/home/FormCard.vue'
import FormCardSkeleton from '~/components/pages/home/FormCardSkeleton.vue'
import TrackClick from '~/components/global/TrackClick.vue'
import UpgradeBanner from "~/components/dashboard/UpgradeBanner.vue"

definePageMeta({
  middleware: ["auth"],
  layout: "dashboard",
})

useOpnSeoMeta({
  title: "Your Forms",
  description:
    "All of your OpnForm are here. Create new forms, or update your existing forms.",
})

// Composables
const { current: workspace, currentId: workspaceId } = useCurrentWorkspace()

const {
  forms,
  isLoading: isFormsLoading,
  isFetchingNextPage: isLoadingMore,
  isFetched,
  currentPage,
  totalPages,
  isComplete,
} = useFormsList(
  workspaceId,
  {
    fetchAll: true,
    enabled: computed(() => import.meta.client && !!workspaceId.value),
  }
)

// State
const search = ref("")
const debouncedSearch = refDebounced(search, 500)
const selectedTags = ref([])

// Methods
const clearFilters = () => {
  search.value = ""
  selectedTags.value = []
}

// Computed
const isFilteringForms = computed(() => {
  return (
    (search.value !== "" && search.value !== null) ||
    selectedTags.value.length > 0
  )
})

// Extract all unique tags from forms
const allTags = computed(() => {
  if (!forms.value) return []
  
  const tagsSet = new Set()
  forms.value.forEach(form => {
    if (form.tags && form.tags.length) {
      form.tags.forEach(tag => tagsSet.add(tag))
    }
  })
  
  return Array.from(tagsSet).sort()
})

const tagOptions = computed(() => allTags.value.map(tag => ({ label: tag, value: tag })))

const baseForms = computed(() => {
  if (!forms.value) return []
  return forms.value.filter((form) => {
    if (selectedTags.value.length === 0) return true
    const selectedTagStrings = selectedTags.value
      .map(t => typeof t === 'string' ? t : t?.value)
      .filter(Boolean)
    return form.tags && form.tags.length
      ? selectedTagStrings.every(tag => form.tags.includes(tag))
      : false
  })
})

const { results: fuseResults } = useFuse(
  debouncedSearch,
  baseForms,
  {
    fuseOptions: {
      keys: ["title", "slug", "tags"],
      threshold: 0.3,
      ignoreLocation: true,
      includeScore: false,
    },
    matchAllWhenSearchEmpty: true,
  }
)

const enrichedForms = computed(() => {
  const base = baseForms.value
  if (!base || base.length === 0) return []
  const results = fuseResults.value
  return results && results.length > 0 ? results.map(r => r.item) : base
})
</script>
