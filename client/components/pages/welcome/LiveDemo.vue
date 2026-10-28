<template>
  <div class="relative z-10 mx-auto flex w-full max-w-266 items-center justify-center">
    <div
      class="relative w-full overflow-hidden rounded-[20px] border border-neutral-200 bg-[#D5E2FF] p-1.5 shadow-[0_28px_90px_-44px_rgba(15,23,42,0.45)] sm:rounded-[28px] sm:p-2.5"
    >
      <div class="overflow-hidden rounded-[16px] bg-white sm:rounded-[20px]">
        <div
          class="relative flex select-none items-center justify-center border-b border-neutral-100 px-3 py-1 sm:px-5 sm:py-1.5"
        >
          <div class="pointer-events-none hidden items-center gap-2.5 text-neutral-300 sm:absolute sm:left-5 sm:flex">
            <UIcon
              name="i-heroicons-view-columns-20-solid"
              class="h-4 w-4"
            />
            <UIcon
              name="i-heroicons-arrow-left-20-solid"
              class="h-4 w-4"
            />
            <UIcon
              name="i-heroicons-arrow-right-20-solid"
              class="h-4 w-4"
            />
            <UIcon
              name="i-heroicons-arrow-path-20-solid"
              class="h-4 w-4"
            />
          </div>

          <div
            class="flex min-w-0 items-center gap-2 rounded-full bg-neutral-50 px-3 py-0.5 text-xs font-medium leading-4 text-neutral-600"
          >
            <UIcon
              name="i-heroicons-link-20-solid"
              class="h-4 w-4 shrink-0 text-neutral-300"
            />
            <span class="truncate">opnform.com</span>
          </div>

          <div class="hidden items-center gap-2 text-neutral-300 sm:absolute sm:right-5 sm:flex">
            <UIcon
              name="i-heroicons-adjustments-horizontal-20-solid"
              class="pointer-events-none h-4 w-4"
            />
            <UIcon
              name="i-heroicons-sparkles-20-solid"
              class="pointer-events-none h-4 w-4"
            />
          </div>
        </div>

        <div ref="demoContainer" class="relative min-h-[460px] bg-white sm:min-h-[620px]">
          <LazyLiveDemoForm
            v-if="hasBeenVisible"
            :key="scenario.key"
            :scenario="scenario"
            :primary-cta-to="primaryCtaTo"
            :secondary-cta-to="secondaryCtaTo"
          />
          <div v-else class="min-h-[460px] sm:min-h-[620px] flex flex-col justify-center gap-5 p-8 sm:p-12" aria-busy="true" aria-label="Loading live demo">
            <div class="h-6 w-32 rounded bg-neutral-100" aria-hidden="true" />
            <div class="h-12 w-3/4 rounded bg-neutral-100" aria-hidden="true" />
            <div class="h-4 w-full rounded bg-neutral-50" aria-hidden="true" />
            <div class="h-4 w-2/3 rounded bg-neutral-50" aria-hidden="true" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useIsAuthenticated } from "~/composables/useIsAuthenticated.js"
import {
  getLiveDemoScenario,
} from "~/data/live-demo-scenarios.js"

const demoContainer = ref(null)
const hasBeenVisible = ref(false)
let observer
onMounted(() => {
  if (!window.IntersectionObserver) {
    hasBeenVisible.value = true
    return
  }
  observer = new IntersectionObserver((entries) => {
    if (!entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= 0.35)) return
    hasBeenVisible.value = true
    observer.disconnect()
  }, { threshold: 0.35 })
  observer.observe(demoContainer.value)
})
onBeforeUnmount(() => observer?.disconnect())

const props = defineProps({
  variant: {
    type: String,
    default: "home",
  },
  competitorName: {
    type: String,
    default: null,
  },
  importSource: {
    type: String,
    default: null,
  },
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const scenario = computed(() =>
  getLiveDemoScenario({
    variant: props.variant,
    competitorName: props.competitorName,
    importSource: props.importSource,
  }),
)

const primaryCtaTo = computed(() => ({
  name: authenticated.value ? "forms-create" : "forms-create-guest",
}))

const secondaryCtaTo = computed(() => {
  if (!props.importSource || !scenario.value.secondaryCtaLabel) {
    return null
  }

  return {
    name: authenticated.value || props.importSource === "google_forms"
      ? "forms-create"
      : "forms-create-guest",
    query: { import: props.importSource },
  }
})
</script>
