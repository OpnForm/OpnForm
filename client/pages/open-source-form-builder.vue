<template>
  <div>
    <section class="relative overflow-hidden bg-white">
      <div
        class="pointer-events-none absolute inset-0"
        aria-hidden="true"
      >
        <img
          class="h-full w-full object-cover object-top opacity-[0.16]"
          src="/img/pages/ai_form_builder/background-pattern.svg"
          alt=""
        />
        <div
          class="absolute inset-0 bg-linear-to-b from-white from-30% via-blue-50 via-65% to-white to-90%"
        />
      </div>

      <div class="relative z-2 px-8 py-14 sm:px-12 sm:py-24">
        <div class="mx-auto grid max-w-266 gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
          <div>
            <NuxtLink
              :to="{ name: 'self-hosted-form-builder' }"
              class="inline-flex items-center gap-2 rounded-[10px] border border-gray-200 bg-white px-2.5 py-1 text-sm font-medium text-gray-600 shadow-sm transition-colors hover:border-gray-300 hover:no-underline"
            >
              <UIcon
                name="i-heroicons-server-stack"
                class="h-4 w-4 text-emerald-600"
              />
              <span>Self-hosted deployment</span>
            </NuxtLink>

            <h1
              class="mt-6 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-[56px] sm:leading-16"
            >
              Open source form builder with unlimited submissions
            </h1>

            <p
              class="mt-5 text-lg font-normal leading-7 tracking-[-1.5%] text-gray-600 sm:text-xl sm:leading-8"
            >
              Build forms, surveys, and workflows on an AGPLv3 form builder you
              can inspect, extend, and self-host. Use OpnForm as a free online
              form builder in the cloud, or run it on your own infrastructure
              when you need full ownership.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <UButton
                :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
                size="lg"
                label="Create a free form"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
              <UButton
                :to="opnformConfig.links.github_url"
                target="_blank"
                size="lg"
                variant="outline"
                color="neutral"
                label="View GitHub"
                trailing-icon="i-simple-icons-github"
                class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
            </div>

            <div
              class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-600"
            >
              <span
                v-for="proof in heroProofs"
                :key="proof"
                class="inline-flex items-center gap-2"
              >
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="h-5 w-5 text-emerald-600"
                />
                {{ proof }}
              </span>
            </div>
          </div>

          <div class="mx-auto w-full max-w-md lg:max-w-none lg:mx-0">
            <div
              class="rounded-4xl border border-gray-200 bg-white p-3 shadow-2xl shadow-blue-900/10 sm:p-4"
            >
              <div class="rounded-3xl border border-gray-200 bg-gray-950 p-4 text-white sm:p-5">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-400" />
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-300" />
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400" />
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-medium leading-4 text-gray-300">
                      AGPLv3
                    </span>
                    <span class="text-xs font-medium text-gray-400">opnform/core</span>
                  </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                  <div
                    v-for="item in sourceSignals"
                    :key="item.label"
                    class="rounded-2xl border border-white/10 bg-white/5 p-3.5"
                  >
                    <div class="flex items-center gap-3">
                      <span
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="item.iconWrapClass"
                      >
                        <UIcon
                          :name="item.icon"
                          class="h-4.5 w-4.5"
                          :class="item.iconClass"
                        />
                      </span>
                      <div class="min-w-0">
                        <div class="truncate text-sm font-semibold leading-5 text-white">
                          {{ item.label }}
                        </div>
                        <div class="text-xs font-medium leading-4 text-gray-400">
                          {{ item.description }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-2xl border border-white/10 bg-[#0d1117]">
                  <div class="flex items-center gap-2 border-b border-white/10 px-3 py-2">
                    <UIcon name="i-simple-icons-github" class="h-3.5 w-3.5 text-gray-400" />
                    <span class="text-[11px] font-medium text-gray-400">README.md</span>
                  </div>
                  <div class="space-y-1.5 px-3 py-3 font-mono text-[11px] leading-4 text-gray-400">
                    <p><span class="text-emerald-400">#</span> OpnForm</p>
                    <p class="text-gray-500">Open-source form builder · unlimited submissions</p>
                    <p><span class="text-blue-300">git clone</span> github.com/OpnForm/OpnForm</p>
                  </div>
                </div>
              </div>

              <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div
                  v-for="metric in heroMetrics"
                  :key="metric.label"
                  class="rounded-2xl border border-gray-200 bg-gray-50 px-3 py-3 text-center"
                >
                  <div class="text-lg font-semibold leading-7 tracking-[-0.6%] text-gray-950 sm:text-xl">
                    {{ metric.value }}
                  </div>
                  <div class="text-xs font-medium leading-4 text-gray-500">
                    {{ metric.label }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600">
            Why open source
          </p>
          <h2
            class="my-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
          >
            Forms should not become a black box
          </h2>
          <p class="text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
            Forms collect customer data, applications, payments, support
            requests, and internal workflows. Open-source software gives teams
            a way to inspect how that layer works before they depend on it.
          </p>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="item in openSourceBenefits"
            :key="item.title"
            class="rounded-3xl border border-gray-200 bg-gray-50 p-6"
          >
            <div
              class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm"
            >
              <UIcon :name="item.icon" class="h-6 w-6" :class="item.iconClass" />
            </div>
            <h3 class="mt-6 text-xl font-semibold leading-7 tracking-[-0.6%] text-gray-950">
              {{ item.title }}
            </h3>
            <p class="mt-3 text-sm font-medium leading-6 tracking-[-0.6%] text-gray-600">
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <PillarComparisonTable
      eyebrow="Benchmark"
      title="Open source where closed form builders stop"
      description="Typeform, Jotform, Tally, Fillout, and Formstack are strong products, but they keep the infrastructure closed. OpnForm is built for teams that want modern form building with inspectable code and deployment choice."
      :columns="competitorColumns"
      :rows="competitorRows"
      note="Competitor positioning is based on public product pages and existing OpnForm comparison research. Pricing and plan limits can change."
    />

    <section class="px-8 sm:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600">
            Deployment choice
          </p>
          <h2
            class="my-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
          >
            Start managed, keep the option to own the stack
          </h2>
          <p class="text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
            Open source is most useful when it comes with practical paths for
            real teams: hosted cloud for speed, or self-hosting when ownership
            matters.
          </p>
        </div>

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
          <div
            v-for="option in deploymentOptions"
            :key="option.title"
            class="rounded-3xl border border-gray-200 bg-white p-8 shadow-sm"
            :class="option.highlight ? 'ring-2 ring-blue-500' : ''"
          >
            <div class="flex items-start justify-between gap-4">
              <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl"
                :class="option.iconWrapClass"
              >
                <UIcon :name="option.icon" class="h-7 w-7" :class="option.iconClass" />
              </div>
              <span
                v-if="option.badge"
                class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold leading-4 text-blue-700"
              >
                {{ option.badge }}
              </span>
            </div>
            <h3 class="mt-7 text-xl font-semibold leading-7 tracking-[-0.6%] text-gray-950">
              {{ option.title }}
            </h3>
            <p class="mt-4 text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
              {{ option.description }}
            </p>
            <ul class="mt-6 space-y-3 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-700">
              <li
                v-for="feature in option.features"
                :key="feature"
                class="flex items-center gap-2.5"
              >
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="h-5 w-5 shrink-0 text-emerald-600"
                />
                <span>{{ feature }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 py-14 sm:px-12 sm:py-28 bg-white">
      <div class="mx-auto max-w-266">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-start">
          <div class="lg:col-span-5">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600">
              Content paths
            </p>
            <h2
              class="mt-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
            >
              Looking for an open-source alternative?
            </h2>
            <p class="mt-4 text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
              Use this page when you are evaluating the category. Use the
              comparison pages when you need direct switching guidance for a
              specific tool.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
              <UButton
                :to="opnformConfig.links.github_url"
                target="_blank"
                size="lg"
                label="View on GitHub"
                trailing-icon="i-simple-icons-github"
                class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
              <UButton
                :to="{ name: 'pricing' }"
                size="lg"
                variant="outline"
                color="neutral"
                label="Compare plans"
                class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 lg:col-span-7">
            <NuxtLink
              v-for="link in comparisonLinks"
              :key="link.label"
              :to="link.to"
              class="group rounded-3xl border border-gray-200 bg-gray-50 p-6 transition-colors hover:border-blue-200 hover:bg-blue-50 hover:no-underline"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h3 class="text-lg font-semibold leading-7 tracking-[-0.6%] text-gray-950">
                    {{ link.label }}
                  </h3>
                  <p class="mt-2 text-sm font-medium leading-6 tracking-[-0.6%] text-gray-600">
                    {{ link.description }}
                  </p>
                </div>
                <UIcon
                  name="i-heroicons-arrow-up-right-20-solid"
                  class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-blue-600"
                />
              </div>
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div
          class="grid gap-10 rounded-4xl bg-gray-950 px-8 py-10 shadow-2xl lg:grid-cols-12 lg:items-center lg:px-14 lg:py-14"
        >
          <div class="lg:col-span-7">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-400">
              Self-hosted deployment
            </p>
            <h2
              class="mt-4 text-3xl font-semibold tracking-[-1%] text-white sm:text-5xl sm:leading-14"
            >
              Need full data control on your own infrastructure?
            </h2>
            <p class="mt-4 max-w-2xl text-base font-normal leading-7 tracking-[-1.1%] text-gray-400">
              The self-hosted form builder page covers Docker deployment,
              Enterprise licensing, SSO, audit logs, and the operational
              checklist for running OpnForm yourself.
            </p>
          </div>
          <div class="lg:col-span-5 lg:text-right">
            <UButton
              :to="{ name: 'self-hosted-form-builder' }"
              size="lg"
              label="Explore self-hosting"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
          </div>
        </div>
      </div>
    </section>

    <FaqSection
      :faqs="openSourceFaqs"
      :title-lines="['Open source form builder', 'questions']"
      description="Clear answers for teams comparing OpnForm with closed form builders and deciding between cloud or self-hosting."
      id-prefix="open-source-form-builder-faq-answer"
      @contact="contactUs"
    />

    <section class="bg-white px-8 pb-14 sm:px-12 sm:pb-28">
      <div
        class="mx-auto max-w-336 overflow-hidden rounded-4xl bg-gray-950 px-8 py-10 shadow-2xl sm:px-10 lg:px-14 lg:py-14"
      >
        <div class="grid gap-8 xl:grid-cols-12 xl:items-center">
          <div class="xl:col-span-8">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-400">
              Open source forms
            </p>
            <h2
              class="mt-4 text-3xl font-semibold tracking-[-1%] text-white sm:text-5xl sm:leading-14"
            >
              Build unlimited forms without locking your data into a black box.
            </h2>
            <p class="mt-4 max-w-2xl text-base font-normal leading-7 tracking-[-1.1%] text-gray-400 sm:text-lg">
              Start on OpnForm Cloud, inspect the code on GitHub, and move to
              self-hosting when your policies or customers require it.
            </p>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row xl:col-span-4 xl:justify-end">
            <UButton
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              size="lg"
              label="Create a free form"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
            <UButton
              :to="{ name: 'self-hosted-form-builder' }"
              size="lg"
              variant="outline"
              color="neutral"
              label="Explore self-hosting"
              class="w-fit rounded-[12px] bg-white px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%] text-gray-950 hover:bg-white/95"
            />
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter :show-cta="false" />
  </div>
</template>

<script setup>
import FaqSection from "~/components/pages/FaqSection.vue"
import PillarComparisonTable from "~/components/pages/pillars/PillarComparisonTable.vue"
import { useIsAuthenticated } from "~/composables/useAuthFlow"
import opnformConfig from "~/opnform.config.js"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "Open Source Form Builder",
  description:
    "Build forms with an open-source online form builder. OpnForm gives unlimited submissions, cloud or self-hosted deployment, API, webhooks, and GDPR-friendly hosting.",
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const heroProofs = [
  "AGPLv3 core",
  "Unlimited submissions",
  "Cloud or self-hosted",
  "API and webhooks",
]

const sourceSignals = [
  {
    icon: "i-simple-icons-github",
    iconWrapClass: "bg-white/10",
    iconClass: "text-white",
    label: "Public repository",
    description: "Inspect the code before you trust it.",
  },
  {
    icon: "i-heroicons-scale",
    iconWrapClass: "bg-blue-400/15",
    iconClass: "text-blue-300",
    label: "AGPLv3 core",
    description: "Open-source with Enterprise separated.",
  },
  {
    icon: "i-heroicons-server-stack",
    iconWrapClass: "bg-emerald-400/15",
    iconClass: "text-emerald-300",
    label: "Self-host ready",
    description: "Deploy when ownership matters.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    iconWrapClass: "bg-violet-400/15",
    iconClass: "text-violet-300",
    label: "API and webhooks",
    description: "Connect forms to your stack.",
  },
]

const heroMetrics = [
  { value: "3k+", label: "GitHub stars" },
  { value: "10k+", label: "companies" },
  { value: "Free", label: "cloud plan" },
  { value: "No caps", label: "submissions" },
]

const openSourceBenefits = [
  {
    icon: "i-heroicons-code-bracket-square",
    iconClass: "text-blue-600",
    title: "Auditable by default",
    description:
      "Review how the product works instead of relying only on vendor promises.",
  },
  {
    icon: "i-heroicons-arrows-pointing-out",
    iconClass: "text-emerald-600",
    title: "No response ceiling",
    description:
      "Use OpnForm as a free form builder without upgrading every time a form performs well.",
  },
  {
    icon: "i-heroicons-lock-closed",
    iconClass: "text-violet-600",
    title: "Data ownership paths",
    description:
      "Choose managed cloud for speed or self-hosted infrastructure for direct control.",
  },
  {
    icon: "i-heroicons-puzzle-piece",
    iconClass: "text-orange-600",
    title: "Extensible workflows",
    description:
      "Use API access, webhooks, integrations, and custom deployment patterns.",
  },
]

const competitorColumns = [
  {
    label: "OpnForm",
    detail: "Open-source",
    logo: "/img/logo.svg",
    highlight: true,
  },
  { label: "Typeform", detail: "Closed SaaS" },
  { label: "Jotform", detail: "Closed SaaS" },
  { label: "Tally", detail: "Closed SaaS" },
  { label: "Fillout", detail: "Closed SaaS" },
  { label: "Formstack", detail: "Closed SaaS" },
]

const competitorRows = [
  {
    label: "Source code",
    values: ["AGPLv3 core", "Closed", "Closed", "Closed", "Closed", "Closed"],
  },
  {
    label: "Self-hosting",
    values: ["Community + Enterprise", "No", "No", "No", "No", "No"],
  },
  {
    label: "Free submissions",
    values: ["Unlimited", "Capped", "Capped", "Unlimited", "Capped", "Limited"],
  },
  {
    label: "Developer workflows",
    values: [
      "API + webhooks",
      "API + integrations",
      "API + webhooks",
      "Webhooks",
      "API + integrations",
      "Enterprise workflows",
    ],
  },
  {
    label: "Data location choice",
    values: [
      "Cloud or self-hosted",
      "Vendor cloud",
      "Vendor cloud",
      "Vendor cloud",
      "Vendor cloud",
      "Vendor cloud",
    ],
  },
  {
    label: "Best fit",
    values: [
      "Teams needing control",
      "Conversational surveys",
      "Large template library",
      "Simple free forms",
      "Database-connected forms",
      "Enterprise workflows",
    ],
  },
]

const deploymentOptions = [
  {
    icon: "i-heroicons-cloud",
    iconWrapClass: "bg-blue-50",
    iconClass: "text-blue-600",
    title: "OpnForm Cloud",
    badge: "Fastest start",
    highlight: true,
    description:
      "The fastest way to launch forms with managed hosting, updates, support, and unlimited submissions.",
    features: ["No infrastructure work", "Free plan available", "Managed updates"],
  },
  {
    icon: "i-heroicons-server-stack",
    iconWrapClass: "bg-violet-50",
    iconClass: "text-violet-600",
    title: "Self-hosted",
    description:
      "Run OpnForm on your own infrastructure when policy, procurement, or customer requirements demand data control.",
    features: ["API and webhooks", "SSO and audit logs", "GDPR-friendly ownership"],
  },
]

const comparisonLinks = [
  {
    label: "Open-source Typeform alternative",
    description:
      "Compare OpnForm with Typeform when conversational forms and response caps are the question.",
    to: { name: "opnform-vs-typeform" },
  },
  {
    label: "Open-source Jotform alternative",
    description:
      "See how OpnForm compares with Jotform on limits, control, and developer workflows.",
    to: { name: "opnform-vs-jotform" },
  },
  {
    label: "Open-source Google Forms alternative",
    description:
      "Move beyond basic forms with unlimited submissions, stronger workflows, and optional self-hosting.",
    to: { name: "opnform-vs-googleforms" },
  },
  {
    label: "Open-source Tally alternative",
    description:
      "Evaluate Tally's free form experience against OpnForm's open infrastructure.",
    to: { name: "opnform-vs-tally" },
  },
  {
    label: "Open-source Fillout alternative",
    description:
      "Compare modern form building, database workflows, and self-hosting options.",
    to: { name: "opnform-vs-fillout" },
  },
]

const openSourceFaqs = [
  {
    question: "Is OpnForm really open source?",
    answer:
      "Yes. The core OpnForm project is open source under AGPLv3 and available on GitHub. Enterprise-only features are licensed separately so the open-source project can remain sustainable.",
  },
  {
    question: "Can I use OpnForm for free?",
    answer:
      "Yes. OpnForm Cloud has a free plan with unlimited submissions, and the self-hosted core product can be run for free. Paid plans add branding, advanced team controls, Enterprise support, and other business features.",
  },
  {
    question: "Is OpnForm an open-source Typeform alternative?",
    answer:
      "Yes. OpnForm can replace Typeform for many form, survey, lead capture, registration, and workflow use cases, especially when unlimited submissions, API access, and deployment control matter.",
  },
  {
    question: "Is OpnForm a Google Forms alternative?",
    answer:
      "Yes. OpnForm can replace Google Forms when you need a more flexible online form builder with unlimited submissions, custom workflows, API access, webhooks, and the option to self-host.",
  },
  {
    question: "What is the difference between open source and self-hosted?",
    answer:
      "Open source means the core product code is publicly available to inspect and modify. Self-hosted means you run the application on your own infrastructure. OpnForm supports both, but you can also use the managed cloud product.",
  },
  {
    question: "What features matter in a self-hosted form builder?",
    answer:
      "Teams usually look for API access, webhooks, SSO, roles and permissions, audit logs, Docker deployment, backups, data residency control, and a GDPR-friendly operating model. OpnForm supports the core form builder for free and adds Enterprise controls when rollout requirements grow.",
  },
  {
    question: "Do non-technical teams need to self-host OpnForm?",
    answer:
      "No. Most teams should start with OpnForm Cloud because it removes infrastructure work. Self-hosting is best for teams with specific compliance, procurement, or infrastructure requirements.",
  },
  {
    question: "Is OpnForm GDPR-friendly?",
    answer:
      "OpnForm gives teams GDPR-friendly deployment choices, including managed cloud and self-hosting for direct infrastructure control. Your legal and data processing requirements still depend on your own setup and use case.",
  },
]

useJsonLd("open-source-form-builder-schema", buildSchemaGraph([
  buildOrganizationSchema(),
  buildWebsiteSchema(),
  buildSoftwareApplicationSchema(),
  buildWebPageSchema({
    name: "Open Source Form Builder",
    description:
      "Build forms with an open-source online form builder. OpnForm gives unlimited submissions, cloud or self-hosted deployment, API, webhooks, and GDPR-friendly hosting.",
    path: "/open-source-form-builder",
  }),
  buildBreadcrumbSchema([
    { name: "Home", path: "/" },
    { name: "Open Source Form Builder", path: "/open-source-form-builder" },
  ]),
  buildFaqSchema(openSourceFaqs),
]))

const contactUs = () => {
  useCrisp().openAndShowChat()
}
</script>
