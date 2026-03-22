<template>
  <div>
    <section class="bg-gradient-to-b relative from-white to-neutral-100 py-8 sm:py-16">
      <div class="absolute inset-0">
        <img
          class="w-full h-full object-cover object-top opacity-[0.10]"
          src="/img/pages/ai_form_builder/background-pattern.svg"
          alt="Page abstract background"
        >
      </div>
      
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative">
        <div class="max-w-4xl mx-auto text-center">
          <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-100 px-3 py-1.5 text-sm mb-6">
            <span class="rounded-full bg-white px-3 py-1 font-semibold text-blue-600 text-xs">
              NEW
            </span>
            <span class="font-normal text-gray-700">
              Built for Teams & Enterprises
            </span>
          </div>
          <h1 class="text-5xl font-semibold text-neutral-900 tracking-tight">
            Build beautiful forms,
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-blue-400">fast.</span>
          </h1>

          <p class="mt-4 sm:mt-6 leading-7 text-lg sm:leading-9 font-medium text-neutral-500">
            Create powerful forms in minutes with a clean, modern builder that works the way you do.
            Unlimited submissions, AI-powered creation, and full customization. All without complexity.
          </p>

          <div class="relative py-8">
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
              <UButton
                size="lg"
                :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Get started. It's FREE!"
              />
              <UButton
                v-if="useFeatureFlag('billing.enabled')"
                size="lg"
                variant="outline"
                :to="{ name: 'pricing' }"
                label="View Pricing"
              />
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm font-semibold text-neutral-600">
              <div class="flex items-center gap-2">
                <UIcon name="i-heroicons-building-office-2" class="h-5 w-5 text-blue-600" />
                <span>10k+ Companies</span>
              </div>

              <span class="hidden sm:block h-4 w-px bg-neutral-300/70" aria-hidden="true" />

              <div class="flex items-center gap-2">
                <UIcon name="i-simple-icons-github" class="h-4 w-4 text-neutral-800" />
                <NuxtLink to="https://github.com/OpnForm/OpnForm" target="_blank">3k+ GitHub Stars</NuxtLink>
              </div>

              <span class="hidden sm:block h-4 w-px bg-neutral-300/70" aria-hidden="true" />

              <NuxtLink
                to="https://www.trustpilot.com/review/opnform.com"
                target="_blank"
                class="flex items-center gap-2"
              >
                <div class="flex items-center gap-1">
                  <UIcon
                    name="i-heroicons-star-solid"
                    class="h-5 w-5 text-green-500"
                  />
                  <UIcon
                    name="i-heroicons-star-solid"
                    class="h-5 w-5 text-green-500"
                  />
                  <UIcon
                    name="i-heroicons-star-solid"
                    class="h-5 w-5 text-green-500"
                  />
                  <UIcon
                    name="i-heroicons-star-solid"
                    class="h-5 w-5 text-green-500"
                  />
                  <div class="relative h-5 w-5">
                    <UIcon
                      name="i-heroicons-star"
                      class="absolute inset-0 h-5 w-5 text-green-500"
                    />
                    <div class="absolute inset-y-0 left-0 w-1/2 overflow-hidden">
                      <UIcon
                        name="i-heroicons-star-solid"
                        class="h-5 w-5 text-green-500"
                      />
                    </div>
                  </div>
                </div>
              </NuxtLink>
            </div>
          </div>
        </div>

        <LiveDemo />
      </div>
      <div class="relative pt-12 pb-12 sm:pb-24 px-8 lg:px-12">
        <div class="absolute inset-0">
          <img
            class="w-full h-full object-cover object-top opacity-[0.20] relative z-1"
            src="/img/pages/ai_form_builder/background-pattern.svg"
            alt="Page abstract background"
          />
          <div
            class="w-full h-full bg-linear-to-b from-white from-35% via-blue-100 via-60% to-white to-85% absolute inset-0"
          ></div>
          <div
            class="min-h-3/5 absolute bottom-0 left-0 right-0 bg-linear-to-b from-transparent to-white z-2"
          ></div>
          <div
            class="w-[15%] min-h-full bg-linear-to-r from-[#fff8] to-transparent z-3 absolute top-0 left-0"
          ></div>
          <div
            class="w-[15%] min-h-full bg-linear-to-l from-[#fff8] to-transparent z-3 absolute top-0 right-0"
          ></div>
        </div>
        <LiveDemo />
      </div>
    </section>

    <TrustedTeams />

    <Features class="pb-8" />

    <MoreFeatures class="pb-8" />

    <AiFeature class="pb-8" />
    
    <OpenFormFooter />
  </div>
</template>

<script setup>
import LiveDemo from '~/components/pages/welcome/LiveDemo.vue'
import Features from "~/components/pages/welcome/Features.vue"
import MoreFeatures from "../components/pages/welcome/MoreFeatures.vue"
import AiFeature from "../components/pages/welcome/AiFeature.vue"
import { useIsAuthenticated } from '~/composables/useAuthFlow'

definePageMeta({
  layout: "default",
  middleware: ["root-redirect"],
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

</script>

<style lang="scss" scoped>
.customer-logo-container {
  max-width: 130px;
  width: 100%;
}

.ticks {
  color: #2563eb;
}

@screen md {
  #macbook-video {
    position: absolute;
    max-width: 84.8% !important;
    right: 0px;
    top: 6.8%;
  }
}
</style>
