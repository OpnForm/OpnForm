<template>
  <div>
    <section class="px-8 sm:px-12 py-14 sm:py-28 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-3xl mx-auto text-center">
          <div class="flex items-center justify-center gap-4">
            <div class="flex items-center gap-0.5 text-amber-500">
              <UIcon name="i-heroicons-star-solid" class="h-4 w-4" />
              <UIcon name="i-heroicons-star-solid" class="h-4 w-4" />
              <UIcon name="i-heroicons-star-solid" class="h-4 w-4" />
              <UIcon name="i-heroicons-star-solid" class="h-4 w-4" />
              <UIcon name="i-heroicons-star-solid" class="h-4 w-4" />
            </div>
            <div
              class="text-base leading-7 tracking-[-1.1%] font-medium text-neutral-600"
            >
              4.9/5 — Trusted by 10,000+ teams in finance, fintech, and
              consulting
            </div>
          </div>

          <h1
            class="mt-8 text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-neutral-900"
          >
            Transform financial
            <br class="hidden sm:block" />
            operations with powerful
            <br class="hidden sm:block" />
            and secure forms
          </h1>

          <p
            class="mt-4 text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-neutral-500"
          >
            Streamline client onboarding, compliance checks, and data collection
            with OpnForm — the open-source form builder built for financial
            institutions that need flexibility, security, and control.
          </p>
        </div>

        <div class="mt-12 sm:mt-16">
          <h2
            class="text-center text-xl leading-7 tracking-[-0.6%] font-semibold text-neutral-900"
          >
            Key Features for financial institutions
          </h2>
          <div class="mt-6 flex flex-wrap justify-center px-1 gap-4">
            <div
              v-for="feature in keyFeatures"
              :key="feature.label"
              class="flex items-center gap-2.5 w-full sm:w-fit rounded-full bg-white border border-neutral-200/80 py-1.5 pl-1.5 pr-4 text-sm leading-5 tracking-[-0.6%] font-medium text-neutral-900 shadow-sm"
            >
              <div
                class="h-8 w-8 shrink-0 rounded-full flex items-center justify-center"
                :class="feature.iconWrapClass"
              >
                <UIcon
                  :name="feature.icon"
                  class="h-4 w-4"
                  :class="feature.iconClass"
                />
              </div>
              <span class="whitespace-nowrap">
                {{ feature.label }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 py-14 sm:py28 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-lg mx-auto text-center">
          <h2
            class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-neutral-900 tracking-[-1%]"
          >
            How financial teams
            <br class="hidden sm:block" />
            use OpnForm
          </h2>
          <p
            class="mt-4 text-base font-normal text-neutral-500 leading-7 tracking-[-1.1%]"
          >
            Choose how and where you host your forms – without compromising on
            enterprise-grade security.
          </p>
        </div>

        <div
          class="mt-12 sm:mt-16 grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3"
        >
          <div
            v-for="useCase in financialUseCases"
            :key="useCase.title"
            class="rounded-3xl bg-neutral-50 p-8"
          >
            <div
              class="h-16 w-16 rounded-[20px] flex items-center justify-center bg-white shadow-xs"
            >
              <UIcon :name="useCase.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <h3 class="mt-8 text-xl leading-7 font-medium text-neutral-900">
              {{ useCase.title }}
            </h3>
            <p
              class="mt-4 text-base font-normal text-neutral-500 leading-7 tracking-[-1.1%]"
            >
              {{ useCase.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 py-14 sm:28 bg-white">
      <TemplatesSlider class="max-w-266 mx-auto" />
    </section>

    <section class="bg-white">
      <Integrations />
    </section>

    <section class="py-14 sm:py-28 px-8 sm:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-157.25 mx-auto text-center">
          <h2
            class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-neutral-900 tracking-[-1%]"
          >
            How OpnForm improves
            <br />
            financial processes
          </h2>
          <p
            class="mt-4 text-base font-normal text-neutral-500 leading-7 tracking-[-1.1%]"
          >
            Choose how and where you host your forms – without compromising on
            enterprise-
            <br />
            grade security.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-6 md:grid-cols-2">
          <div
            v-for="item in financialImprovements"
            :key="item.title"
            class="rounded-3xl bg-neutral-50 p-8"
          >
            <div class="flex items-start gap-8">
              <div
                class="h-12 w-12 rounded-[12px] flex items-center justify-center bg-white shadow-xs shrink-0"
              >
                <UIcon :name="item.icon" class="h-6 w-6 text-blue-500" />
              </div>

              <div>
                <h3 class="text-xl leading-7 font-medium text-neutral-900">
                  {{ item.title }}
                </h3>
                <p
                  class="mt-4 text-base font-medium text-neutral-500 leading-7 tracking-[-1.1%]"
                >
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
import TemplatesSlider from "~/components/pages/welcome/TemplatesSlider.vue";

definePageMeta({
  layout: "default",
});

useOpnSeoMeta({
  title: "Industry",
  description:
    "Transform financial operations with powerful and secure forms built for teams that need flexibility, security, and control.",
});

const financialUseCases = [
  {
    title: "Client onboarding",
    description:
      "Create digital intake forms to collect KYC data and supporting documents securely.",
    icon: "i-heroicons-identification",
  },
  {
    title: "Compliance and audit requests",
    description:
      "Build internal forms to track approvals, risk assessments, and incident reports.",
    icon: "i-heroicons-clipboard-document-check",
  },
  {
    title: "Loan applications",
    description:
      "Gather borrower information and documentation without manual data entry.",
    icon: "i-heroicons-banknotes",
  },
  {
    title: "Expense and reimbursement submissions",
    description:
      "Simplify financial workflows with automated routing and notifications.",
    icon: "i-heroicons-receipt-refund",
  },
  {
    title: "Customer feedback & surveys",
    description:
      "Collect NPS, satisfaction, or post-service insights while keeping data private.",
    icon: "i-heroicons-chat-bubble-left-right",
  },
  {
    title: "Some title",
    description: "Some text goes here...",
    icon: "i-heroicons-circle-stack",
  },
];

const financialImprovements = [
  {
    title: "Reduces operational overhead",
    description:
      "Replace manual paperwork and email chains with centralized digital workflows.",
    icon: "i-heroicons-document-duplicate",
  },
  {
    title: "Ensures compliance",
    description:
      "Keep sensitive data within your control — self-host or use secure cloud hosting.",
    icon: "i-heroicons-shield-check",
  },
  {
    title: "Accelerates turnaround times",
    description:
      "Automate approvals and instantly route form submissions to the right departments.",
    icon: "i-heroicons-bolt",
  },
  {
    title: "Improves data accuracy",
    description:
      "Validate inputs, standardize formats, and integrate directly with your existing systems.",
    icon: "i-heroicons-check-badge",
  },
  {
    title: "Enhances client experience",
    description:
      "Provide a professional, branded experience that builds trust from the first interaction.",
    icon: "i-heroicons-sparkles",
  },
  {
    title: "Some titles goes here...",
    description: "Some text goes here...",
    icon: "i-heroicons-users",
  },
];

const keyFeatures = [
  {
    label: "Advanced permissions and access rules",
    icon: "i-heroicons-check-badge",
    iconWrapClass: "bg-blue-50 ring-1 ring-blue-100",
    iconClass: "text-blue-600",
  },
  {
    label: "Custom form logic and conditional workflows",
    icon: "i-heroicons-arrows-right-left",
    iconWrapClass: "bg-red-50 ring-1 ring-red-100",
    iconClass: "text-red-600",
  },
  {
    label: "Encrypted data storage and SSL by default",
    icon: "i-heroicons-lock-closed",
    iconWrapClass: "bg-pink-50 ring-1 ring-pink-100",
    iconClass: "text-pink-600",
  },
  {
    label: "Audit trails and submission tracking",
    icon: "i-heroicons-clipboard-document-check",
    iconWrapClass: "bg-violet-50 ring-1 ring-violet-100",
    iconClass: "text-violet-600",
  },
  {
    label: "API & webhook integrations for automation",
    icon: "i-heroicons-command-line",
    iconWrapClass: "bg-sky-50 ring-1 ring-sky-100",
    iconClass: "text-sky-600",
  },
  {
    label: "Custom branding and white-labeling",
    icon: "i-heroicons-swatch",
    iconWrapClass: "bg-orange-50 ring-1 ring-orange-100",
    iconClass: "text-orange-600",
  },
  {
    label: "Option to self-host for full data control",
    icon: "i-heroicons-server-stack",
    iconWrapClass: "bg-fuchsia-50 ring-1 ring-fuchsia-100",
    iconClass: "text-fuchsia-600",
  },
];
</script>
