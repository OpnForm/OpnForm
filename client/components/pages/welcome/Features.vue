<template>
  <section
    id="features"
    class="px-4 mx-auto sm:max-w-xl md:max-w-full lg:max-w-screen-xl md:px-24 lg:px-8 py-10 sm:py-14"
  >
    <div class="space-y-8 sm:space-y-10">
      <div
        v-for="panel in panels"
        :key="panel.eyebrow"
        class="rounded-[2.5rem] border border-neutral-200/80 bg-white p-8 sm:p-12"
      >
        <div class="grid gap-12 lg:grid-cols-2 items-center">
          <div>
            <div :class="['font-semibold text-sm tracking-wide', panel.eyebrowClass]">
              {{ panel.eyebrow }}
            </div>

            <h2 class="mt-3 text-3xl font-semibold leading-tight tracking-tight text-neutral-900">
              {{ panel.title }}
            </h2>

            <p class="mt-6 text-base leading-8 font-medium text-neutral-500 max-w-xl">
              {{ panel.description }}
            </p>

            <div class="mt-8 space-y-5">
              <div
                v-for="item in panel.items"
                :key="item.title"
                class="flex items-start gap-4"
              >
                <div :class="['h-8 w-8 rounded-xl flex items-center justify-center ring-1', item.iconWrapClass]">
                  <UIcon :name="item.icon" :class="['h-5 w-5', item.iconClass]" />
                </div>
                <div class="text-base font-semibold text-neutral-700">
                  {{ item.title }}
                </div>
              </div>
            </div>

            <div v-if="panel.link" class="mt-10">
              <NuxtLink
                :to="panel.link.to"
                class="inline-flex items-center gap-2 font-semibold"
                :class="panel.link.class"
              >
                {{ panel.link.label }}
                <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
              </NuxtLink>
            </div>
          </div>

          <div class="flex justify-center lg:justify-end">
            <div :class="['w-full max-w-xl rounded-3xl p-6 sm:p-10 border', panel.mediaWrapClass]">
              <img
                :src="panel.imageSrc"
                :alt="panel.imageAlt"
                class="w-full h-auto rounded-2xl"
                loading="lazy"
              >
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="pt-14">
      <div class="max-w-3xl mx-auto text-center">
        <h2 class="text-3xl sm:text-5xl font-semibold text-neutral-900 tracking-tight">
          Everything you expect from
          <br>
          a modern form builder.
        </h2>
        <p class="mt-4 text-base sm:text-lg font-medium text-neutral-500 leading-7">
          Automate your workflows with native integrations or connect anything with webhooks and our public API.
        </p>
      </div>

      <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition"
          :class="activeTab === tab.key
            ? 'bg-blue-50 border-blue-200 text-blue-700'
            : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
          @click="activeTab = tab.key"
        >
          <UIcon :name="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </button>
      </div>

      <div class="mt-10 rounded-[2.5rem] border border-neutral-200/80 bg-white p-8 sm:p-12">
        <div class="grid gap-12 lg:grid-cols-2 items-center">
          <div>
            <div class="text-2xl font-semibold text-neutral-900">
              {{ activeContent.title }}
            </div>
            <p class="mt-3 text-base sm:text-lg font-medium text-neutral-500 leading-7 max-w-xl">
              {{ activeContent.description }}
            </p>

            <div class="mt-8 space-y-4">
              <div
                v-for="point in activeContent.points"
                :key="point"
                class="flex items-start gap-3 text-base font-semibold text-neutral-700"
              >
                <UIcon name="i-heroicons-check-20-solid" class="h-5 w-5 text-blue-600 mt-0.5" />
                <span>{{ point }}</span>
              </div>
            </div>
          </div>

          <div class="flex justify-center lg:justify-end">
            <div class="w-full max-w-xl rounded-3xl border border-neutral-200 bg-neutral-50 p-6 sm:p-10">
              <img
                :src="activeContent.imageSrc"
                :alt="activeContent.imageAlt"
                class="w-full h-auto rounded-2xl"
                loading="lazy"
              >
            </div>
          </div>
        </div>
      </div>

      <div class="mt-10 text-center">
        <NuxtLink
          :to="{ name: 'pricing' }"
          class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-semibold hover:no-underline"
        >
          See the Full Feature List
          <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

<script setup>
const panels = [
  {
    eyebrow: 'Modern Form Builder',
    eyebrowClass: 'text-blue-600',
    title: 'Design forms that look professional - without needing a designer.',
    description:
      'Drag and drop fields, apply themes, use multi-page layouts, and choose between conversational or classic form styles. Everything feels fast, smooth, and focused.',
    items: [
      {
        title: 'Modern multi-step & single-page forms',
        icon: 'i-heroicons-rectangle-stack',
        iconWrapClass: 'bg-blue-50 ring-blue-100',
        iconClass: 'text-blue-600',
      },
      {
        title: 'Typeform-style or classic layouts',
        icon: 'i-heroicons-view-columns',
        iconWrapClass: 'bg-blue-50 ring-blue-100',
        iconClass: 'text-blue-600',
      },
      {
        title: 'Conditional logic',
        icon: 'i-heroicons-arrows-right-left',
        iconWrapClass: 'bg-blue-50 ring-blue-100',
        iconClass: 'text-blue-600',
      },
      {
        title: 'Custom themes, brand colors & fonts',
        icon: 'i-heroicons-paint-brush',
        iconWrapClass: 'bg-blue-50 ring-blue-100',
        iconClass: 'text-blue-600',
      },
      {
        title: 'Remove OpnForm branding on paid plans',
        icon: 'i-heroicons-no-symbol',
        iconWrapClass: 'bg-blue-50 ring-blue-100',
        iconClass: 'text-blue-600',
      },
      {
        title: "AI assistance when you want it (never when you don't)",
        icon: 'i-heroicons-sparkles',
        iconWrapClass: 'bg-blue-50 ring-blue-100',
        iconClass: 'text-blue-600',
      },
    ],
    mediaWrapClass: 'bg-blue-50/70 border-blue-100',
    imageSrc: '/img/pages/welcome/feature-1.png',
    imageAlt: 'Modern form builder preview',
    link: null,
  },
  {
    eyebrow: 'Unlimited Submissions',
    eyebrowClass: 'text-emerald-600',
    title: 'Collect as many responses as you need — even on the free plan.',
    description:
      'No per-response charges. No hidden quotas. No unexpected overages. OpnForm grows with your team.',
    items: [
      {
        title: 'Unlimited submissions',
        icon: 'i-heroicons-infinity',
        iconWrapClass: 'bg-emerald-50 ring-emerald-100',
        iconClass: 'text-emerald-600',
      },
      {
        title: 'Generous free tier',
        icon: 'i-heroicons-gift',
        iconWrapClass: 'bg-emerald-50 ring-emerald-100',
        iconClass: 'text-emerald-600',
      },
      {
        title: 'Fair, transparent pricing',
        icon: 'i-heroicons-banknotes',
        iconWrapClass: 'bg-emerald-50 ring-emerald-100',
        iconClass: 'text-emerald-600',
      },
    ],
    mediaWrapClass: 'bg-emerald-50/70 border-emerald-100',
    imageSrc: '/img/pages/welcome/feature-2.png',
    imageAlt: 'Unlimited submissions preview',
    link: null,
  },
  {
    eyebrow: 'Integrations & Automation',
    eyebrowClass: 'text-violet-600',
    title: 'Connect OpnForm to the tools you already use.',
    description:
      'Automate your workflows with native integrations or connect anything with webhooks and our public API.',
    items: [
      {
        title: 'Slack, Discord, Telegram',
        icon: 'i-heroicons-chat-bubble-left-right',
        iconWrapClass: 'bg-violet-50 ring-violet-100',
        iconClass: 'text-violet-600',
      },
      {
        title: 'Google Sheets & Zapier',
        icon: 'i-heroicons-table-cells',
        iconWrapClass: 'bg-violet-50 ring-violet-100',
        iconClass: 'text-violet-600',
      },
      {
        title: 'Stripe payments',
        icon: 'i-heroicons-credit-card',
        iconWrapClass: 'bg-violet-50 ring-violet-100',
        iconClass: 'text-violet-600',
      },
      {
        title: 'Webhooks + REST API',
        icon: 'i-heroicons-link',
        iconWrapClass: 'bg-violet-50 ring-violet-100',
        iconClass: 'text-violet-600',
      },
      {
        title: 'Auto-notifications & routing',
        icon: 'i-heroicons-arrow-path-rounded-square',
        iconWrapClass: 'bg-violet-50 ring-violet-100',
        iconClass: 'text-violet-600',
      },
    ],
    mediaWrapClass: 'bg-violet-50/70 border-violet-100',
    imageSrc: '/img/pages/welcome/feature-3.png',
    imageAlt: 'Integrations & automation preview',
    link: {
      to: { name: 'pricing' },
      label: 'Explore All Features',
      class: 'text-violet-600 hover:text-violet-700 hover:no-underline',
    },
  },
]


const tabs = [
  { key: 'smart', label: 'Smart Forms', icon: 'i-heroicons-sparkles' },
  { key: 'inputs', label: 'Rich Inputs', icon: 'i-heroicons-adjustments-horizontal' },
  { key: 'security', label: 'Quality & Security', icon: 'i-heroicons-shield-check' },
  { key: 'control', label: 'Experience & Control', icon: 'i-heroicons-sliders' },
]

const activeTab = ref('smart')

const tabContent = {
  smart: {
    title: 'Smart Forms',
    description:
      'Automate your workflows with native integrations or connect anything with webhooks and our public API.',
    points: [
      'Conditional logic',
      'Calculations & computed fields',
      'Answer piping & hidden fields',
      'Redirect on submit',
    ],
    imageSrc: '/img/pages/welcome/feature-4.png',
    imageAlt: 'Smart forms preview',
  },
  inputs: {
    title: 'Rich Inputs',
    description:
      'Collect higher-quality data with powerful field types, validations, and advanced input experiences.',
    points: [
      'File uploads',
      'Address & phone inputs',
      'Payments & signatures',
      'Validation rules',
    ],
    imageSrc: '/img/pages/welcome/feature-4.png',
    imageAlt: 'Rich inputs preview',
  },
  security: {
    title: 'Quality & Security',
    description:
      'Keep your data safe and your pipeline clean with built-in protections and control over submissions.',
    points: [
      'Spam protection',
      'reCAPTCHA support',
      'Email notifications',
      'Data exports',
    ],
    imageSrc: '/img/pages/welcome/feature-4.png',
    imageAlt: 'Quality and security preview',
  },
  control: {
    title: 'Experience & Control',
    description:
      'Fine-tune the end-to-end experience with themes, customization, and powerful routing options.',
    points: [
      'Custom themes & branding',
      'Multi-page forms',
      'Thank-you pages',
      'Webhooks & integrations',
    ],
    imageSrc: '/img/pages/welcome/feature-4.png',
    imageAlt: 'Experience and control preview',
  },
}

const activeContent = computed(() => tabContent[activeTab.value] || tabContent.smart)

</script>
