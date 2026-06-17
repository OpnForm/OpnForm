<template>
  <div>
    <section class="relative">
      <div class="py-14 sm:py-16 px-8 sm:px-12 relative z-2">
        <div class="max-w-3xl mx-auto text-center">
          <h1
            class="text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950"
          >
            Everything you need to build, share, and automate beautiful forms.
          </h1>
          <p
            class="mt-4 text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600"
          >
            Explore the full OpnForm feature set, from powerful form building blocks to branding, delivery, and team workflows.
          </p>

          <div
            class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <UButton
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              size="lg"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              label="Create a form"
              class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
            <UButton
              :to="{ name: 'pricing' }"
              size="lg"
              variant="outline"
              color="neutral"
              label="View pricing"
              class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
          </div>
        </div>
      </div>
      <div
        class="pointer-events-none w-full h-full bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85% absolute inset-0"
      ></div>
    </section>     

    <section class="px-5 py-12 sm:px-8 sm:py-16 lg:px-12">
      <div class="mx-auto max-w-7xl">
        <div class="flex flex-col gap-6">
          <div class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">
              Feature directory
            </p>
            <h2 class="mt-3 text-3xl font-semibold tracking-[-1%] text-neutral-950 sm:text-4xl">
              Find the right capability for your workflow.
            </h2>
            <p class="mt-4 text-base leading-7 text-neutral-600">
              Browse by category to narrow the library, or explore everything at once.
            </p>
          </div>

          <div class="flex w-full flex-wrap gap-1.5">
            <UButton
              v-for="category in categories"
              :key="category"
              :variant="selectedCategory === category ? 'solid' : 'outline'"
              color="neutral"
              :label="category"
              class="rounded-full px-3 py-1 text-xs font-medium"
              :class="selectedCategory === category ? 'bg-neutral-950 text-white ring-neutral-950' : ''"
              @click="selectedCategory = category"
            />
          </div>
        </div>

        <div
          v-if="isLoading"
          class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
          <USkeleton
            v-for="index in 6"
            :key="index"
            class="h-52 rounded-[24px]"
          />
        </div>

        <div
          v-else-if="filteredFeatures.length"
          class="mt-12 grid gap-5 sm:grid-cols-3"
        >
          <FeatureCard
            v-for="feature in filteredFeatures"
            :key="feature.slug"
            :feature="feature"
          />
        </div>

        <div
          v-else
          class="mt-12 rounded-[28px] border border-neutral-200 bg-neutral-50 p-8 text-center"
        >
          <h3 class="text-xl font-semibold text-neutral-950">
            No features found
          </h3>
          <p class="mt-2 text-neutral-600">
            Try another category to explore the feature library.
          </p>
        </div>
      </div>
    </section>

    <OpenFormFooter class="border-t" />
  </div>
</template>

<script setup>
import { filterPublishedFeatures, sortFeatures } from '~/lib/features.js'

defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: 'Form Builder Features',
  description: 'Explore all OpnForm features for building, sharing, automating, and managing beautiful online forms.',
})

const { isAuthenticated: authenticated } = useIsAuthenticated()
const selectedCategory = ref('All')

const { data: features, pending: isLoading } = await useAsyncData('features-list', () => {
  return queryCollection('features').all().then((documents) => {
    return filterPublishedFeatures(documents).sort(sortFeatures)
  })
})

const sortedFeatures = computed(() => features.value ?? [])
const categories = computed(() => {
  const featureCategories = sortedFeatures.value.map((feature) => feature.category)
  return ['All', ...new Set(featureCategories)]
})
const filteredFeatures = computed(() => {
  if (selectedCategory.value === 'All') return sortedFeatures.value
  return sortedFeatures.value.filter((feature) => feature.category === selectedCategory.value)
})
</script>
