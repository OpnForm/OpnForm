<template>
  <div class="min-h-full bg-white">
    <template v-if="feature">
      <FeatureDetailHero :feature="feature" />

      <section class="px-5 py-12 sm:px-8 sm:py-16 lg:px-12">
        <div class="mx-auto max-w-6xl">
          <article class="min-w-0 animate-fade-in-up animation-delay-200">
            <ContentRenderer
              :value="feature"
              class="feature-content"
            />
          </article>
        </div>
      </section>

      <section class="border-y border-neutral-200 bg-neutral-50 px-5 py-12 sm:px-8 sm:py-16 lg:px-12">
        <div class="mx-auto max-w-4xl text-center">
          <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">
            Get started
          </p>
          <h2 class="mt-3 text-3xl font-semibold tracking-[-1%] text-neutral-950 sm:text-4xl">
            Ready to use {{ feature.title }}?
          </h2>
          <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-neutral-600">
            Create your first form in minutes. Upgrade when you need advanced capabilities like this one.
          </p>
          <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
            <UButton
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              size="lg"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              label="Start for free"
              class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
            <UButton
              :to="{ name: 'pricing' }"
              size="lg"
              variant="outline"
              color="neutral"
              label="Compare plans"
              class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
          </div>
        </div>
      </section>

      <section
        v-if="relatedFeatures.length"
        class="px-5 py-12 sm:px-8 sm:py-16 lg:px-12"
      >
        <div class="mx-auto max-w-7xl">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">
                Keep exploring
              </p>
              <h2 class="mt-3 text-3xl font-semibold tracking-[-1%] text-neutral-950">
                More in {{ feature.category }}
              </h2>
            </div>
            <UButton
              :to="{ name: 'features' }"
              color="neutral"
              variant="outline"
              label="View all features"
            />
          </div>

          <div class="mt-8 grid gap-5 sm:grid-cols-3">
            <FeatureCard
              v-for="(relatedFeature, index) in relatedFeatures"
              :key="relatedFeature.slug"
              :feature="relatedFeature"
              class="animate-fade-in-up"
              :class="relatedAnimationDelay(index)"
            />
          </div>
        </div>
      </section>
    </template>

    <section
      v-else
      class="px-5 py-20 sm:px-8 lg:px-12"
    >
      <div class="mx-auto max-w-2xl rounded-[32px] border border-neutral-200 bg-neutral-50 p-8 text-center sm:p-12">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-neutral-950">
          <UIcon
            name="i-heroicons-magnifying-glass"
            class="h-7 w-7"
          />
        </div>
        <h1 class="mt-6 text-3xl font-semibold tracking-[-1%] text-neutral-950">
          Feature not found
        </h1>
        <p class="mt-4 text-base leading-7 text-neutral-600">
          This feature page may have moved, or the content has not been published yet.
        </p>
        <UButton
          :to="{ name: 'features' }"
          label="Browse all features"
          class="mt-7 rounded-xl"
        />
      </div>
    </section>

    <OpenFormFooter class="border-t" />
  </div>
</template>

<script setup>
import { filterPublishedFeatures, getRelatedFeatures, normalizeFeature, sortFeatures } from '~/lib/features.js'

defineRouteRules({
  swr: 3600,
})

const route = useRoute()
const slug = computed(() => String(route.params.slug ?? ''))
const { isAuthenticated: authenticated } = useIsAuthenticated()

const { data: feature } = await useAsyncData(`feature-${slug.value}`, () => {
  return queryCollection('features')
    .path(`/features/${slug.value}`)
    .first()
    .then((document) => {
      if (!document) return null
      const normalized = normalizeFeature(document)
      if (!normalized || normalized.published === false) return null
      return normalized
    })
})

const { data: allFeatures } = await useAsyncData('features-related-list', () => {
  return queryCollection('features').all().then((documents) => {
    return filterPublishedFeatures(documents).sort(sortFeatures)
  })
})

const relatedFeatures = computed(() => {
  if (!feature.value) return []

  return getRelatedFeatures(feature.value, allFeatures.value ?? [])
})

provide('featureTitle', computed(() => feature.value?.title ?? ''))

function relatedAnimationDelay (index) {
  return ['animation-delay-100', 'animation-delay-200', 'animation-delay-300'][index] ?? 'animation-delay-300'
}

useOpnSeoMeta({
  title: () => feature.value?.seoTitle ?? feature.value?.title ?? 'Features',
  description: () => feature.value?.seoDescription ?? feature.value?.summary ?? 'Explore OpnForm features for building powerful online forms.',
})
</script>
