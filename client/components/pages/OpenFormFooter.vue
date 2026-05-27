<template>
  <section v-if="showCta" class="relative px-8 lg:px-12">
    <div class="max-w-336 mx-auto">
      <div
        class="relative rounded-4xl overflow-hidden bg-linear-to-br from-blue-600 via-blue-700 to-blue-800 pt-8 sm:pt-10 lg:pt-14 xl:pt-24 pl-8 md:pl-10 lg:pl-14 xl:pl-35"
      >
        <img
          src="/img/pages/welcome/powerForm-bg.png"
          class="absolute min-h-full inset-0 opacity-30"
        />
        <div class="grid lg:grid-cols-2 relative z-10">
          <div
            class="text-white relative z-20 pr-8 sm:pr-10 md:pr-0 pb-8 sm:pb-10 lg:pb-14 xl:pb-24"
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
              class="my-8 lg:my-12 flex flex-wrap items-center gap-6 text-base font-medium leading-7 tracking-[-1.1%] text-white/90"
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
            <div
              class="text-sm leading-5 tracking-[-0.6%] font-medium text-white/90"
            >
              Open-source, secure, and trusted by teams worldwide.
            </div>
            <div
              class="w-full xl:min-w-155 mt-6 md:mt-4 flex items-center justify-center sm:justify-start lg:justify-center gap-x-12 gap-y-5 sm:gap-12 flex-wrap"
            >
              <div class="w-fit filter-[sepia(1)_brightness(2)_saturate(0)]">
                <img
                  src="/img/pages/welcome/vanguard-1.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit filter-[sepia(1)_brightness(2)_saturate(0)]">
                <img
                  src="/img/pages/welcome/logo-berkeley-2.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit filter-[sepia(1)_brightness(2)_saturate(0)]">
                <img
                  src="/img/pages/welcome/Frame-47.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit filter-[sepia(1)_brightness(2)_saturate(0)]">
                <img
                  src="/img/pages/welcome/Zomato_Logo-2.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit filter-[sepia(1)_brightness(2)_saturate(0)]">
                <img
                  src="/img/pages/welcome/UN_emblem_blue-2.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
            </div>
          </div>

          <div class="flex items-end justify-end pl-8 sm:pl-10">
            <div
              class="relative w-full overflow-hidden flex justify-end items-end"
            >
              <img
                src="/img/pages/welcome/product-cover-half.png"
                alt="Product screenshot"
                class="w-full max-w-128.25 h-auto"
              />

              <!-- gradient fade
              <div
                class="w-full max-w-128.25 absolute z-2 bottom-0 right-0 h-1/3 backdrop-blur-xs [mask:linear-gradient(to_top,rgba(0,0,0,0.2),transparent)]"
              ></div> -->
            </div>
          </div>
        </div>
      </div>

      <div
        class="max-w-266 mx-auto pt-14 sm:pt-28 pb-12 sm:pb-16 border-b border-neutral-200"
      >
        <div class="grid md:grid-cols-2 gap-4">
          <div class="md:max-w-lg">
            <h3
              class="text-3xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
            >
              Build your first form today.
            </h3>
            <p
              class="mt-4 text-lg leading-7 tracking-[-1.5%] font-normal text-gray-600"
            >
              Start free with unlimited submissions. Upgrade when you need more
              control and customization.
            </p>
          </div>

          <div
            class="flex flex-col sm:flex-row gap-6 items-center justify-center md:justify-end md:max-h-12"
          >
            <UButton
              :to="{ name: 'pricing' }"
              label="View Pricing"
              variant="outline"
              color="neutral"
              class="w-fit sm:w-auto px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
            <UButton
              :to="{
                name: authenticated ? 'forms-create' : 'forms-create-guest',
              }"
              label="Get started. It's FREE!"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="w-fit sm:w-auto pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="bg-white">
    <div class="px-5 sm:px-8 lg:px-12 pt-10 sm:pt-14 lg:pt-16 pb-12 sm:pb-16 lg:pb-24">
      <div
        class="max-w-266 mx-auto grid gap-10 lg:grid-cols-[minmax(220px,0.8fr)_minmax(0,2.2fr)] lg:gap-14 xl:gap-20"
      >
        <div
          class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between lg:flex-col lg:justify-start lg:gap-0"
        >
          <div>
            <NuxtLink
              :to="{ name: user ? 'home' : 'index' }"
              class="inline-flex items-center gap-2 hover:no-underline"
            >
              <img src="/img/logo.svg" alt="OpnForm" class="h-10 w-10" />
              <span class="text-2xl font-semibold tracking-tight text-neutral-950"
                >OpnForm</span
              >
            </NuxtLink>

            <div class="mt-6 flex items-center gap-3 lg:mt-8">
              <a
                v-for="social in socialLinks"
                :key="social.label"
                :href="social.href"
                target="_blank"
                rel="noopener noreferrer"
                class="h-11 w-11 sm:h-10 sm:w-10 rounded-full flex items-center justify-center transition-colors border border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50"
                :aria-label="social.label"
              >
                <UIcon :name="social.icon" class="h-4 w-4 text-neutral-600" />
              </a>
            </div>
          </div>

          <div
            class="text-sm leading-5 tracking-[0.6%] font-medium text-neutral-600 sm:text-right lg:mt-8 lg:text-left"
          >
            {{ currYear }} OpnForm — All rights reserved
            <span v-if="version" class="block mt-1">
              Version {{ version }}
            </span>
          </div>
        </div>

        <nav aria-label="Footer navigation" class="lg:pt-1">
          <div class="grid grid-cols-2 gap-x-6 gap-y-9 sm:grid-cols-4 md:grid-cols-5 md:gap-x-8 lg:gap-x-10 xl:gap-x-12">
            <div
              v-for="group in linkGroups"
              :key="group.title"
              :class="group.wide ? 'min-[480px]:col-span-2 md:col-span-2' : ''"
            >
              <div
                class="text-sm leading-5 tracking-[0.6%] font-medium text-neutral-950"
              >
                {{ group.title }}
              </div>

              <div
                :class="[
                  'mt-3 gap-2.5 sm:gap-3',
                  group.wide
                    ? 'grid grid-cols-1 min-[480px]:grid-cols-2 min-[480px]:gap-x-6 md:gap-x-8'
                    : 'flex flex-col items-start',
                ]"
              >
                <template v-for="link in group.links" :key="link.label">
                  <NuxtLink
                    v-if="link.to"
                    :to="link.to"
                    class="flex min-h-7 w-fit items-center whitespace-nowrap text-sm leading-5 tracking-[-0.6%] font-medium transition-colors hover:no-underline text-neutral-600 hover:text-neutral-950"
                  >
                    {{ link.label }}
                  </NuxtLink>

                  <a
                    v-else
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex min-h-7 w-fit items-center whitespace-nowrap text-sm leading-5 tracking-[-0.6%] font-medium transition-colors hover:no-underline text-neutral-600 hover:text-neutral-950"
                  >
                    {{ link.label }}
                  </a>
                </template>
              </div>
            </div>
          </div>
        </nav>
      </div>
    </div>
  </footer>
</template>

<script setup>
import opnformConfig from "~/opnform.config.js"

defineProps({
  showCta: {
    type: Boolean,
    default: true,
  },
})

const { isAuthenticated: authenticated } = useIsAuthenticated()
const { data: user } = useAuth().user()
const currYear = ref(new Date().getFullYear())

// Use the reactive version for proper template reactivity
const version = computed(() => useFeatureFlag("version"))

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
])

const linkGroups = computed(() => [
  {
    title: "Product",
    links: [
      { label: "Pricing", to: { name: "pricing" } },
      { label: "Features", to: { name: "index", hash: "#features" } },
      { label: "Integrations", to: { name: "integrations" } },
      { label: "Enterprise", to: { name: "enterprise" } },
      { label: "Industry", to: { name: "industry" } },
      { label: "Templates", to: { name: "templates" } },
    ],
  },
  {
    title: "Comparisons",
    wide: true,
    links: [
      { label: "vs Google Forms", to: { name: "opnform-vs-googleforms" } },
      { label: "vs Typeform", to: { name: "opnform-vs-typeform" } },
      { label: "vs Fillout", to: { name: "opnform-vs-fillout" } },
      { label: "vs Jotform", to: { name: "opnform-vs-jotform" } },
      { label: "vs Tally", to: { name: "opnform-vs-tally" } },
      { label: "vs Youform", to: { name: "opnform-vs-youform" } },
      { label: "vs HeyForm", to: { name: "opnform-vs-heyform" } },
      { label: "vs Form.io", to: { name: "opnform-vs-formio" } },
      { label: "vs Formbricks", to: { name: "opnform-vs-formbricks" } },
      { label: "vs 123FormBuilder", to: { name: "opnform-vs-123formbuilder" } },
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
    title: "Updates",
    links: [
      { label: "Feedback", href: "https://feedback.opnform.com/" },
      { label: "Changelog", href: "https://feedback.opnform.com/changelog" },
      // { label: "Privacy Policy", to: { name: "privacy-policy" } },
      // { label: "Terms & Conditions", to: { name: "terms-conditions" } },
    ],
  },
])
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
