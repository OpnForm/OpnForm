<template>
  <section class="relative py-14 sm:py-20">
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
              <img src="/img/pages/welcome/trusted-teams.png" alt="Trusted Teams" class="m-auto mt-6">
            </div>
          </div>

          <div class="relative flex items-end justify-end pt-12 pl-8 sm:pt-16 sm:pl-10">
            <div class="w-[92%] sm:w-[90%] bg-white/95 border border-neutral-200 shadow-2xl rounded-tl-3xl overflow-hidden">
              <img
                src="/img/pages/welcome/product-cover-half.png"
                alt="Product screenshot"
                class="w-full h-auto"
              >
            </div>
          </div>
        </div>
      </div>

      <div class="mt-14 sm:mt-20">
        <div class="grid gap-8 lg:grid-cols-12 items-center">
          <div class="lg:col-span-7">
            <h3 class="text-4xl sm:text-5xl font-semibold text-neutral-900 tracking-tight">
              Build your first form today.
            </h3>
            <p class="mt-4 text-base sm:text-lg font-medium text-neutral-600 leading-8 max-w-xl">
              Start free with unlimited submissions. Upgrade when you need more control and customization.
            </p>
          </div>

          <div class="lg:col-span-5 flex flex-col sm:flex-row gap-3 sm:justify-end">
            <UButton
              :to="{ name: 'pricing' }"
              label="View Pricing"
              variant="outline"
              class="rounded-xl px-6 py-3 text-base font-semibold border-neutral-300 text-neutral-900 hover:bg-neutral-50"
            />
            <UButton
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              label="Get started. It's FREE!"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="rounded-xl px-6 py-3 text-base font-semibold"
            />
          </div>
        </div>
      </div>
    </div>
  </section>
    
  <footer class="bg-white border-t border-neutral-200">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto py-14 sm:py-20">
      <div class="grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-4">
          <NuxtLink :to="{ name: user ? 'home' : 'index' }" class="inline-flex items-center gap-3 hover:no-underline">
            <img src="/img/logo.svg" alt="OpnForm" class="h-11 w-11" />
            <span class="text-2xl font-semibold tracking-tight text-neutral-900">OpnForm</span>
          </NuxtLink>

          <div class="mt-6 flex items-center gap-3">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.href"
              target="_blank"
              rel="noopener noreferrer"
              class="h-8 w-8 rounded-full flex items-center justify-center transition-colors bg-neutral-100 hover:bg-neutral-200"
              :aria-label="social.label"
            >
              <UIcon :name="social.icon" class="h-4 w-4 text-neutral-700" />
            </a>
          </div>

          <div class="mt-10 text-sm text-neutral-500">
            {{ currYear }} OpnForm — All rights reserved
            <span v-if="version" class="block mt-1">
              Version {{ version }}
            </span>
          </div>
        </div>

        <div class="lg:col-span-8">
          <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="group in linkGroups" :key="group.title">
              <div class="text-sm font-semibold text-neutral-900">
                {{ group.title }}
              </div>

              <div class="mt-5 space-y-3">
                <template v-for="link in group.links" :key="link.label">
                  <NuxtLink
                    v-if="link.to"
                    :to="link.to"
                    class="block text-sm font-medium transition-colors hover:no-underline text-neutral-600 hover:text-neutral-900"
                  >
                    {{ link.label }}
                  </NuxtLink>

                  <a
                    v-else
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block text-sm font-medium transition-colors hover:no-underline text-neutral-600 hover:text-neutral-900"
                  >
                    {{ link.label }}
                  </a>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import opnformConfig from "~/opnform.config.js"

const { isAuthenticated: authenticated } = useIsAuthenticated()
const { data: user } = useAuth().user()
const currYear = ref(new Date().getFullYear())

// Use the reactive version for proper template reactivity
const version = computed(() => useFeatureFlag('version'))


const socialLinks = computed(() => [
  { label: 'X', href: opnformConfig.links.twitter, icon: 'i-simple-icons-x' },
  { label: 'Discord', href: opnformConfig.links.discord, icon: 'i-simple-icons-discord' },
  { label: 'GitHub', href: opnformConfig.links.github_url, icon: 'i-simple-icons-github' },
])

const linkGroups = computed(() => [
  {
    title: 'Product',
    links: [
      { label: 'Pricing', to: { name: 'pricing' } },
      { label: 'Features', to: { name: 'index', hash: '#features' } },
      { label: 'Integrations', to: { name: 'integrations' } },
      { label: 'Enterprise', to: { name: 'enterprise' } },
      { label: 'Industry', to: { name: 'industry' } },
    ],
  },
  {
    title: 'Comparisons',
    links: [
      { label: 'Typeform Alternative', to: { name: 'opnform-vs-typeform' } },
      { label: 'Jotform Alternative', href: opnformConfig.links.help_url },
      { label: 'Tally Alternative', href: opnformConfig.links.help_url },
    ],
  },
  {
    title: 'Developers',
    links: [
      { label: 'Open-source', href: opnformConfig.links.github_url },
      { label: 'Self-hosting', href: opnformConfig.links.self_hosting },
      { label: 'Documentation', href: opnformConfig.links.tech_docs },
      { label: 'GitHub', href: opnformConfig.links.github_url },
    ],
  },
  {
    title: 'Company',
    links: [
      { label: 'Blog', href: opnformConfig.links.changelog_url },
      { label: 'Feature Requests', href: opnformConfig.links.feature_requests },
      { label: 'Roadmap', href: opnformConfig.links.roadmap },
      { label: 'Privacy Policy', to: { name: 'privacy-policy' } },
      { label: 'Terms & Conditions', to: { name: 'terms-conditions' } },
    ],
  },
])
</script>
