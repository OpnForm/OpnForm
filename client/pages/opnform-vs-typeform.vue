<template>
  <div>
    <section class="relative py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <div class="flex items-center justify-center gap-3">
            <div class="h-14 w-14 rounded-2xl bg-white shadow-sm ring-1 ring-neutral-200 flex items-center justify-center">
              <img src="/img/logo.svg" alt="OpnForm" class="h-9 w-9" />
            </div>
            <div class="text-xs font-semibold tracking-widest text-neutral-400">
              VS
            </div>
            <div class="h-14 w-14 rounded-2xl bg-neutral-950 shadow-sm ring-1 ring-neutral-900/10 flex items-center justify-center">
              <UIcon name="i-simple-icons-typeform" class="h-8 w-8 text-white" />
            </div>
          </div>

          <h1 class="mt-10 text-4xl sm:text-5xl lg:text-6xl font-semibold text-neutral-900 tracking-tight">
            OpnForm — the powerful
            <br>
            Typeform alternative
          </h1>

          <p class="mt-5 text-base sm:text-lg font-medium leading-7 text-neutral-500 max-w-3xl mx-auto">
            Create beautiful, logic-driven forms with unlimited responses, full control,
            <br class="hidden sm:block">
            and zero limits — all for free.
          </p>

          <div class="mt-10 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
            <UButton
              size="lg"
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              label="Create Free Account"
            />
            <UButton
              size="lg"
              variant="outline"
              :to="authenticated ? { name: 'forms-create', query: { source: 'typeform-import' } } : { name: 'forms-create-guest', query: { source: 'typeform-import' } }"
              label="Have a Typeform URL? Import it now"
            />
          </div>
        </div>

        <div class="mt-20">
          <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-neutral-900">
              See the difference. Try it live.
            </h2>
            <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
              Compare OpnForm and Typeform using the same form experience —
              <br class="hidden sm:block">
              no screenshots, no assumptions.
            </p>
          </div>

          <LiveDemo class="mt-12"/>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            Free Plan Comparison:
            <br>
            OpnForm vs Typeform
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            With OpnForm, you get everything Typeform offers — and more — without limits.
          </p>
        </div>

        <div class="mt-14 max-w-6xl mx-auto">
          <div class="grid grid-cols-12 items-start">
            <div class="col-span-12 lg:col-span-4">
              <div class="h-20 hidden lg:block" />
              <div
                v-for="(row, idx) in freePlanComparison"
                :key="row.label"
                class="px-6 py-4 text-base font-semibold text-neutral-900 rounded-l-2xl"
                :class="idx % 2 === 0 ? 'bg-neutral-50' : 'bg-white'"
              >
                {{ row.label }}
              </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
              <div class="rounded-3xl border-2 border-blue-500 bg-white shadow-sm overflow-hidden">
                <div class="h-20 px-6 flex items-center justify-center gap-3">
                  <img src="/img/logo.svg" alt="OpnForm" class="h-9 w-9" />
                  <div class="text-lg font-semibold text-neutral-900">
                    OpnForm
                    <span class="ml-2 text-neutral-500 font-medium">(Free)</span>
                  </div>
                </div>
                <div
                  v-for="(row, idx) in freePlanComparison"
                  :key="row.label"
                  class="px-6 py-4 text-base text-neutral-900"
                  :class="idx % 2 === 0 ? 'bg-neutral-50' : 'bg-white'"
                >
                  {{ row.cells[0] }}
                </div>
              </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
              <div class="h-20 flex items-center justify-center gap-3">
                <UIcon name="i-simple-icons-typeform" class="h-8 w-8 text-neutral-900" />
                <div class="text-lg font-semibold text-neutral-900">
                  Typeform
                  <span class="ml-2 text-neutral-500 font-medium">(Free)</span>
                </div>
              </div>
              <div
                v-for="(row, idx) in freePlanComparison"
                :key="row.label"
                class="px-6 py-4 text-base text-neutral-900 rounded-r-2xl"
                :class="idx % 2 === 0 ? 'bg-neutral-50' : 'bg-white'"
              >
                {{ row.cells[1] }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            Why Users Switch from
            <br>
            Typeform to OpnForm
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            Typeform is beautiful — but OpnForm is powerful, open, and free.
          </p>
        </div>

        <div class="mt-14 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in switchReasons"
            :key="item.title"
            class="rounded-3xl bg-neutral-50 p-8"
          >
            <div class="rounded-2xl bg-white h-16 w-16 flex items-center justify-center shadow-sm ring-1 ring-neutral-200">
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div class="mt-8 text-xl font-semibold text-neutral-900">
              {{ item.title }}
            </div>
            <div class="mt-3 text-base font-medium leading-7 text-neutral-600">
              {{ item.description }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            Feature-by-Feature
            <br>
            Comparison
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            OpnForm gives you the same polished experience — but open, customizable, and accessible to everyone.
          </p>
        </div>

        <div class="mt-14 max-w-5xl mx-auto">
          <div class="rounded-3xl border border-neutral-200 overflow-hidden bg-white">
            <table class="w-full border-collapse">
              <thead>
                <tr class="bg-white">
                  <th class="w-[45%] px-6 py-5 text-left text-sm font-semibold text-neutral-700 border-b border-neutral-200" />
                  <th class="w-[27.5%] px-6 py-5 border-b border-neutral-200 text-center">
                    <div class="flex items-center justify-center gap-3">
                      <img src="/img/logo.svg" alt="OpnForm" class="h-8 w-8" />
                      <span class="text-base font-semibold text-neutral-900">OpnForm</span>
                    </div>
                  </th>
                  <th class="w-[27.5%] px-6 py-5 border-b border-neutral-200 text-center">
                    <div class="flex items-center justify-center gap-3">
                      <UIcon name="i-simple-icons-typeform" class="h-7 w-7 text-neutral-900" />
                      <span class="text-base font-semibold text-neutral-900">Typeform</span>
                    </div>
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="row in featureComparison"
                  :key="row.label"
                  class="border-b border-neutral-200 last:border-b-0"
                >
                  <td class="px-6 py-5 text-sm sm:text-base font-medium text-neutral-900">
                    {{ row.label }}
                  </td>

                  <td class="px-6 py-5 text-center border-l border-neutral-200">
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
                        class="text-sm sm:text-base font-medium text-neutral-600"
                      >
                        {{ row.cells[0] }}
                      </span>
                    </div>
                  </td>

                  <td class="px-6 py-5 text-center border-l border-neutral-200">
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
                        class="text-sm sm:text-base font-medium text-neutral-600"
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

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            See the real cost as your
            <br>
            usage grows
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            Move the slider to estimate your monthly submissions and see how pricing compares.
          </p>
        </div>

        <div class="mt-14 max-w-4xl mx-auto">
          <div class="text-center text-lg font-semibold text-neutral-900">
            Expected submissions per month
          </div>

          <div class="mt-8">
            <input
              v-model.number="submissionsIndex"
              type="range"
              :min="0"
              :max="submissionOptions.length - 1"
              :step="1"
              class="w-full h-2 rounded-full appearance-none cursor-pointer"
              :style="sliderStyle"
            >
          </div>

          <div class="mt-5 grid grid-cols-11 gap-2 text-center text-xs sm:text-sm font-semibold text-neutral-500">
            <div
              v-for="(val, idx) in submissionOptions"
              :key="val"
              class="flex flex-col items-center"
            >
              <div class="h-3 w-px bg-neutral-300" />
              <div class="mt-2 whitespace-nowrap">
                {{ formatSubmissionsLabel(val, idx) }}
              </div>
            </div>
          </div>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 max-w-3xl mx-auto">
          <div class="rounded-3xl bg-neutral-50 p-10 text-center">
            <div class="text-2xl font-semibold text-neutral-900">
              OpnForm
            </div>
            <div class="mt-4 text-6xl sm:text-7xl font-semibold tracking-tight text-neutral-900">
              {{ formatCurrency(opnformPrice) }}
            </div>
          </div>

          <div class="rounded-3xl bg-neutral-50 p-10 text-center">
            <div class="text-2xl font-semibold text-neutral-900">
              TypeForm
            </div>
            <div class="mt-4 text-6xl sm:text-7xl font-semibold tracking-tight text-neutral-900">
              {{ formatCurrency(typeformPrice) }}
            </div>
          </div>
        </div>

        <div class="mt-10 text-center text-lg sm:text-xl font-semibold text-neutral-900">
          You save {{ formatCurrency(monthlySavings) }} per month with OpnForm
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            Integrations that
            <br>
            work for you
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            Connect OpnForm to your favorite tools in seconds.
          </p>
          <p class="mt-6 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            Sync form data automatically with Notion, Airtable, Google Sheets, Slack, Zapier, and X
            <br class="hidden sm:block">
            more via webhooks and automation platforms.
          </p>
        </div>

        <div class="mt-14 max-w-3xl mx-auto">
          <div class="grid grid-cols-4 gap-4">
            <div
              v-for="item in integrationLogos"
              :key="item.name"
              :title="item.name"
              class="h-24 rounded-3xl border border-neutral-200 bg-white flex items-center justify-center shadow-sm hover:shadow-md transition-shadow"
            >
              <Icon :name="item.icon" class="h-10 w-10" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            Privacy-first and
            <br>
            open by design
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium leading-7 text-neutral-500">
            Built for teams, enterprises, and developers who need full control over their data.
          </p>
        </div>

        <div class="mt-14 grid gap-8 lg:grid-cols-2 max-w-6xl mx-auto">
          <div
            v-for="item in privacyFeatures"
            :key="item.title"
            class="rounded-3xl bg-neutral-50 p-8 sm:p-10 flex gap-6 items-start"
          >
            <div class="h-16 w-16 rounded-2xl bg-white shadow-sm ring-1 ring-neutral-200 flex items-center justify-center flex-shrink-0">
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div>
              <div class="text-xl sm:text-2xl font-semibold text-neutral-900">
                {{ item.title }}
              </div>
              <div class="mt-3 text-base sm:text-lg font-medium leading-7 text-neutral-600">
                {{ item.description }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <Testimonials />
    </section>
    
    <OpenFormFooter />
  </div>
</template>

<script setup>
import LiveDemo from '~/components/pages/welcome/LiveDemo.vue'
import Testimonials from '~/components/pages/welcome/Testimonials.vue'
import { useIsAuthenticated } from '~/composables/useAuthFlow'

definePageMeta({
  layout: 'default',
})

useOpnSeoMeta({
  title: 'OpnForm vs Typeform',
  description: 'A powerful Typeform alternative with unlimited responses and full control — for free.',
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const freePlanComparison = [
  {
    label: 'Monthly Responses',
    cells: ['Unlimited submissions per month', '10 responses per month'],
  },
  {
    label: 'Forms Allowed',
    cells: ['Unlimited forms', '1 active form'],
  },
  {
    label: 'Branding Removal',
    cells: ['Not available on free', 'Not available'],
  },
  {
    label: 'Conditional Logic',
    cells: ['Conditional logic included', 'Basic conditional logic available'],
  },
  {
    label: 'File Uploads',
    cells: ['File uploads available', 'Not Included'],
  },
  {
    label: 'Custom CSS',
    cells: ['No direct custom CSS', 'No custom CSS'],
  },
  {
    label: 'Integrations',
    cells: ['Webhooks and API access available', 'Only limited native integrations'],
  },
  {
    label: 'Self-hosting Option',
    cells: ['Available', 'No self-hosting'],
  },
  {
    label: 'Price for 1,000 Responses',
    cells: ['Free', '$10/month'],
  },
]

const switchReasons = [
  {
    icon: 'i-heroicons-infinity',
    title: 'No limits, no hidden costs',
    description: 'Unlimited forms and responses on every plan.',
  },
  {
    icon: 'i-heroicons-paint-brush',
    title: 'Fully branded forms',
    description: 'Add your logo, colors, and even custom CSS.',
  },
  {
    icon: 'i-heroicons-command-line',
    title: 'Advanced logic & calculations',
    description: 'Create dynamic, intelligent forms easily.',
  },
  {
    icon: 'i-heroicons-circle-stack',
    title: 'Open source',
    description: 'Own your data, host it yourself, or use our managed version.',
  },
  {
    icon: 'i-heroicons-code-bracket-square',
    title: 'Developer-friendly',
    description: 'REST API, webhooks, and automation-ready.',
  },
  {
    icon: 'i-heroicons-user-group',
    title: 'Some title goes here...',
    description: 'Some text goes here...',
  },
]

const featureComparison = [
  {
    label: 'Unlimited responses',
    cells: ['Y', 'N'],
  },
  {
    label: 'Logic jumps & branching',
    cells: ['Y', 'Y'],
  },
  {
    label: 'File uploads',
    cells: ['Y', 'Y'],
  },
  {
    label: 'Calculations',
    cells: ['Y', 'Paid'],
  },
  {
    label: 'Custom branding',
    cells: ['Y', 'N'],
  },
  {
    label: 'Custom CSS',
    cells: ['Y', 'N'],
  },
  {
    label: 'Integrations',
    cells: ['100+', 'Limited'],
  },
  {
    label: 'Analytics dashboard',
    cells: ['Y', 'Paid'],
  },
  {
    label: 'Open source',
    cells: ['Y', 'N'],
  },
  {
    label: 'Self-hosting',
    cells: ['Y', 'N'],
  },
  {
    label: 'API access',
    cells: ['Y', 'Paid'],
  },
  {
    label: 'GDPR compliance',
    cells: ['Y', 'Y'],
  },
]


const submissionOptions = [100, 250, 500, 1000, 2500, 5000, 7500, 10000, 15000, 20000, 25000]
const submissionsIndex = ref(7)

const submissionsPerMonth = computed(() => submissionOptions[submissionsIndex.value] ?? 10000)
const sliderPercent = computed(() => (submissionsIndex.value / (submissionOptions.length - 1)) * 100)
const sliderStyle = computed(() => ({
  background: `linear-gradient(to right, #2563eb 0%, #2563eb ${sliderPercent.value}%, #f3f4f6 ${sliderPercent.value}%, #f3f4f6 100%)`,
}))

function formatSubmissionsLabel(value, idx) {
  const formatted = new Intl.NumberFormat('en-US').format(value)
  return idx === submissionOptions.length - 1 ? `${formatted}+` : formatted
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
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
const typeformPrice = computed(() => getTypeformPrice(submissionsPerMonth.value))
const monthlySavings = computed(() => Math.max(0, typeformPrice.value - opnformPrice.value))


const integrationLogos = [
  { name: 'Google Sheets', icon: 'simple-icons:googlesheets' },
  { name: 'Notion', icon: 'simple-icons:notion' },
  { name: 'Airtable', icon: 'simple-icons:airtable' },
  { name: 'Slack', icon: 'simple-icons:slack' },
  { name: 'Zapier', icon: 'simple-icons:zapier' },
  { name: 'n8n', icon: 'simple-icons:n8n' },
  { name: 'Make', icon: 'simple-icons:make' },
  { name: 'Discord', icon: 'simple-icons:discord' },
]

const privacyFeatures = [
  {
    icon: 'i-heroicons-code-bracket-square',
    title: 'Open Source',
    description: 'Self-host OpnForm or use our managed service — your data always belongs to you.',
  },
  {
    icon: 'i-heroicons-globe-europe-africa',
    title: 'GDPR-Compliant',
    description: 'Your forms and responses stay private and secure.',
  },
  {
    icon: 'i-heroicons-check-badge',
    title: 'Enterprise-Ready',
    description: 'SSO, API access, custom SLAs, and advanced permissions for teams who need scale.',
  },
  {
    icon: 'i-heroicons-shield-check',
    title: 'Transparency',
    description: 'No tracking pixels, no hidden analytics — just clean, honest form building.',
  },
]

</script>
