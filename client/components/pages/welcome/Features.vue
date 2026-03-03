<template>
  <section id="features" class="px-8 lg:px-12">
    <div
      class="space-y-8 sm:space-y-12 mx-auto max-w-full sm:max-w-xl md:max-w-336"
    >
      <div
        v-for="panel in panels"
        :key="panel.eyebrow"
        class="rounded-4xl border border-neutral-200/80 bg-white py-10 sm:py-14 md:py-24 px-10 sm:px-14 md:px-24 lg:px-35"
      >
        <div class="grid gap-12 lg:gap-16 lg:grid-cols-2 items-center">
          <div>
            <div
              :class="[
                'font-semibold text-sm tracking-[-0.6%]',
                panel.eyebrowClass,
              ]"
            >
              {{ panel.eyebrow }}
            </div>

            <h2
              class="my-4 text-3xl sm:text-[40px] font-semibold sm:leading-12 tracking-[-1%] text-neutral-900"
            >
              {{ panel.title }}
            </h2>

            <p
              class="text-base mt-4 leading-7 font-normal tracking-[-1.1%] text-neutral-500"
            >
              {{ panel.description }}
            </p>

            <div class="mt-12 space-y-4">
              <div
                v-for="item in panel.items"
                :key="item.title"
                class="flex items-start gap-4"
              >
                <div
                  :class="[
                    'h-6 w-6 rounded-[6px] flex items-center justify-center',
                    item.iconWrapClass,
                  ]"
                >
                  <UIcon
                    :name="item.icon"
                    :class="['h-3.5 w-3.5', item.iconClass]"
                  />
                </div>
                <div
                  class="text-base leading-7 font-medium tracking-[-1.1%] text-neutral-700"
                >
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
                <UIcon
                  name="i-heroicons-arrow-up-right-20-solid"
                  class="h-4 w-4"
                />
              </NuxtLink>
            </div>
          </div>

          <div class="flex justify-center lg:justify-end">
            <img
              :src="panel.imageSrc"
              :alt="panel.eyebrow"
              class="w-full h-auto rounded-2xl"
              loading="lazy"
            />
          </div>
        </div>
      </div>
    </div>

    <div class="py-14 md:py-28">
      <div class="max-w-3xl mx-auto text-center">
        <h2
          class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-neutral-900 tracking-[-1%]"
        >
          Everything you expect from a modern form builder.
        </h2>
        <p
          class="mx-auto max-w-lg mt-4 text-base leading-7 font-medium tracking-[-1.1%] text-neutral-500"
        >
          Automate your workflows with native integrations or connect anything
          with webhooks and our public API.
        </p>
      </div>

      <div
        class="mt-12 sm:mt-16 flex flex-wrap items-center justify-center gap-4"
      >
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="inline-flex items-center gap-2 rounded-[12px] border px-3.5 py-2 text-base leading-7 font-medium tracking-[-1.1%] transition"
          :class="
            activeTab === tab.key
              ? 'bg-blue-50 border-blue-200 text-blue-700'
              : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50'
          "
          @click="activeTab = tab.key"
        >
          <UIcon :name="tab.icon" class="h-5 w-5" />
          {{ tab.label }}
        </button>
      </div>

      <div
        class="mt-8 rounded-[24px] mx-auto max-w-266 border border-neutral-200/80 bg-white p-8"
      >
        <div class="grid gap-8 lg:grid-cols-2 items-center">
          <div>
            <div
              class="text-2xl leading-8 font-semibold tracking-[-0.5%] text-neutral-900"
            >
              {{ activeContent.title }}
            </div>
            <p
              class="mt-4 text-base leading-7 font-normal tracking-[-1.1%] text-neutral-500"
            >
              {{ activeContent.description }}
            </p>

            <div class="mt-6 space-y-4">
              <div
                v-for="point in activeContent.points"
                :key="point"
                class="flex items-start gap-4 text-base leading-7 font-medium tracking-[-1.1%] text-neutral-700"
              >
                <div
                  class="h-6 w-6 bg-primary-100 flex justify-center items-center rounded-[6px]"
                >
                  <UIcon
                    name="i-heroicons-check-20-solid"
                    class="h-3.5 w-3.5 text-blue-500"
                  />
                </div>
                <span>{{ point }}</span>
              </div>
            </div>
          </div>

          <div class="flex justify-center lg:justify-end">
            <img
              :src="activeContent.imageSrc"
              :alt="activeContent.title"
              class="w-full h-auto rounded-2xl"
              loading="lazy"
            />
          </div>
        </div>
      </div>

      <div class="mt-12 sm:mt-16 text-center">
        <div
          class="flex flex-col sm:flex-row items-center justify-center gap-6"
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
            :to="{ name: 'pricing' }"
            label="See the Full Feature List"
            variant="outline"
            color="neutral"
            size="lg"
            class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const { isAuthenticated: authenticated } = useIsAuthenticated();

const panels = [
  {
    eyebrow: "Modern Form Builder",
    eyebrowClass: "text-blue-600",
    title: "Design forms that look professional - without needing a designer.",
    description:
      "Drag and drop fields, apply themes, use multi-page layouts, and choose between conversational or classic form styles. Everything feels fast, smooth, and focused.",
    items: [
      {
        title: "Modern multi-step & single-page forms",
        icon: "i-heroicons-rectangle-stack",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
      {
        title: "Typeform-style or classic layouts",
        icon: "i-heroicons-view-columns",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
      {
        title: "Conditional logic",
        icon: "i-heroicons-arrows-right-left",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
      {
        title: "Custom themes, brand colors & fonts",
        icon: "i-heroicons-paint-brush",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
      {
        title: "Remove OpnForm branding on paid plans",
        icon: "i-heroicons-no-symbol",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
      {
        title: "AI assistance when you want it (never when you don't)",
        icon: "i-heroicons-sparkles",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
    ],
    imageSrc: "/img/pages/welcome/feature-1.png",
    link: null,
  },
  {
    eyebrow: "Unlimited Submissions",
    eyebrowClass: "text-emerald-600",
    title: "Collect as many responses as you need — even on the free plan.",
    description:
      "No per-response charges. No hidden quotas. No unexpected overages. OpnForm grows with your team.",
    items: [
      {
        title: "Unlimited submissions",
        icon: "i-ph-infinity-bold",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
      {
        title: "Generous free tier",
        icon: "i-heroicons-gift",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
      {
        title: "Fair, transparent pricing",
        icon: "i-heroicons-banknotes",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
    ],
    imageSrc: "/img/pages/welcome/feature-2.png",
    link: null,
  },
  {
    eyebrow: "Integrations & Automation",
    eyebrowClass: "text-violet-600",
    title: "Connect OpnForm to the tools you already use.",
    description:
      "Automate your workflows with native integrations or connect anything with webhooks and our public API.",
    items: [
      {
        title: "Slack, Discord, Telegram",
        icon: "i-heroicons-chat-bubble-left-right",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
      {
        title: "Google Sheets & Zapier",
        icon: "i-heroicons-table-cells",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
      {
        title: "Stripe payments",
        icon: "i-heroicons-credit-card",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
      {
        title: "Webhooks + REST API",
        icon: "i-heroicons-link",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
      {
        title: "Auto-notifications & routing",
        icon: "i-heroicons-arrow-path-rounded-square",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
    ],
    imageSrc: "/img/pages/welcome/feature-3.png",
    link: {
      to: { name: "pricing" },
      label: "Explore All Features",
      class: "text-violet-600 hover:text-violet-700 hover:no-underline",
    },
  },
];

const tabs = [
  { key: "smart", label: "Smart Forms", icon: "i-heroicons-sparkles" },
  { key: "inputs", label: "Rich Inputs", icon: "i-heroicons-bars-3-20-solid" },
  {
    key: "security",
    label: "Quality & Security",
    icon: "i-heroicons-shield-check",
  },
  {
    key: "control",
    label: "Experience & Control",
    icon: "i-heroicons-adjustments-horizontal",
  },
];

const activeTab = ref("smart");

const tabContent = {
  smart: {
    title: "Smart Forms",
    description:
      "Automate your workflows with native integrations or connect anything with webhooks and our public API.",
    points: [
      "Conditional logic",
      "Calculations & computed fields",
      "Answer piping & hidden fields",
      "Redirect on submit",
    ],
    imageSrc: "/img/pages/welcome/feature-4.png",
  },
  inputs: {
    title: "Rich Inputs",
    description:
      "Collect higher-quality data with powerful field types, validations, and advanced input experiences.",
    points: [
      "File uploads",
      "Address & phone inputs",
      "Payments & signatures",
      "Validation rules",
    ],
    imageSrc: "/img/pages/welcome/feature-5.png",
  },
  security: {
    title: "Quality & Security",
    description:
      "Keep your data safe and your pipeline clean with built-in protections and control over submissions.",
    points: [
      "Spam protection",
      "reCAPTCHA support",
      "Email notifications",
      "Data exports",
    ],
    imageSrc: "/img/pages/welcome/feature-6.png",
  },
  control: {
    title: "Experience & Control",
    description:
      "Fine-tune the end-to-end experience with themes, customization, and powerful routing options.",
    points: [
      "Custom themes & branding",
      "Multi-page forms",
      "Thank-you pages",
      "Webhooks & integrations",
    ],
    imageSrc: "/img/pages/welcome/feature-7.png",
  },
};

const activeContent = computed(
  () => tabContent[activeTab.value] || tabContent.smart,
);
</script>
