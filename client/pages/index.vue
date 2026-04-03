<template>
  <div>
    <section>
      <div class="pt-8 sm:pt-16 px-8 lg:px-12 max-w-7xl mx-auto relative">
        <div class="max-w-3xl mx-auto text-center">
          <NuxtLink
            :to="{ name: 'enterprise' }"
            class="inline-flex items-center gap-2 rounded-[10px] border border-gray-100 bg-gray-100 px-2 py-0.5 text-sm transition-colors hover:bg-gray-200/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
          >
            <span
              class="rounded-[5px] bg-white px-1.5 py-0.5 font-semibold text-blue-600 text-[11px] leading-3 tracking-wide"
            >
              NEW
            </span>
            <span class="font-normal text-gray-600">
              Built for Teams & Enterprises
            </span>
          </NuxtLink>
          <h1
            class="text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950 my-4"
          >
            Build beautiful forms,
            <span class="text-blue-600">fast.</span>
          </h1>

          <p
            class="text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600"
          >
            Create powerful forms in minutes with a clean, modern builder that
            works the way you do. Unlimited submissions, AI-powered creation,
            and full customization. All without complexity.
          </p>

          <div class="relative mt-8">
            <div
              class="flex flex-col sm:flex-row items-center justify-center gap-3"
            >
              <UButton
                size="lg"
                :to="{
                  name: authenticated ? 'forms-create' : 'forms-create-guest',
                }"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Get started. It's FREE!"
                class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
              />
              <UButton
                v-if="useFeatureFlag('billing.enabled')"
                size="lg"
                variant="outline"
                :to="{ name: 'pricing' }"
                label="View Pricing"
                class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
              />
            </div>

            <div
              class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm font-semibold text-neutral-600"
            >
              <div class="flex items-center gap-2">
                <UIcon
                  name="i-heroicons-building-office-2"
                  class="h-5 w-5 text-blue-600"
                />
                <span
                  class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600"
                  >10k+ Companies</span
                >
              </div>

              <span
                class="hidden sm:block h-4 w-px bg-neutral-300/70"
                aria-hidden="true"
              ></span>

              <div class="flex items-center gap-2">
                <UIcon
                  name="i-simple-icons-github"
                  class="h-4 w-4 text-neutral-800"
                />
                <NuxtLink
                  to="https://github.com/OpnForm/OpnForm"
                  target="_blank"
                  class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600"
                  >3k+ GitHub Stars</NuxtLink
                >
              </div>

              <span
                class="hidden sm:block h-4 w-px bg-neutral-300/70"
                aria-hidden="true"
              ></span>

              <div class="flex items-center gap-2">
                <UIcon
                  name="i-heroicons-star-solid"
                  class="h-5 w-5 text-green-500"
                />
                <span
                  class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600"
                  >Trustpilot grade</span
                >
              </div>
            </div>
          </div>
        </div>
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

    <Features />

    <MoreFeatures />

    <!-- <AiFeature class="pb-8" /> -->

    <div class="pt-12 sm:pt-16"><OpenFormFooter /></div>
  </div>
</template>

<script setup>
import LiveDemo from "~/components/pages/welcome/LiveDemo.vue"
import Features from "~/components/pages/welcome/Features.vue"
import MoreFeatures from "../components/pages/welcome/MoreFeatures.vue"
import { useIsAuthenticated } from "~/composables/useAuthFlow"

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
