<template>
  <NuxtLink
    :to="getIntegrationLink(integration)"
    class="group relative flex h-full flex-col overflow-visible rounded-[24px] border border-neutral-200 bg-white p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50/30 hover:no-underline"
  >
    <div
      v-if="integration.featured"
      class="absolute -top-2 -left-3 z-10 -rotate-12 rounded-sm bg-blue-500 px-2 py-1 text-xs font-semibold text-white shadow-sm"
    >
      Most Popular
    </div>

    <div class="flex items-start gap-3">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
        :class="[colorClasses.iconBg, colorClasses.iconText]"
      >
        <UIcon
          :name="integration.icon"
          class="h-5 w-5"
        />
      </div>

      <div class="min-w-0 flex-1">
        <h3 class="text-lg font-semibold leading-6 tracking-[-0.4%] text-neutral-950">
          {{ integration.title }}
        </h3>
      </div>
    </div>

    <p class="mt-3 line-clamp-2 flex-1 text-sm leading-6 text-neutral-600">
      {{ integration.summary }}
    </p>

    <ul
      v-if="integration.highlights?.length"
      class="mt-4 space-y-2 text-sm leading-6 text-neutral-700"
    >
      <li
        v-for="highlight in integration.highlights.slice(0, 3)"
        :key="highlight"
        class="flex items-start gap-2"
      >
        <UIcon
          name="i-heroicons-check-20-solid"
          class="mt-0.5 h-5 w-5 shrink-0 text-blue-600"
        />
        <span>{{ highlight }}</span>
      </li>
    </ul>

    <div class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-950">
      Setup guide
      <UIcon
        name="i-heroicons-arrow-up-right-20-solid"
        class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
      />
    </div>
  </NuxtLink>
</template>

<script setup>
import { getIntegrationColorClasses, getIntegrationLink } from '~/lib/integrations.js'

const props = defineProps({
  integration: {
    type: Object,
    required: true,
  },
})

const colorClasses = computed(() => getIntegrationColorClasses(props.integration.color))
</script>
