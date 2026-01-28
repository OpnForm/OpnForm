<template>
  <div>
    <section
      class="bg-gradient-to-b relative from-white to-neutral-100 py-8 sm:py-16"
    >
      <div class="absolute inset-0">
        <img
          class="w-full h-full object-cover object-top"
          src="/img/pages/ai_form_builder/background-pattern.svg"
          alt="Page abstract background"
        >
      </div>
      
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-semibold text-neutral-900 tracking-tight">
            Build beautiful forms,
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-blue-400">fast.</span>
          </h1>

          <p class="mt-4 sm:mt-6 text-base leading-7 sm:text-xl sm:leading-9 font-medium text-neutral-500">
            Create powerful forms in minutes with a clean, modern builder that works the way you do.
            Unlimited submissions, AI-powered creation, and full customization. All without complexity.
          </p>

          <div class="relative py-8">
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
              <UButton
                v-if="!authenticated"
                class="rounded-full px-7 py-3 text-base font-semibold"
                :to="{ name: 'forms-create-guest' }"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Get started. It's FREE!"
              />
              <UButton
                v-else
                class="rounded-full px-7 py-3 text-base font-semibold"
                :to="{ name: 'forms-create' }"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Get started. It's FREE!"
              />
              <UButton
                v-if="useFeatureFlag('billing.enabled')"
                class="rounded-full px-7 py-3 text-base font-semibold"
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

              <div class="flex items-center gap-2">
                <UIcon name="i-heroicons-star-solid" class="h-5 w-5 text-green-500" />
                <span>Trustpilot grade</span>
              </div>
            </div>
          </div>
        </div>

        <div
          class="w-full mt-12 relative px-6 mx-auto max-w-4xl sm:px-10 lg:px-0 z-10 flex items-center justify-center"
        >
          <div
            class="-m-2 rounded-xl bg-blue-900/5 p-2 backdrop-blur-xs ring ring-inset ring-blue-900/10 lg:-m-4 lg:rounded-2xl lg:p-4 w-full"
          >
            <img
              src="/img/pages/welcome/product-cover.jpg"
              sizes="320px sm:650px lg:896px"
              alt="Product screenshot"
              loading="lazy"
              class="rounded-sm w-full shadow-2xl ring ring-neutral-900/10"
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
              <!-- <UButton
                v-if="useFeatureFlag('billing.enabled')"
                size="lg"
                variant="outline"
                :to="{ name: 'pricing' }"
                label="View Pricing"
              /> -->
              <UButton
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

    <section class="relative py-14 sm:py-20">
      <div class="pointer-events-none absolute inset-x-0 bottom-0 top-60 sm:top-80 bg-neutral-950" />

      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative z-10">
        <div class="rounded-[2.5rem] overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 shadow-2xl">
          <div class="grid lg:grid-cols-2">
            <div class="px-8 py-12 sm:px-12 sm:py-16 text-white">
              <h2 class="text-white text-4xl font-semibold tracking-tight leading-tight">
                Powerful forms for
                <br>
                everyone.
              </h2>
              <p class="mt-6 text-base sm:text-lg font-medium text-white/80 leading-8 max-w-xl">
                Start free with unlimited submissions. Upgrade when you need more control and customization.
              </p>

              <div class="mt-10">
                <UButton
                  :to="{ name: 'pricing' }"
                  label="View Pricing"
                  trailing-icon="i-heroicons-arrow-up-right-20-solid"
                  class="rounded-xl px-6 py-3 text-base font-semibold bg-white text-blue-700 hover:bg-white/95"
                />
              </div>

              <div class="mt-10 flex flex-wrap items-center gap-x-10 gap-y-4 text-sm font-semibold text-white/90">
                <div class="flex items-center gap-2">
                  <UIcon name="i-heroicons-check-20-solid" class="h-5 w-5 text-white/90" />
                  <span>Free forever</span>
                </div>
                <div class="flex items-center gap-2">
                  <UIcon name="i-heroicons-check-20-solid" class="h-5 w-5 text-white/90" />
                  <span>No per-response fees</span>
                </div>
                <div class="flex items-center gap-2">
                  <UIcon name="i-heroicons-check-20-solid" class="h-5 w-5 text-white/90" />
                  <span>Fair pricing for growing teams</span>
                </div>
              </div>

              <div class="mt-12">
                <div class="text-sm font-semibold text-white/80">
                  Open-source, secure, and trusted by teams worldwide.
                </div>
                <img src="/img/pages/welcome/trusted-teams.png" alt="Trusted Teams" class="w-full h-auto mt-4">
              </div>
            </div>

            <div class="relative bg-white/95">
              <div class="p-6 sm:p-10">
                <div class="rounded-3xl border border-neutral-200 bg-white overflow-hidden shadow-xl">
                  <div class="aspect-[5/4] w-full flex items-center justify-center text-neutral-400 font-semibold">
                    Image goes here
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-14 sm:mt-20">
          <div class="grid gap-8 lg:grid-cols-12 items-center">
            <div class="lg:col-span-7">
              <h3 class="text-4xl sm:text-5xl font-semibold text-white tracking-tight">
                Build your first form today.
              </h3>
              <p class="mt-4 text-base sm:text-lg font-medium text-neutral-300 leading-8 max-w-xl">
                Start free with unlimited submissions. Upgrade when you need more control and customization.
              </p>
            </div>

            <div class="lg:col-span-5 flex flex-col sm:flex-row gap-3 sm:justify-end">
              <UButton
                :to="{ name: 'pricing' }"
                label="View Pricing"
                variant="outline"
                class="rounded-xl px-6 py-3 text-base font-semibold border-white/20 text-white hover:bg-white/5"
              />

              <UButton
                v-if="!authenticated"
                :to="{ name: 'forms-create-guest' }"
                label="Get started. It's FREE!"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="rounded-xl px-6 py-3 text-base font-semibold"
              />
              <UButton
                v-else
                :to="{ name: 'forms-create' }"
                label="Get started. It's FREE!"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="rounded-xl px-6 py-3 text-base font-semibold"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="flex flex-col bg-neutral-50 dark:bg-notion-dark">
      <pricing-table
        v-if="useFeatureFlag('billing.enabled')"
        class="pb-20"
        :home-page="true"
      >
        <template #pricing-table>
          <li class="flex gap-x-3">
            <NuxtLink
              :to="{ name: 'pricing' }"
              class="flex gap-3"
            >
              <div class="w-5" />
              Read more about our pricing
            </NuxtLink>
          </li>
        </template>
      </pricing-table>

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
