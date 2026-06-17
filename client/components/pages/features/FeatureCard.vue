<template>
  <NuxtLink
    :to="getFeatureLink(feature)"
    class="group relative flex h-full flex-col overflow-visible rounded-[24px] border border-neutral-200 bg-white p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50/30 hover:no-underline sm:p-5"
  >
    <div
      v-if="feature.featured"
      class="absolute -top-2 -left-3 z-10 -rotate-12 rounded-sm bg-blue-500 px-2 py-1 text-xs font-semibold text-white shadow-sm"
    >
      Featured
    </div>

    <div class="flex items-start gap-3">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
        :class="[colorClasses.iconBg, colorClasses.iconText]"
      >
        <UIcon
          :name="feature.icon"
          class="h-5 w-5"
        />
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <p class="truncate text-xs font-medium leading-5 text-blue-600">
            {{ feature.category }}
          </p>
          <FeaturePlanBadge :plan="feature.plan" />
        </div>
        <h3 class="mt-1 text-lg font-semibold leading-6 tracking-[-0.4%] text-neutral-950">
          {{ feature.title }}
        </h3>
      </div>
    </div>

    <p class="mt-3 line-clamp-2 flex-1 text-sm leading-6 text-neutral-600">
      {{ feature.summary }}
    </p>

    <div class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-950">
      Explore feature
      <UIcon
        name="i-heroicons-arrow-up-right-20-solid"
        class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
      />
    </div>
  </NuxtLink>
</template>

<script setup>
import { getFeatureColorClasses, getFeatureLink } from '~/lib/features.js'

const props = defineProps({
  feature: {
    type: Object,
    required: true,
  },
})

const colorClasses = computed(() => getFeatureColorClasses(props.feature.color))
</script>
