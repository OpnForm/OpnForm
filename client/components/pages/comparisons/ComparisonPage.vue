<template>
  <div>
    <section class="bg-white">
      <div class="relative">
        <div class="py-14 sm:py-28 px-8 lg:px-12 relative z-2">
          <div class="max-w-3xl mx-auto text-center">
            <div class="flex items-center justify-center gap-4">
              <div
                class="h-16 w-16 rounded-2xl bg-blue-500! shadow-sm flex items-center justify-center"
              >
                <img
                  src="/img/logo.svg"
                  alt="OpnForm"
                  class="h-9 w-9 filter-[sepia(1)_brightness(2)_saturate(0)]"
                />
              </div>
              <div
                class="text-xs leading-4 font-medium tracking-[4%] text-gray-400 px-2 py-1"
              >
                VS
              </div>
              <div
                class="h-16 w-16 rounded-2xl flex items-center justify-center"
              >
                <Icon :name="competitorIcon" :class="competitorIconClass" class="h-16 w-16" />
              </div>
            </div>

            <h1
              class="mt-12 sm:mt-16 text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950"
            >
              {{ heroTitle }}
            </h1>
            <p
              class="my-8 text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600"
            >
              <slot name="hero-subtitle">
                Create beautiful, logic-driven forms with <b>unlimited responses, full control, and zero limits</b>
                — all for free.
              </slot>
            </p>

            <div
              class="flex flex-col sm:flex-row items-center justify-center gap-4"
            >
              <UButton
                size="lg"
                :to="{
                  name: authenticated ? 'forms-create' : 'forms-create-guest',
                }"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Create Free Account"
                class="w-fit pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
              />
              <UButton
                size="lg"
                variant="outline"
                :to="heroSecondaryCtaTo"
                :label="resolvedHeroSecondaryCtaLabel"
                class="w-fit px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
              />
            </div>
          </div>
        </div>
        <div
          class="w-full h-full bg-linear-to-b from-white from-40% via-blue-50 via-65% to-white to-90% absolute inset-0"
        ></div>
      </div>
      <div class="relative">
        <div class="pt-1 pb-14 sm:pb-24 px-8 lg:px-12 relative z-2">
          <div class="max-w-3xl mx-auto text-center">
            <h2
              class="text-2xl leading-8 font-semibold tracking-[-0.5%] text-gray-950"
            >
              See the difference. Try it live.
            </h2>
            <p
              class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
            >
              Compare OpnForm and {{ competitorName }} using the same form
              experience —
              <br class="hidden sm:block" />
              no screenshots, no assumptions.
            </p>
          </div>
          <LiveDemo class="mt-12" />
        </div>
        <div
          class="w-full h-full bg-linear-to-b from-white from-50% via-blue-50 via-70% to-white to-90% absolute inset-0"
        ></div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-4xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
          >
            Free Plan Comparison:
            <br />
            OpnForm vs {{ competitorName }}
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            With OpnForm, you get everything {{ competitorName }} offers — and
            more — without limits.
          </p>
        </div>

        <div class="mt-12 sm:mt-16">
          <div class="grid grid-cols-12 items-start">
            <div class="col-span-12 md:col-span-4 pb-8">
              <div class="h-20 hidden md:block" />
              <div
                v-for="(row, idx) in freePlanComparison"
                :key="row.label"
                class="px-8 py-4 text-base leading-8 tracking-[-1.1%] font-medium text-gray-950 rounded-l-[12px]"
                :class="idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'"
              >
                {{ row.label }}
              </div>
            </div>

            <div class="col-span-12 md:col-span-4">
              <div
                class="rounded-[24px] pb-8 border-2 border-blue-500 bg-white shadow-sm overflow-hidden"
              >
                <div class="h-20 px-6 flex items-center justify-center gap-2">
                  <img src="/img/logo.svg" alt="OpnForm" class="h-9 w-9" />
                  <div class="text-lg font-semibold text-gray-950">
                    OpnForm
                    <span
                      class="ml-3 text-gray-600 text-base leading-8 tracking-[-1.1%] font-medium"
                      >(Free)</span
                    >
                  </div>
                </div>
                <div
                  v-for="(row, idx) in freePlanComparison"
                  :key="row.label"
                  class="px-6 py-4 text-base leading-8 tracking-[-1.1%] font-medium text-gray-950"
                  :class="idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'"
                >
                  {{ row.cells[0] }}
                </div>
              </div>
            </div>

            <div class="col-span-12 md:col-span-4 pb-8">
              <div class="h-20 flex items-center justify-center gap-2">
                <Icon :name="competitorIcon" :class="competitorIconClass" class="h-8 w-8" />
                <div class="text-lg font-semibold text-gray-950">
                  {{ competitorName }}
                  <span
                    class="ml-3 text-gray-600 text-base leading-8 tracking-[-1.1%] font-medium"
                    >(Free)</span
                  >
                </div>
              </div>
              <div
                v-for="(row, idx) in freePlanComparison"
                :key="row.label"
                class="px-6 py-4 text-base leading-8 tracking-[-1.1%] font-medium text-gray-600 rounded-r-[12px]"
                :class="idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'"
              >
                {{ row.cells[1] }}
              </div>
            </div>
          </div>
        </div>

        <div class="mt-12 sm:mt-16 flex justify-center items-center">
          <UButton
            size="lg"
            :to="{
              name: authenticated ? 'forms-create' : 'forms-create-guest',
            }"
            trailing-icon="i-heroicons-arrow-up-right-20-solid"
            label="Get started. It's FREE!"
            class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-4xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
          >
            {{ switchSectionTitle }}
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            {{ competitorName }} is beautiful — but OpnForm is powerful, open,
            and free.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in switchReasons"
            :key="item.title"
            class="rounded-3xl border border-gray-200 bg-gray-50 p-8"
          >
            <div
              class="rounded-[20px] bg-white h-16 w-16 flex items-center justify-center shadow-sm"
            >
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div class="mt-8 text-xl leading-7 font-semibold text-gray-950">
              {{ item.title }}
            </div>
            <div
              class="mt-4 text-base font-medium leading-7 tracking-[-1.1%] text-gray-600"
            >
              {{ item.description }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-4xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
          >
            Feature-by-Feature
            <br />
            Comparison
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            {{ featureSectionSubtitle }}
          </p>
        </div>

        <div class="mt-12 sm:mt-16">
          <div
            class="rounded-[24px] border border-gray-200 overflow-hidden bg-white"
          >
            <table class="w-full border-collapse">
              <thead>
                <tr class="bg-white">
                  <th
                    class="w-[40%] sm:w-[45%] px-4 py-5 text-left text-sm font-semibold text-gray-700 border-b border-gray-200"
                  />
                  <th
                    class="w-[30%] sm:w-[27.5%] px-4 py-5 border-b border-l border-gray-200 text-center"
                  >
                    <div
                      class="flex items-center justify-center flex-col sm:flex-row gap-3"
                    >
                      <img src="/img/logo.svg" alt="OpnForm" class="h-8 w-8" />
                      <span class="text-lg font-semibold text-gray-950"
                        >OpnForm</span
                      >
                    </div>
                  </th>
                  <th
                    class="w-[30%] sm:w-[27.5%] px-2 py-5 border-b border-l border-gray-200 text-center"
                  >
                    <div class="flex items-center justify-center gap-3">
                      <Icon
                        :name="competitorIcon"
                        :class="competitorIconClass"
                        class="h-8 w-8 text-black"
                      />
                      <span class="text-lg font-semibold text-gray-950">{{
                        competitorName
                      }}</span>
                    </div>
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="row in featureComparison"
                  :key="row.label"
                  class="border-b border-gray-200 last:border-b-0"
                >
                  <td
                    class="px-4 sm:px-8 py-4 text-sm sm:text-base sm:leading-7 trackig-[-1.1%] font-medium text-gray-950"
                  >
                    {{ row.label }}
                  </td>

                  <td class="px-8 py-4 text-center border-l border-gray-200">
                    <div class="flex items-center justify-center gap-2">
                      <UIcon
                        v-if="row.cells[0] === 'Y'"
                        name="i-heroicons-check"
                        class="h-6 w-6 text-green-500"
                      />
                      <UIcon
                        v-else-if="row.cells[0] === 'N'"
                        name="i-heroicons-x-mark"
                        class="h-6 w-6 text-red-500"
                      />
                      <span
                        v-else
                        class="text-sm sm:text-base sm:leading-7 trackig-[-1.1%] font-medium text-gray-600"
                      >
                        {{ row.cells[0] }}
                      </span>
                    </div>
                  </td>

                  <td class="px-8 py-4 text-center border-l border-neutral-200">
                    <div class="flex items-center justify-center gap-2">
                      <UIcon
                        v-if="row.cells[1] === 'Y'"
                        name="i-heroicons-check"
                        class="h-6 w-6 text-green-500"
                      />
                      <UIcon
                        v-else-if="row.cells[1] === 'N'"
                        name="i-heroicons-x-mark"
                        class="h-6 w-6 text-red-500"
                      />
                      <span
                        v-else
                        class="text-sm sm:text-base sm:leading-7 trackig-[-1.1%] font-medium text-gray-600"
                      >
                        {{ row.cells[1] }}
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-4xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
          >
            See the real cost as your
            <br />
            usage grows
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            Move the slider to estimate your monthly submissions and see how
            pricing compares.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 max-w-3xl mx-auto">
          <div class="text-center text-xl leading-7 font-medium text-gray-950">
            Expected submissions per month
          </div>

          <div class="mt-6">
            <input
              v-model.number="submissionsIndex"
              type="range"
              :min="0"
              :max="submissionOptions.length - 1"
              :step="1"
              class="w-full h-2 rounded-full appearance-none cursor-pointer"
              :style="sliderStyle"
            />
          </div>

          <div
            class="mt-2 grid grid-cols-11 gap-2 text-center text-xs sm:text-sm leading-5 tracking-[-0.6%] font-medium text-gray-600"
          >
            <div
              v-for="(val, idx) in submissionOptions"
              :key="val"
              class="flex flex-col items-center"
            >
              <div class="h-3 w-px bg-gray-300" />
              <div class="mt-2 whitespace-nowrap">
                {{ formatSubmissionsLabel(val, idx) }}
              </div>
            </div>
          </div>
        </div>

        <div class="mt-10 sm:mt-12 grid gap-6 sm:grid-cols-2 max-w-xl mx-auto">
          <div
            class="rounded-3xl border border-gray-200 bg-gray-50 p-8 text-center"
          >
            <div class="text-2xl leading-8 font-medium text-gray-950">
              OpnForm
            </div>
            <div
              class="mt-4 text-4xl sm:text-[56px] sm:leading-16 font-medium tracking-[-1%] text-gray-950"
            >
              {{ formatCurrency(opnformPrice) }}
            </div>
          </div>

          <div
            class="rounded-3xl border border-gray-200 bg-gray-50 p-8 text-center"
          >
            <div class="text-2xl leading-8 font-medium text-gray-950">
              {{ competitorName }}
            </div>
            <div
              class="mt-4 text-4xl sm:text-[56px] sm:leading-16 font-medium tracking-[-1%] text-gray-950"
            >
              {{ formatCurrency(competitorPrice) }}
            </div>
          </div>
        </div>

        <div
          class="mt-6 text-center text-xl font-medium leading-7 text-gray-950"
        >
          You save {{ formatCurrency(monthlySavings) }} per month with OpnForm
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-4xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
          >
            Integrations that
            <br />
            work for you
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            Connect OpnForm to your favorite tools in seconds.
          </p>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            Sync form data automatically with Email, Google Sheets, Slack,
            Zapier, Telegram, and more
            <br class="hidden sm:block" />
            via webhooks and automation platforms.
          </p>
        </div>

        <div class="mt-12 sm:mt-14 max-w-md mx-auto">
          <div class="flex justify-center items-center gap-6">
            <div
              v-for="item in integrationLogosrow1"
              :key="item.name"
              :title="item.name"
              class="border flex items-center justify-center rounded-2xl h-18 sm:h-30 min-w-18 sm:min-w-30"
            >
              <UIcon
                :name="item.icon"
                class="h-12 sm:h-14 w-12 sm:w-14"
                :class="item.iconClass"
              />
            </div>
          </div>
          <div class="mt-6 flex justify-center items-center gap-6">
            <div
              v-for="item in integrationLogosrow2"
              :key="item.name"
              :title="item.name"
              class="border flex items-center justify-center rounded-2xl h-18 sm:h-30 min-w-18 sm:min-w-30"
            >
              <UIcon
                :name="item.icon"
                class="h-12 sm:h-14 w-12 sm:w-14"
                :class="item.iconClass"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-4xl sm:text-5xl sm:leading-14 font-semibold tracking-[-1%] text-gray-950"
          >
            Privacy-first and
            <br />
            open by design
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            Built for teams, enterprises, and developers who need full control
            over their data.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-6 md:grid-cols-2">
          <div
            v-for="item in privacyFeatures"
            :key="item.title"
            class="rounded-3xl border border-gray-200 bg-gray-50 p-8 flex gap-6 items-start"
          >
            <div
              class="h-16 w-16 rounded-[20px] bg-white shadow-sm flex items-center justify-center shrink-0"
            >
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div>
              <div class="text-xl leading-7 font-medium text-gray-950">
                {{ item.title }}
              </div>
              <div
                class="mt-3 text-base font-medium leading-7 tracking-[-1.1%] text-gray-600"
              >
                {{ item.description }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <Testimonials />
    </section>

    <OpenFormFooter />
  </div>
</template>

<script setup>
import LiveDemo from "~/components/pages/welcome/LiveDemo.vue"
import Testimonials from "~/components/pages/welcome/Testimonials.vue"
import { useIsAuthenticated } from "~/composables/useAuthFlow"

const props = defineProps({
  competitorName: {
    type: String,
    required: true,
  },
  competitorIcon: {
    type: String,
    required: true,
  },
  competitorIconClass: {
    type: String,
    default: null,
  },
  heroTitle: {
    type: String,
    required: true,
  },
  /** Overrides default: `Have a {competitor} URL? Import it now` */
  heroSecondaryCtaLabel: {
    type: String,
    default: null,
  },
  heroSecondaryCtaTo: {
    type: [String, Object],
    default: "#",
  },
  featureSectionSubtitle: {
    type: String,
    default:
      "OpnForm gives you the same polished experience — but open, customizable, and accessible to everyone.",
  },
  freePlanComparison: {
    type: Array,
    required: true,
  },
  switchReasons: {
    type: Array,
    required: true,
  },
  featureComparison: {
    type: Array,
    required: true,
  },
  getCompetitorPrice: {
    type: Function,
    required: true,
  }
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const switchSectionTitle = computed(
  () => `Why Users Switch from ${props.competitorName} to OpnForm`,
)

const resolvedHeroSecondaryCtaLabel = computed(
  () =>
    props.heroSecondaryCtaLabel ??
    `Have a ${props.competitorName} URL? Import it now`,
)

const integrationLogosrow1 = [
  {name: "Email", icon: "heroicons:envelope-20-solid", iconClass: "text-[#2563EB]" },
  { name: "Slack", icon: "simple-icons:slack", iconClass: "text-[#4A154B]" },
  { name: "Discord", icon: "ic:baseline-discord", iconClass: "text-[#5865F2]" },
  { name: "Webhook", icon: "material-symbols:webhook", iconClass: "text-[#0061FF]" },
]

const integrationLogosrow2 = [
  { name: "Telegram", icon: "mdi:telegram", iconClass: "text-[#27A7E7]" },
  { name: "Zapier", icon: "simple-icons:zapier", iconClass: "text-[#FF4A00]" },
  { name: "Google Sheets", icon: "mdi:google-spreadsheet", iconClass: "text-[#34A853]" },
  { name: "n8n", icon: "simple-icons:n8n", iconClass: "text-[#EA4B71]" },
]

const privacyFeatures = [
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Open Source",
    description:
      "Self-host OpnForm or use our managed service — your data always belongs to you.",
  },
  {
    icon: "i-heroicons-globe-europe-africa",
    title: "GDPR-Compliant",
    description: "Your forms and responses stay private and secure.",
  },
  {
    icon: "i-heroicons-check-badge",
    title: "Enterprise-Ready",
    description:
      "SSO, API access, custom SLAs, and advanced permissions for teams who need scale.",
  },
  {
    icon: "i-heroicons-shield-check",
    title: "Transparency",
    description:
      "No tracking pixels, no hidden analytics — just clean, honest form building.",
  },
]


const submissionOptions = [
  100, 250, 500, 1000, 2500, 5000, 7500, 10000, 15000, 20000, 25000,
]
const submissionsIndex = ref(7)

const submissionsPerMonth = computed(
  () => submissionOptions[submissionsIndex.value] ?? 10000,
)
const sliderPercent = computed(
  () => (submissionsIndex.value / (submissionOptions.length - 1)) * 100,
)
const sliderStyle = computed(() => ({
  background: `linear-gradient(to right, #2563eb 0%, #2563eb ${sliderPercent.value}%, #f3f4f6 ${sliderPercent.value}%, #f3f4f6 100%)`,
}))

function formatSubmissionsLabel(value, idx) {
  const formatted = new Intl.NumberFormat("en-US").format(value)
  return idx === submissionOptions.length - 1 ? `${formatted}+` : formatted
}

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 0,
  }).format(amount)
}

const opnformPrice = computed(() => 0)
const competitorPrice = computed(() =>
  props.getCompetitorPrice(submissionsPerMonth.value),
)
const monthlySavings = computed(() =>
  Math.max(0, competitorPrice.value - opnformPrice.value),
)
</script>
