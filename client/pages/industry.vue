<template>
  <div>
    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-3xl mx-auto text-center">
          <div class="flex items-center justify-center gap-4 text-sm sm:text-base font-semibold text-neutral-600">
            <div class="flex items-center gap-1 text-amber-500">
              <UIcon name="i-heroicons-star-solid" class="h-5 w-5" />
              <UIcon name="i-heroicons-star-solid" class="h-5 w-5" />
              <UIcon name="i-heroicons-star-solid" class="h-5 w-5" />
              <UIcon name="i-heroicons-star-solid" class="h-5 w-5" />
              <UIcon name="i-heroicons-star-solid" class="h-5 w-5" />
            </div>
            <div>
              4.9/5 — Trusted by 10,000+ teams in finance, fintech, and consulting
            </div>
          </div>

          <h1 class="mt-10 text-5xl font-semibold tracking-tight text-neutral-900">
            Transform financial
            <br>
            operations with powerful
            <br>
            and secure forms
          </h1>

          <p class="mt-6 text-lg font-medium leading-7 text-neutral-500 mx-auto">
            Streamline client onboarding, compliance checks, and data collection with
            OpnForm — the open-source form builder built for financial institutions that need
            flexibility, security, and control.
          </p>
        </div>

        <div class="mt-14 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
          <h2 class="text-center text-xl font-semibold text-neutral-900 tracking-tight">
            Key Features for financial institutions
          </h2>
          <div class="mt-6 flex flex-wrap justify-center gap-5">
            <div
              v-for="feature in keyFeatures"
              :key="feature.label"
              class="flex items-center gap-2 w-fit rounded-full bg-white border border-neutral-200/80 py-1.5 pl-2 pr-4 text-sm font-semibold text-neutral-900 shadow-sm"
            >
              <div class="h-8 w-8 shrink-0 rounded-full flex items-center justify-center" :class="feature.iconWrapClass">
                <UIcon :name="feature.icon" class="h-5 w-5" :class="feature.iconClass" />
              </div>
              <span class="whitespace-nowrap">
                {{ feature.label }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-16 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-3xl mx-auto text-center">
          <h2 class="text-5xl font-semibold text-neutral-900 tracking-tight">
            How financial teams
            <br>
            use OpnForm
          </h2>
          <p class="mt-4 text-lg font-medium text-neutral-500 leading-7">
            Choose how and where you host your forms – without compromising
            <br>
            on enterprise-grade security.
          </p>
        </div>

        <div class="mt-12 max-w-5xl mx-auto grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="useCase in financialUseCases"
            :key="useCase.title"
            class="rounded-3xl bg-neutral-50 border border-neutral-200/70 p-7"
          >
            <div class="h-14 w-14 rounded-2xl ring-1 flex items-center justify-center bg-white shadow-xs ring-neutral-200/70">
              <UIcon :name="useCase.icon" class="h-7 w-7 text-blue-600" />
            </div>

            <h3 class="mt-6 text-xl font-semibold text-neutral-900">
              {{ useCase.title }}
            </h3>
            <p class="mt-4 text-base font-medium text-neutral-500 leading-7">
              {{ useCase.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <TemplatesSlider class="max-w-full mb-12" />
    </section>

    <section class="py-12 bg-white">
      <Integrations />
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-3xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold text-neutral-900 tracking-tight">
            How OpnForm improves
            <br>
            financial processes
          </h2>
          <p class="mt-4 text-base sm:text-lg font-medium text-neutral-500 leading-8">
            Choose how and where you host your forms – without compromising on enterprise-
            <br>
            grade security.
          </p>
        </div>

        <div class="mt-12 max-w-5xl mx-auto grid gap-6 sm:grid-cols-2">
          <div
            v-for="item in financialImprovements"
            :key="item.title"
            class="rounded-3xl bg-neutral-50 border border-neutral-200/70 p-3"
          >
            <div class="flex items-start gap-6">
              <div class="h-14 w-14 rounded-2xl ring-1 flex items-center justify-center bg-white shadow-xs ring-neutral-200/70 shrink-0">
                <UIcon :name="item.icon" class="h-7 w-7 text-blue-600" />
              </div>

              <div>
                <h3 class="text-xl font-semibold text-neutral-900">
                  {{ item.title }}
                </h3>
                <p class="mt-4 text-base font-medium text-neutral-500 leading-7">
                  {{ item.description }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <OpenFormFooter />
  </div>
</template>

<script setup>
import TemplatesSlider from "~/components/pages/welcome/TemplatesSlider.vue"

definePageMeta({
  layout: 'default',
})

useOpnSeoMeta({
  title: 'Industry',
  description: 'Transform financial operations with powerful and secure forms built for teams that need flexibility, security, and control.',
})

const financialUseCases = [
  {
    title: 'Client onboarding',
    description: 'Create digital intake forms to collect KYC data and supporting documents securely.',
    icon: 'i-heroicons-identification',
  },
  {
    title: 'Compliance and audit requests',
    description: 'Build internal forms to track approvals, risk assessments, and incident reports.',
    icon: 'i-heroicons-clipboard-document-check',
  },
  {
    title: 'Loan applications',
    description: 'Gather borrower information and documentation without manual data entry.',
    icon: 'i-heroicons-banknotes',
  },
  {
    title: 'Expense and reimbursement submissions',
    description: 'Simplify financial workflows with automated routing and notifications.',
    icon: 'i-heroicons-receipt-refund',
  },
  {
    title: 'Customer feedback & surveys',
    description: 'Collect NPS, satisfaction, or post-service insights while keeping data private.',
    icon: 'i-heroicons-chat-bubble-left-right',
  },
  {
    title: 'Some title',
    description: 'Some text goes here...',
    icon: 'i-heroicons-circle-stack',
  },
]

const financialImprovements = [
  {
    title: 'Reduces operational overhead',
    description: 'Replace manual paperwork and email chains with centralized digital workflows.',
    icon: 'i-heroicons-document-duplicate',
  },
  {
    title: 'Ensures compliance',
    description: 'Keep sensitive data within your control — self-host or use secure cloud hosting.',
    icon: 'i-heroicons-shield-check',
  },
  {
    title: 'Accelerates turnaround times',
    description: 'Automate approvals and instantly route form submissions to the right departments.',
    icon: 'i-heroicons-bolt',
  },
  {
    title: 'Improves data accuracy',
    description: 'Validate inputs, standardize formats, and integrate directly with your existing systems.',
    icon: 'i-heroicons-check-badge',
  },
  {
    title: 'Enhances client experience',
    description: 'Provide a professional, branded experience that builds trust from the first interaction.',
    icon: 'i-heroicons-sparkles',
  },
  {
    title: 'Some titles goes here...',
    description: 'Some text goes here...',
    icon: 'i-heroicons-users',
  },
]

const keyFeatures = [
  {
    label: 'Advanced permissions and access rules',
    icon: 'i-heroicons-check-badge',
    iconWrapClass: 'bg-blue-50 ring-1 ring-blue-100',
    iconClass: 'text-blue-600',
  },
  {
    label: 'Custom form logic and conditional workflows',
    icon: 'i-heroicons-arrows-right-left',
    iconWrapClass: 'bg-red-50 ring-1 ring-red-100',
    iconClass: 'text-red-600',
  },
  {
    label: 'Encrypted data storage and SSL by default',
    icon: 'i-heroicons-lock-closed',
    iconWrapClass: 'bg-pink-50 ring-1 ring-pink-100',
    iconClass: 'text-pink-600',
  },
  {
    label: 'Audit trails and submission tracking',
    icon: 'i-heroicons-clipboard-document-check',
    iconWrapClass: 'bg-violet-50 ring-1 ring-violet-100',
    iconClass: 'text-violet-600',
  },
  {
    label: 'API & webhook integrations for automation',
    icon: 'i-heroicons-command-line',
    iconWrapClass: 'bg-sky-50 ring-1 ring-sky-100',
    iconClass: 'text-sky-600',
  },
  {
    label: 'Custom branding and white-labeling',
    icon: 'i-heroicons-swatch',
    iconWrapClass: 'bg-orange-50 ring-1 ring-orange-100',
    iconClass: 'text-orange-600',
  },
  {
    label: 'Option to self-host for full data control',
    icon: 'i-heroicons-server-stack',
    iconWrapClass: 'bg-fuchsia-50 ring-1 ring-fuchsia-100',
    iconClass: 'text-fuchsia-600',
  },
]

</script>
