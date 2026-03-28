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
                class="h-16 w-16 rounded-2xl bg-gray-950 shadow-sm flex items-center justify-center"
              >
                <Icon
                  name="opnform:typeform"
                  class="h-16 w-16"
                />
              </div>
            </div>

            <h1
              class="mt-12 sm:mt-16 text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950"
            >
              OpnForm — the powerful Typeform alternative
            </h1>

            <p
              class="my-8 text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600"
            >
              Create beautiful, logic-driven forms with unlimited responses,
              full control,
              <br class="hidden sm:block" />
              and zero limits — all for free.
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
                to="#"
                label="Have a Typeform URL? Import it now"
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
              Compare OpnForm and Typeform using the same form experience —
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
            OpnForm vs Typeform
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            With OpnForm, you get everything Typeform offers — and more —
            without limits.
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
                <Icon
                  name="opnform:typeform"
                  class="h-8 w-8"
                />
                <div class="text-lg font-semibold text-gray-950">
                  Typeform
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
            Why Users Switch from Typeform to OpnForm
          </h2>
          <p
            class="mt-4 text-base tracking-[-1.1%] font-medium leading-8 text-gray-600"
          >
            Typeform is beautiful — but OpnForm is powerful, open, and free.
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
            OpnForm gives you the same polished experience — but open,
            customizable, and accessible to everyone.
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
                        name="opnform:typeform"
                        class="h-8 w-8 text-black"
                      />
                      <span class="text-lg font-semibold text-gray-950"
                        >Typeform</span
                      >
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
              TypeForm
            </div>
            <div
              class="mt-4 text-4xl sm:text-[56px] sm:leading-16 font-medium tracking-[-1%] text-gray-950"
            >
              {{ formatCurrency(typeformPrice) }}
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

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Typeform",
  description:
    "A powerful Typeform alternative with unlimited responses and full control — for free.",
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const freePlanComparison = [
  {
    label: "Monthly Responses",
    cells: ["Unlimited submissions per month", "10 responses per month"],
  },
  {
    label: "Forms Allowed",
    cells: ["Unlimited forms", "1 active form"],
  },
  {
    label: "Branding Removal",
    cells: ["Not available on free", "Not available"],
  },
  {
    label: "Conditional Logic",
    cells: ["Conditional logic included", "Basic conditional logic available"],
  },
  {
    label: "File Uploads",
    cells: ["File uploads available", "Not Included"],
  },
  {
    label: "Custom CSS",
    cells: ["No direct custom CSS", "No custom CSS"],
  },
  {
    label: "Integrations",
    cells: [
      "Webhooks and API access available",
      "Only limited native integrations",
    ],
  },
  {
    label: "Self-hosting Option",
    cells: ["Available", "No self-hosting"],
  },
  {
    label: "Price for 1,000 Responses",
    cells: ["Free", "$10/month"],
  },
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "No limits, no hidden costs",
    description: "Unlimited forms and responses on every plan.",
  },
  {
    icon: "i-heroicons-paint-brush",
    title: "Fully branded forms",
    description: "Add your logo, colors, and even custom CSS.",
  },
  {
    icon: "i-heroicons-command-line",
    title: "Advanced logic & calculations",
    description: "Create dynamic, intelligent forms easily.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Open source",
    description: "Own your data, host it yourself, or use our managed version.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly",
    description: "REST API, webhooks, and automation-ready.",
  },
  {
    icon: "i-heroicons-user-group",
    title: "Some title goes here...",
    description: "Some text goes here...",
  },
]

const featureComparison = [
  {
    label: "Unlimited responses",
    cells: ["Y", "N"],
  },
  {
    label: "Logic jumps & branching",
    cells: ["Y", "Y"],
  },
  {
    label: "File uploads",
    cells: ["Y", "Y"],
  },
  {
    label: "Calculations",
    cells: ["Y", "Paid"],
  },
  {
    label: "Custom branding",
    cells: ["Y", "N"],
  },
  {
    label: "Custom CSS",
    cells: ["Y", "N"],
  },
  {
    label: "Integrations",
    cells: ["100+", "Limited"],
  },
  {
    label: "Analytics dashboard",
    cells: ["Y", "Paid"],
  },
  {
    label: "Open source",
    cells: ["Y", "N"],
  },
  {
    label: "Self-hosting",
    cells: ["Y", "N"],
  },
  {
    label: "API access",
    cells: ["Y", "Paid"],
  },
  {
    label: "GDPR compliance",
    cells: ["Y", "Y"],
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

function getTypeformPrice(submissions) {
  // A simple, easy-to-tweak approximation for the comparison chart.
  if (submissions <= 100) return 0
  if (submissions <= 1000) return 29
  if (submissions <= 2500) return 59
  if (submissions <= 5000) return 99
  if (submissions <= 10000) return 149
  if (submissions <= 15000) return 249
  if (submissions <= 20000) return 399
  return 499
}

const opnformPrice = computed(() => 0)
const typeformPrice = computed(() =>
  getTypeformPrice(submissionsPerMonth.value),
)
const monthlySavings = computed(() =>
  Math.max(0, typeformPrice.value - opnformPrice.value),
)

const integrationLogosrow1 = [
  {
    name: "Email",
    icon: "heroicons:envelope-20-solid",
    iconClass: "text-[#2563EB]",
  },
  { name: "Slack", icon: "simple-icons:slack", iconClass: "text-[#4A154B]" },
  { name: "Discord", icon: "ic:baseline-discord", iconClass: "text-[#5865F2]" },
  {
    name: "Webhook",
    icon: "material-symbols:webhook",
    iconClass: "text-[#0061FF]",
  },
]

const integrationLogosrow2 = [
  { name: "Telegram", icon: "mdi:telegram", iconClass: "text-[#27A7E7]" },
  { name: "Zapier", icon: "simple-icons:zapier", iconClass: "text-[#FF4A00]" },
  {
    name: "Google Sheets",
    icon: "mdi:google-spreadsheet",
    iconClass: "text-[#34A853]",
  },
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
</script>
