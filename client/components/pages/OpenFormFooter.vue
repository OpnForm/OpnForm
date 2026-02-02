<template>
  <footer :class="isDark ? 'bg-neutral-950 border-t border-white/10' : 'bg-white border-t border-neutral-200'">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto py-14 sm:py-20">
      <div class="grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-4">
          <NuxtLink :to="{ name: user ? 'home' : 'index' }" class="inline-flex items-center gap-3 hover:no-underline">
            <img src="/img/logo.svg" alt="OpnForm" class="h-11 w-11" />
            <span class="text-2xl font-semibold tracking-tight" :class="isDark ? 'text-white' : 'text-neutral-900'">OpnForm</span>
          </NuxtLink>

          <div class="mt-6 flex items-center gap-3">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.href"
              target="_blank"
              rel="noopener noreferrer"
              class="h-8 w-8 rounded-full flex items-center justify-center transition-colors"
              :class="isDark ? 'bg-white/10 hover:bg-white/15' : 'bg-neutral-100 hover:bg-neutral-200'"
              :aria-label="social.label"
            >
              <UIcon :name="social.icon" class="h-4 w-4" :class="isDark ? 'text-white/80' : 'text-neutral-700'" />
            </a>
          </div>

          <div class="mt-10 text-sm" :class="isDark ? 'text-white/45' : 'text-neutral-500'">
            {{ currYear }} OpnForm — All rights reserved
            <span v-if="version" class="block mt-1">
              Version {{ version }}
            </span>
          </div>
        </div>

        <div class="lg:col-span-8">
          <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="group in linkGroups" :key="group.title">
              <div class="text-sm font-semibold" :class="isDark ? 'text-white/90' : 'text-neutral-900'">
                {{ group.title }}
              </div>

              <div class="mt-5 space-y-3">
                <template v-for="link in group.links" :key="link.label">
                  <NuxtLink
                    v-if="link.to"
                    :to="link.to"
                    class="block text-sm font-medium transition-colors hover:no-underline"
                    :class="isDark ? 'text-white/55 hover:text-white' : 'text-neutral-600 hover:text-neutral-900'"
                  >
                    {{ link.label }}
                  </NuxtLink>

                  <a
                    v-else
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block text-sm font-medium transition-colors hover:no-underline"
                    :class="isDark ? 'text-white/55 hover:text-white' : 'text-neutral-600 hover:text-neutral-900'"
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

defineProps({
  isDark: {
    type: Boolean,
    default: false,
  },
})

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
