<template>
  <div>
    <section class="relative">
      <div class="relative z-2 px-8 py-14 sm:px-12 sm:py-16">
        <div class="mx-auto max-w-3xl text-center">
          <h1 class="text-4xl font-semibold tracking-[-1%] text-neutral-950 sm:text-[56px] sm:leading-16">
            Connect OpnForm to your workflow.
          </h1>
          <p class="mt-4 text-lg font-normal leading-7 tracking-[-1.5%] text-neutral-600 sm:text-xl sm:leading-8">
            Send responses instantly to your tools. Automate notifications, sync data, and connect your forms to the apps your team already uses.
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
      <div class="pointer-events-none absolute inset-0 h-full w-full bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85%" />
    </section>

    <section class="px-5 pb-12 sm:px-8 sm:pb-16 lg:px-12">
      <div class="mx-auto max-w-7xl">
        <div
          v-if="isLoading"
          class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
          <USkeleton
            v-for="index in 6"
            :key="index"
            class="h-60 rounded-[24px]"
          />
        </div>

        <div
          v-else-if="sortedIntegrations.length"
          class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
          <IntegrationCard
            v-for="integration in sortedIntegrations"
            :key="integration.slug"
            :integration="integration"
          />
        </div>

        <div
          v-else
          class="mt-12 rounded-[28px] border border-neutral-200 bg-neutral-50 p-8 text-center"
        >
          <h3 class="text-xl font-semibold text-neutral-950">
            No integrations found
          </h3>
          <p class="mt-2 text-neutral-600">
            Integration guides will appear here once they are published.
          </p>
        </div>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-neutral-50 px-5 py-12 sm:px-8 sm:py-16 lg:px-12">
      <div class="mx-auto max-w-4xl text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">
          Need help?
        </p>
        <h2 class="mt-3 text-3xl font-semibold tracking-[-1%] text-neutral-950 sm:text-4xl">
          Want help connecting your tools?
        </h2>
        <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-neutral-600">
          Visit our Help Center for detailed documentation, setup guidance, and troubleshooting tips.
        </p>
        <UButton
          label="Open Help Center"
          trailing-icon="i-heroicons-arrow-up-right-20-solid"
          class="mt-8"
          size="lg"
          @click="crisp.openHelpdesk()"
        />
      </div>
    </section>

    <OpenFormFooter class="border-t" />
  </div>
</template>

<script setup>
import { filterPublishedIntegrations, sortIntegrations } from '~/lib/integrations.js'

defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: 'Integrations',
  description: 'Connect OpnForm with notification, automation, payment, and database tools to send submissions into your existing workflows.',
})

const crisp = useCrisp()
const { isAuthenticated: authenticated } = useIsAuthenticated()

const { data: integrations, pending: isLoading } = await useAsyncData('integrations-list', () => {
  return queryCollection('integrations').all().then((documents) => {
    return filterPublishedIntegrations(documents).sort(sortIntegrations)
  })
})

const sortedIntegrations = computed(() => integrations.value ?? [])

const integrationsSchema = computed(() => buildSchemaGraph([
  buildCollectionPageSchema({
    name: 'OpnForm Integrations',
    description: 'Connect OpnForm with notification, automation, payment, and database tools to send submissions into your existing workflows.',
    path: '/integrations',
  }),
  buildBreadcrumbSchema([
    { name: 'Home', path: '/' },
    { name: 'Integrations', path: '/integrations' },
  ]),
  buildItemListSchema(
    sortedIntegrations.value.map((integration) => ({
      name: integration.title,
      path: `/integrations/${integration.slug}`,
    })),
    {
      path: '/integrations',
      name: 'OpnForm integrations',
    },
  ),
]))

useJsonLd('integrations-schema', integrationsSchema)
</script>
