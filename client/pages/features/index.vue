<template>
  <div>
    <section class="relative">
      <div class="relative z-2 px-8 py-14 sm:px-12 sm:py-16">
        <div class="mx-auto max-w-3xl text-center">
          <h1
            class="text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-[56px] sm:leading-16"
          >
            Everything you need to build, share, and automate beautiful forms.
          </h1>
          <p
            class="mt-4 text-lg font-normal leading-7 tracking-[-1.5%] text-gray-600 sm:text-xl sm:leading-8"
          >
            Explore the full OpnForm feature set, from powerful form building blocks to branding, delivery, and team workflows.
          </p>

          <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
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
        class="pointer-events-none absolute inset-0 h-full w-full bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85%"
      />
    </section>

    <section class="px-5 pb-12 sm:px-8 sm:pb-16 lg:px-12">
      <div class="mx-auto max-w-7xl">
        <div
          v-if="!isLoading && categorySections.length"
          class="my-10 flex flex-wrap items-center justify-center gap-1.5"
        >
          <UButton
            v-for="section in categorySections"
            :key="section.slug"
            variant="outline"
            color="neutral"
            :label="section.category"
            class="rounded-full px-3 py-1 text-xs font-medium"
            @click="scrollToCategory(section.slug)"
          />
        </div>

        <div
          v-if="isLoading"
          class="space-y-12"
        >
          <div
            v-for="index in 3"
            :key="index"
          >
            <USkeleton class="h-8 w-64 rounded-lg" />
            <USkeleton class="mt-3 h-5 w-full max-w-2xl rounded-lg" />
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              <USkeleton
                v-for="cardIndex in 3"
                :key="cardIndex"
                class="h-52 rounded-[24px]"
              />
            </div>
          </div>
        </div>

        <div
          v-else-if="categorySections.length"
          class="space-y-16 sm:space-y-20"
        >
          <section
            v-for="section in categorySections"
            :id="section.slug"
            :key="section.slug"
            class="scroll-mt-24"
          >
            <div class="max-w-3xl">
              <h2 class="text-3xl font-semibold tracking-[-1%] text-neutral-950 sm:text-4xl">
                {{ section.category }}
              </h2>
              <p class="mt-4 text-base leading-7 text-neutral-600">
                {{ section.description }}
              </p>
            </div>

            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              <FeatureCard
                v-for="feature in section.features"
                :key="feature.slug"
                :feature="feature"
              />
            </div>
          </section>
        </div>

        <div
          v-else
          class="mt-12 rounded-[28px] border border-neutral-200 bg-neutral-50 p-8 text-center"
        >
          <h3 class="text-xl font-semibold text-neutral-950">
            No features found
          </h3>
          <p class="mt-2 text-neutral-600">
            Feature pages will appear here once they are published.
          </p>
        </div>
      </div>
    </section>

    <OpenFormFooter class="border-t" />
  </div>
</template>

<script setup>
import { filterPublishedFeatures, groupFeaturesByCategory, sortFeatures } from '~/lib/features.js'

defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: 'Form Builder Features',
  description: 'Explore all OpnForm features for building, sharing, automating, and managing beautiful online forms.',
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const { data: features, pending: isLoading } = await useAsyncData('features-list', () => {
  return queryCollection('features').all().then((documents) => {
    return filterPublishedFeatures(documents).sort(sortFeatures)
  })
})

const sortedFeatures = computed(() => features.value ?? [])
const categorySections = computed(() => groupFeaturesByCategory(sortedFeatures.value))

function scrollToCategory (categorySlug) {
  document.getElementById(categorySlug)?.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  })
}
</script>
