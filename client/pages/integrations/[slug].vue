<template>
  <div class="min-h-full bg-white">
    <template v-if="integration">
      <section class="relative overflow-hidden border-b border-neutral-200">
        <div class="pointer-events-none absolute inset-0 bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85%" />
        <div class="relative z-2 px-5 py-10 sm:px-8 sm:py-16 lg:px-12">
          <div class="mx-auto max-w-4xl">
            <UButton
              :to="{ name: 'integrations' }"
              variant="ghost"
              color="neutral"
              icon="i-heroicons-arrow-left"
              class="-ml-3 mb-10 animate-fade-in-up"
            >
              All integrations
            </UButton>

            <div class="text-center">
              <div class="relative mx-auto mb-8 inline-flex animate-feature-float">
                <div
                  class="relative flex h-20 w-20 items-center justify-center rounded-[28px] shadow-lg ring-4 sm:h-24 sm:w-24"
                  :class="[colorClasses.iconBg, colorClasses.iconText, colorClasses.ring]"
                >
                  <UIcon
                    :name="integration.icon"
                    class="h-10 w-10 sm:h-11 sm:w-11"
                  />
                </div>
              </div>

              <h1 class="mt-7 text-4xl font-semibold leading-tight tracking-[-1.2%] text-neutral-950 sm:text-5xl lg:text-[56px] lg:leading-[1.1]">
                {{ integration.title }}
              </h1>

              <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-neutral-600 sm:text-xl">
                {{ integration.summary }}
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
                  :to="{ name: 'integrations' }"
                  size="lg"
                  variant="outline"
                  color="neutral"
                  label="View all integrations"
                  class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="px-5 py-12 sm:px-8 sm:py-16 lg:px-12">
        <div class="mx-auto max-w-3xl">
          <article class="min-w-0 animate-fade-in-up animation-delay-200">
            <ContentRenderer
              :value="integration"
              class="feature-content"
            />
          </article>
        </div>
      </section>

      <section
        v-if="otherIntegrations.length"
        class="border-t border-neutral-200 px-5 py-12 sm:px-8 sm:py-16 lg:px-12"
      >
        <div class="mx-auto max-w-7xl">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">
                Keep exploring
              </p>
              <h2 class="mt-3 text-3xl font-semibold tracking-[-1%] text-neutral-950">
                More integrations
              </h2>
            </div>
            <UButton
              :to="{ name: 'integrations' }"
              color="neutral"
              variant="outline"
              label="View all integrations"
            />
          </div>

          <div class="mt-8 grid gap-5 sm:grid-cols-3">
            <IntegrationCard
              v-for="otherIntegration in otherIntegrations"
              :key="otherIntegration.slug"
              :integration="otherIntegration"
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
          Integration not found
        </h1>
        <p class="mt-4 text-base leading-7 text-neutral-600">
          This integration guide may have moved, or the content has not been published yet.
        </p>
        <UButton
          :to="{ name: 'integrations' }"
          label="Browse all integrations"
          class="mt-7 rounded-xl"
        />
      </div>
    </section>

    <OpenFormFooter class="border-t" />
  </div>
</template>

<script setup>
import { filterPublishedIntegrations, getIntegrationColorClasses, getOtherIntegrations, normalizeIntegration, sortIntegrations } from '~/lib/integrations.js'

defineRouteRules({
  swr: 3600,
})

const route = useRoute()
const slug = computed(() => String(route.params.slug ?? ''))
const { isAuthenticated: authenticated } = useIsAuthenticated()

const { data: integration } = await useAsyncData(`integration-${slug.value}`, () => {
  return queryCollection('integrations')
    .path(`/integrations/${slug.value}`)
    .first()
    .then((document) => {
      if (!document) return null
      const normalized = normalizeIntegration(document)
      if (!normalized || normalized.published === false) return null
      return normalized
    })
})

const { data: allIntegrations } = await useAsyncData('integrations-related-list', () => {
  return queryCollection('integrations').all().then((documents) => {
    return filterPublishedIntegrations(documents).sort(sortIntegrations)
  })
})

const colorClasses = computed(() => getIntegrationColorClasses(integration.value?.color))
const otherIntegrations = computed(() => getOtherIntegrations(integration.value, allIntegrations.value ?? []))

useOpnSeoMeta({
  title: () => integration.value?.seoTitle ?? integration.value?.title ?? 'Integrations',
  description: () => integration.value?.seoDescription ?? integration.value?.summary ?? 'Connect OpnForm with your existing tools and workflows.',
})

const integrationSchema = computed(() => {
  if (!integration.value) return null

  const description = integration.value.seoDescription
    ?? integration.value.summary
    ?? 'Connect OpnForm with your existing tools and workflows.'

  return buildSchemaGraph([
    buildWebPageSchema({
      name: integration.value.title,
      description,
      path: route.path,
    }),
    buildBreadcrumbSchema([
      { name: 'Home', path: '/' },
      { name: 'Integrations', path: '/integrations' },
      { name: integration.value.title, path: route.path },
    ]),
  ])
})

useJsonLd('integration-schema', integrationSchema)
</script>
