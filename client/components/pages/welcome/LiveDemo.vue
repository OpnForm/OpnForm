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
            <button
              type="button"
              class="rounded-full border border-neutral-200 bg-white/80 px-1.5 py-0.5 text-[10px] font-semibold leading-3 text-neutral-400 transition hover:border-neutral-300 hover:text-neutral-600"
              :aria-label="`Switch live demo images to ${imageVariant === 'blobs' ? 'classic' : 'soft blobs'} version`"
              :title="`Live demo images: ${imageVariantLabel}`"
              @click="toggleImageVariant"
            >
              {{ imageVariantLabel }}
            </button>
          </div>
        </div>

        <div class="relative min-h-[460px] bg-white sm:min-h-[620px]">
          <LiveDemoForm
            :key="`${scenario.key}-${imageVariant}`"
            :scenario="scenario"
            :primary-cta-to="primaryCtaTo"
            :secondary-cta-to="secondaryCtaTo"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import LiveDemoForm from "~/components/pages/welcome/LiveDemoForm.vue"
import { useIsAuthenticated } from "~/composables/useAuthFlow"
import {
  DEFAULT_LIVE_DEMO_MEDIA_VARIANT,
  getLiveDemoMediaPreloads,
  getLiveDemoScenario,
} from "~/data/live-demo-scenarios.js"

useHead({
  link: getLiveDemoMediaPreloads().map((href) => ({
    rel: "preload",
    href,
    as: "image",
    type: "image/webp",
  })),
})

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
const imageVariant = ref(DEFAULT_LIVE_DEMO_MEDIA_VARIANT)
const imageVariantLabel = computed(() => imageVariant.value === "blobs" ? "v2" : "v1")

const scenario = computed(() =>
  getLiveDemoScenario({
    variant: props.variant,
    competitorName: props.competitorName,
    importSource: props.importSource,
    mediaVariant: imageVariant.value,
  }),
)

function toggleImageVariant() {
  imageVariant.value = imageVariant.value === "blobs" ? "classic" : "blobs"
}

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
