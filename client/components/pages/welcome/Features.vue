<template>
  <section id="features" class="px-8 lg:px-12">
    <div class="space-y-8 sm:space-y-12 mx-auto w-full max-w-266 lg:hidden">
      <div
        v-for="panel in panels"
        :key="panel.eyebrow"
        class="rounded-4xl border border-gray-200/80 bg-white py-8 sm:py-10 lg:py-14 xl:py-24 px-8 md:px-10 lg:px-14 xl:px-35"
      >
        <div class="grid gap-8 lg:gap-16 lg:grid-cols-2 items-start">
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
              class="my-4 text-3xl sm:text-[40px] font-semibold sm:leading-12 tracking-[-1%] text-gray-950"
            >
              {{ panel.title }}
            </h2>

            <p
              class="text-base mt-4 leading-7 font-normal tracking-[-1.1%] text-gray-600"
            >
              {{ panel.description }}
            </p>

            <div class="mt-8 sm:mt-12 space-y-4">
              <div
                v-for="item in panel.items"
                :key="item.title"
                class="flex items-center gap-4"
              >
                <div
                  :class="[
                    'h-6 w-6 rounded-[6px] flex items-center justify-center',
                    item.iconWrapClass,
                  ]"
                >
                  <UIcon
                    :name="item.icon"
                    :class="['h-4 w-4', item.iconClass]"
                  />
                </div>
                <div
                  class="text-base leading-7 font-medium tracking-[-1.1%] text-gray-600"
                >
                  {{ item.title }}
                </div>
              </div>
            </div>

            <div v-if="panel.link" class="mt-8 sm:mt-10">
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
              class="w-full max-w-100 lg:max-w-120 mx-auto h-auto rounded-2xl"
              loading="lazy"
            />
          </div>
        </div>
      </div>
    </div>

    <div
      class="hidden lg:grid mx-auto w-full max-w-266 grid-cols-[minmax(0,1fr)_32rem] gap-14 xl:gap-20"
    >
      <div class="space-y-18 xl:space-y-24 py-10 xl:py-16">
        <article
          v-for="(panel, index) in panels"
          :key="panel.eyebrow"
          :ref="(element) => setDesktopPanelRef(element, index)"
          class="min-h-[66vh] xl:min-h-[70vh] flex items-center"
        >
          <div
            class="max-w-2xl py-12 transition-opacity duration-300"
            :class="activeDesktopPanel === index ? 'opacity-100' : 'opacity-45'"
          >
            <div
              :class="[
                'font-semibold text-sm tracking-[-0.6%]',
                panel.eyebrowClass,
              ]"
            >
              {{ panel.eyebrow }}
            </div>

            <h2
              class="my-4 text-3xl xl:text-[44px] font-semibold leading-tight tracking-[-1%] text-gray-950"
            >
              {{ panel.title }}
            </h2>

            <p
              class="text-base xl:text-lg mt-4 leading-7 xl:leading-8 font-normal tracking-[-1.1%] text-gray-600"
            >
              {{ panel.description }}
            </p>

            <div class="mt-8 xl:mt-10 space-y-4">
              <div
                v-for="item in panel.items"
                :key="item.title"
                class="flex items-center gap-4"
              >
                <div
                  :class="[
                    'h-6 w-6 rounded-[6px] flex items-center justify-center',
                    item.iconWrapClass,
                  ]"
                >
                  <UIcon
                    :name="item.icon"
                    :class="['h-4 w-4', item.iconClass]"
                  />
                </div>
                <div
                  class="text-base leading-7 font-medium tracking-[-1.1%] text-gray-600"
                >
                  {{ item.title }}
                </div>
              </div>
            </div>

            <div v-if="panel.link" class="mt-8 xl:mt-10">
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
        </article>
      </div>

      <div class="relative py-10 xl:py-16">
        <div class="sticky top-18 xl:top-24 flex justify-center">
          <Transition name="feature-panel-image" mode="out-in">
            <img
              :key="activeDesktopImage.eyebrow"
              :src="activeDesktopImage.imageSrc"
              :alt="activeDesktopImage.eyebrow"
              class="w-full max-w-120 h-auto rounded-[28px]"
              loading="lazy"
            />
          </Transition>
        </div>
      </div>
    </div>

    <div class="py-14 md:py-28">
      <div class="max-w-3xl mx-auto text-center">
        <h2
          class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-gray-950 tracking-[-1%]"
        >
          Everything you expect from a modern form builder.
        </h2>
        <p
          class="mx-auto max-w-lg mt-4 text-base leading-7 font-medium tracking-[-1.1%] text-gray-600"
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
          class="inline-flex items-center gap-2 rounded-[14px] border px-3.5 py-2 text-base leading-7 font-medium tracking-[-1.1%] transition-all duration-300"
          :class="
            activeTab === tab.key
              ? `${activeTabTheme.tabActiveClass} -translate-y-0.5 shadow-lg shadow-neutral-200/70`
              : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300'
          "
          @click="activeTab = tab.key"
        >
          <UIcon :name="tab.icon" class="h-5 w-5" />
          {{ tab.label }}
        </button>
      </div>

      <div
        class="mt-8 mx-auto max-w-266 overflow-hidden rounded-[28px] border border-neutral-200/80 bg-white p-1.5 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.22)]"
      >
        <div
          class="relative grid items-center gap-8 rounded-[24px] p-5 md:p-6 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)] lg:gap-10"
          :class="activeTabTheme.panelClass"
        >
          <div
            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-white/80 to-transparent"
          ></div>
          <div class="relative">
            <div
              class="inline-flex items-center gap-2 rounded-full border border-white/80 bg-white/80 px-3 py-1 text-sm font-medium text-neutral-600 shadow-sm backdrop-blur-sm"
            >
              <span
                class="h-2.5 w-2.5 rounded-full"
                :class="activeTabTheme.dotClass"
              ></span>
              {{ activeContent.badge }}
            </div>

            <Transition name="feature-copy" mode="out-in">
              <div :key="activeContent.title" class="pt-5">
                <div
                  class="text-2xl leading-8 font-semibold tracking-[-0.5%] text-gray-950 sm:text-[32px] sm:leading-10"
                >
                  {{ activeContent.title }}
                </div>
                <p
                  class="mt-4 max-w-xl text-base leading-7 font-normal tracking-[-1.1%] text-neutral-700"
                >
                  {{ activeContent.description }}
                </p>

                <div class="mt-6 space-y-4">
                  <div
                    v-for="point in activeContent.points"
                    :key="point"
                    class="flex items-center gap-4 text-base leading-7 font-medium tracking-[-1.1%] text-neutral-700"
                  >
                    <div
                      class="flex h-6 w-6 items-center justify-center rounded-[6px] bg-white/85 shadow-sm ring-1 ring-white/90"
                    >
                      <UIcon
                        name="i-heroicons-check-20-solid"
                        class="h-5 w-5"
                        :class="activeTabTheme.iconClass"
                      />
                    </div>
                    <span>{{ point }}</span>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <div class="relative flex justify-center lg:justify-end">
            <div
              class="pointer-events-none absolute inset-x-[12%] top-8 h-24 rounded-full blur-3xl"
              :class="activeTabTheme.glowClass"
            ></div>
            <div
              class="relative w-full max-w-100 overflow-hidden rounded-[24px] border border-white/80 bg-white/75 p-2 shadow-[0_20px_50px_-28px_rgba(15,23,42,0.55)] backdrop-blur-sm lg:max-w-121"
            >
              <div
                class="relative aspect-[1.67/1] overflow-hidden rounded-[18px] bg-white"
              >
                <Transition name="feature-tab-image" mode="out-in">
                  <div
                    :key="activeContent.imageSrc"
                    class="absolute inset-0 flex items-center justify-center"
                  >
                    <img
                      :src="activeContent.imageSrc"
                      :alt="activeContent.title"
                      class="h-full w-full rounded-[18px] object-contain"
                      loading="lazy"
                    />
                  </div>
                </Transition>
              </div>
            </div>
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
const { isAuthenticated: authenticated } = useIsAuthenticated()

const desktopPanelRefs = ref([])
const activeDesktopPanel = ref(0)
let desktopPanelObserver = null

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
]

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
]

const activeTab = ref("smart")

const tabContent = {
  smart: {
    badge: "Build smarter flows",
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
    badge: "Capture better answers",
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
    badge: "Keep submissions clean",
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
    badge: "Shape every interaction",
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
}

const activeContent = computed(
  () => tabContent[activeTab.value] || tabContent.smart,
)

const tabThemes = {
  smart: {
    tabActiveClass: "border-blue-200 bg-blue-50 text-blue-700",
    panelClass: "bg-linear-to-br from-blue-50 via-white to-sky-50",
    dotClass: "bg-blue-500",
    iconClass: "text-blue-500",
    glowClass: "bg-blue-200/80",
  },
  inputs: {
    tabActiveClass: "border-amber-200 bg-amber-50 text-amber-700",
    panelClass: "bg-linear-to-br from-amber-50 via-white to-orange-50",
    dotClass: "bg-amber-500",
    iconClass: "text-amber-500",
    glowClass: "bg-amber-200/80",
  },
  security: {
    tabActiveClass: "border-emerald-200 bg-emerald-50 text-emerald-700",
    panelClass: "bg-linear-to-br from-emerald-50 via-white to-teal-50",
    dotClass: "bg-emerald-500",
    iconClass: "text-emerald-500",
    glowClass: "bg-emerald-200/80",
  },
  control: {
    tabActiveClass: "border-violet-200 bg-violet-50 text-violet-700",
    panelClass: "bg-linear-to-br from-violet-50 via-white to-fuchsia-50",
    dotClass: "bg-violet-500",
    iconClass: "text-violet-500",
    glowClass: "bg-violet-200/80",
  },
}

const activeTabTheme = computed(
  () => tabThemes[activeTab.value] || tabThemes.smart,
)

const activeDesktopImage = computed(
  () => panels[activeDesktopPanel.value] || panels[0],
)

function setDesktopPanelRef(element, index) {
  if (!element) {
    delete desktopPanelRefs.value[index]
    return
  }

  desktopPanelRefs.value[index] = element
}

function initializeDesktopPanelObserver() {
  if (!import.meta.client || !window.matchMedia("(min-width: 1024px)").matches) {
    return
  }

  desktopPanelObserver?.disconnect()

  desktopPanelObserver = new IntersectionObserver(
    (entries) => {
      const visibleEntries = entries
        .filter((entry) => entry.isIntersecting)
        .sort(
          (entryA, entryB) => entryB.intersectionRatio - entryA.intersectionRatio,
        )

      if (visibleEntries.length === 0) return

      const nextPanelIndex = Number(visibleEntries[0].target.dataset.panelIndex)

      if (!Number.isNaN(nextPanelIndex)) {
        activeDesktopPanel.value = nextPanelIndex
      }
    },
    {
      threshold: [0.4, 0.55, 0.7, 0.85],
      rootMargin: "-12% 0px -12% 0px",
    },
  )

  desktopPanelRefs.value.forEach((element, index) => {
    if (!element) return

    element.dataset.panelIndex = index
    desktopPanelObserver.observe(element)
  })
}

onMounted(() => {
  nextTick().then(() => {
    initializeDesktopPanelObserver()
  })
})

onBeforeUnmount(() => {
  desktopPanelObserver?.disconnect()
})
</script>

<style scoped>
.feature-panel-image-enter-active,
.feature-panel-image-leave-active {
  transition: opacity 220ms ease, transform 220ms ease;
}

.feature-panel-image-enter-from,
.feature-panel-image-leave-to {
  opacity: 0;
  transform: translateY(18px) scale(0.98);
}

.feature-tab-image-enter-active,
.feature-tab-image-leave-active,
.feature-copy-enter-active,
.feature-copy-leave-active {
  transition: opacity 260ms ease, transform 260ms ease, filter 260ms ease;
}

.feature-tab-image-enter-from,
.feature-tab-image-leave-to {
  opacity: 0;
  filter: blur(8px);
  transform: translateY(16px) scale(0.97);
}

.feature-copy-enter-from,
.feature-copy-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
