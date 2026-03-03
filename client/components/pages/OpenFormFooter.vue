<template>
  <section class="relative px-4 sm:px-8 lg:px-12">
    <div class="max-w-336 mx-auto">
      <div
        class="relative rounded-4xl overflow-hidden bg-linear-to-br from-blue-600 via-blue-700 to-blue-800 pt-10 sm:pt-14 md:pt-24 pl-10 sm:pl-20 md:pl-24 lg:pl-35"
      >
        <img
          src="/img/pages/welcome/powerForm-bg.png"
          class="absolute min-h-full inset-0 opacity-30"
        />
        <div class="grid lg:grid-cols-2 relative z-10">
          <div
            class="text-white relative z-20 pr-10 sm:pr-20 md:pr-0 pb-10 md:pb-24"
          >
            <h2
              class="text-white text-3xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] max-w-xl"
            >
              Powerful forms for everyone.
            </h2>
            <p
              class="mt-6 text-lg leading-7 tracking-[-1.5%] font-normal text-white/80 max-w-xl"
            >
              Start free with unlimited submissions. Upgrade when you need more
              control and customization.
            </p>

            <div class="mt-8">
              <UButton
                :to="{ name: 'pricing' }"
                label="View Pricing"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="rounded-xl pl-4 py-2.5 pr-3.5 text-base leading-7 tracking-[-1.1%] font-medium bg-white text-blue-700 hover:bg-white/95"
              />
            </div>

            <div
              class="my-12 flex flex-wrap items-center gap-6 text-base font-medium leading-7 tracking-[-1.1%] text-white/90"
            >
              <div class="flex items-center gap-2">
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="h-6 w-4 text-white/90"
                />
                <span>Free forever</span>
              </div>
              <div class="flex items-center gap-2">
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="h-6 w-4 text-white/90"
                />
                <span>No per-response fees</span>
              </div>
              <div class="flex items-center gap-2">
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="h-6 w-4 text-white/90"
                />
                <span>Fair pricing for growing teams</span>
              </div>
            </div>

            <div class="max-w-xl">
              <div
                class="text-sm leading-5 tracking-[-0.6%] font-medium text-white/90"
              >
                Open-source, secure, and trusted by teams worldwide.
              </div>
              <img
                src="/img/pages/welcome/trusted-teams-white.png"
                alt="Trusted Teams"
                class="m-auto mt-4"
              />
            </div>
          </div>

          <div class="flex items-end justify-end pl-8 sm:pl-10">
            <div
              class="relative w-full bg-white/95 border border-neutral-200 rounded-tl-3xl overflow-hidden"
            >
              <img
                src="/img/pages/welcome/product-cover-half.png"
                alt="Product screenshot"
                class="w-full h-auto"
              />
              <div
                class="absolute bottom-0 left-0 right-0 min-h-2/5 backdrop-blur-[2px] bg-linear-to-b from-transparent to-[#0002]"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div
        class="max-w-266 mx-auto pt-14 sm:pt-28 pb-12 sm:pb-16 border-b border-neutral-200"
      >
        <div class="grid md:grid-cols-2 gap-4">
          <div class="max-w-lg">
            <h3
              class="text-3xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-neutral-900"
            >
              Build your first form today.
            </h3>
            <p
              class="mt-4 text-lg leading-7 tracking-[-1.5%] font-normal text-neutral-600"
            >
              Start free with unlimited submissions. Upgrade when you need more
              control and customization.
            </p>
          </div>

          <div class="flex flex-col sm:flex-row gap-6 sm:justify-end max-h-12">
            <UButton
              :to="{ name: 'pricing' }"
              label="View Pricing"
              variant="outline"
              color="neutral"
              class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
            <UButton
              :to="{
                name: authenticated ? 'forms-create' : 'forms-create-guest',
              }"
              label="Get started. It's FREE!"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="bg-white">
    <div class="px-4 sm:px-8 lg:px-12 pt-12 sm:pt-16 pb-14 sm:pb-28">
      <div class="max-w-266 mx-auto grid gap-12 lg:gap-24 lg:grid-cols-12">
        <div class="lg:col-span-4">
          <NuxtLink
            :to="{ name: user ? 'home' : 'index' }"
            class="inline-flex items-center gap-2 hover:no-underline"
          >
            <img src="/img/logo.svg" alt="OpnForm" class="h-10 w-10" />
            <span class="text-2xl font-semibold tracking-tight text-neutral-900"
              >OpnForm</span
            >
          </NuxtLink>

          <div class="mt-8 flex items-center gap-3">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.href"
              target="_blank"
              rel="noopener noreferrer"
              class="h-8 w-8 rounded-full flex items-center justify-center transition-colors border border-neutral-100"
              :aria-label="social.label"
            >
              <UIcon :name="social.icon" class="h-4 w-4 text-neutral-700" />
            </a>
          </div>

          <div
            class="mt-8 text-sm leading-5 tracking-[0.6%] font-medium text-neutral-500"
          >
            {{ currYear }} OpnForm — All rights reserved
            <span v-if="version" class="block mt-1">
              Version {{ version }}
            </span>
          </div>
        </div>

        <div class="lg:col-span-8">
          <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="group in linkGroups" :key="group.title">
              <div
                class="text-sm leaing-5 tracking-[0.6%] font-medium text-neutral-900"
              >
                {{ group.title }}
              </div>

              <div class="mt-4 space-y-4">
                <template v-for="link in group.links" :key="link.label">
                  <NuxtLink
                    v-if="link.to"
                    :to="link.to"
                    class="block text-sm leading-5 tracking-[-0.6%] font-medium transition-colors hover:no-underline text-neutral-600 hover:text-neutral-900"
                  >
                    {{ link.label }}
                  </NuxtLink>

                  <a
                    v-else
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block text-sm leading-5 tracking-[-0.6%] font-medium transition-colors hover:no-underline text-neutral-600 hover:text-neutral-900"
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
import opnformConfig from "~/opnform.config.js";

const { isAuthenticated: authenticated } = useIsAuthenticated();
const { data: user } = useAuth().user();
const currYear = ref(new Date().getFullYear());

// Use the reactive version for proper template reactivity
const version = computed(() => useFeatureFlag("version"));

const socialLinks = computed(() => [
  { label: "X", href: opnformConfig.links.twitter, icon: "i-simple-icons-x" },
  {
    label: "Discord",
    href: opnformConfig.links.discord,
    icon: "i-simple-icons-discord",
  },
  {
    label: "GitHub",
    href: opnformConfig.links.github_url,
    icon: "i-simple-icons-github",
  },
]);

const linkGroups = computed(() => [
  {
    title: "Product",
    links: [
      { label: "Pricing", to: { name: "pricing" } },
      { label: "Features", to: { name: "index", hash: "#features" } },
      { label: "Integrations", to: { name: "integrations" } },
      { label: "Enterprise", to: { name: "enterprise" } },
      // { label: "Industry", to: { name: "industry" } },
    ],
  },
  {
    title: "Comparisons",
    links: [
      { label: "Typeform Alternative", to: { name: "opnform-vs-typeform" } },
      { label: "Jotform Alternative", to: { name: "index" } },
      { label: "Tally Alternative", to: { name: "index" } },
      // { label: "Formbricks Alternative", to: { name: "index" } },
    ],
  },
  {
    title: "Developers",
    links: [
      { label: "Open-source", href: opnformConfig.links.github_url },
      { label: "Self-hosting", href: opnformConfig.links.self_hosting },
      { label: "Documentation", href: opnformConfig.links.tech_docs },
      { label: "GitHub", href: opnformConfig.links.github_url },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "Blog", href: opnformConfig.links.changelog_url },
      { label: "Status", href: opnformConfig.links.changelog_url },
      // { label: "Feature Requests", href: opnformConfig.links.feature_requests },
      // { label: "Roadmap", href: opnformConfig.links.roadmap },
      // { label: "Privacy Policy", to: { name: "privacy-policy" } },
      // { label: "Terms & Conditions", to: { name: "terms-conditions" } },
    ],
  },
]);
</script>

<style scoped>
.footer-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.2;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.24) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.24) 1px, transparent 1px),
    linear-gradient(rgba(255, 255, 255, 0.11) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.11) 1px, transparent 1px);
  background-size: 72px 72px, 72px 72px, 12px 12px, 12px 12px;
  background-position: 0 0, 0 0, 0 0, 0 0;
  background-repeat: repeat;
}
</style>
