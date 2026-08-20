<template>
  <div>
    <section class="relative overflow-hidden bg-white">
      <div
        class="pointer-events-none absolute inset-0"
        aria-hidden="true"
      >
        <img
          class="h-full w-full object-cover object-top opacity-[0.14]"
          src="/img/pages/ai_form_builder/background-pattern.svg"
          alt=""
        />
        <div
          class="absolute inset-0 bg-linear-to-b from-white from-30% via-blue-50 via-62% to-white to-88%"
        />
      </div>

      <div class="relative z-2 px-8 py-14 sm:px-12 sm:py-24">
        <div class="mx-auto grid max-w-266 gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
          <div>
            <NuxtLink
              :to="{ name: 'open-source-form-builder' }"
              class="inline-flex items-center gap-2 rounded-[10px] border border-gray-200 bg-white px-2.5 py-1 text-sm font-medium text-gray-600 shadow-sm transition-colors hover:border-gray-300 hover:no-underline"
            >
              <UIcon
                name="i-simple-icons-github"
                class="h-4 w-4 text-gray-900"
              />
              <span>Open-source core, Enterprise path</span>
            </NuxtLink>

            <h1
              class="mt-6 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-[56px] sm:leading-16"
            >
              Self-hosted form builder for teams that need data control
            </h1>

            <p
              class="mt-5 text-lg font-normal leading-7 tracking-[-1.5%] text-gray-600 sm:text-xl sm:leading-8"
            >
              Deploy OpnForm on your own infrastructure when forms need to live
              inside your security, procurement, or data residency model. Use a
              secure form builder your team can inspect, operate, and upgrade
              with Enterprise controls when the rollout grows.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <UButton
                :to="opnformConfig.links.self_hosting"
                target="_blank"
                size="lg"
                label="View deployment docs"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
              <UButton
                :to="opnformConfig.links.book_onboarding"
                target="_blank"
                size="lg"
                variant="outline"
                color="neutral"
                label="Talk to us"
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
                      Docker
                    </span>
                    <span class="text-xs font-medium text-gray-400">deployment plan</span>
                  </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                  <div
                    v-for="node in architectureNodes"
                    :key="node.title"
                    class="rounded-2xl border border-white/10 bg-white/5 p-3.5"
                  >
                    <div class="flex items-center gap-3">
                      <span
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="node.iconWrapClass"
                      >
                        <UIcon
                          :name="node.icon"
                          class="h-4.5 w-4.5"
                          :class="node.iconClass"
                        />
                      </span>
                      <div class="min-w-0">
                        <div class="truncate text-sm font-semibold leading-5 text-white">
                          {{ node.title }}
                        </div>
                        <div class="text-xs font-medium leading-4 text-gray-400">
                          {{ node.description }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-2xl border border-white/10 bg-[#0d1117]">
                  <div class="flex items-center gap-2 border-b border-white/10 px-3 py-2">
                    <UIcon name="i-simple-icons-docker" class="h-3.5 w-3.5 text-gray-400" />
                    <span class="text-[11px] font-medium text-gray-400">docker-compose.yml</span>
                  </div>
                  <div class="space-y-1.5 px-3 py-3 font-mono text-[11px] leading-4 text-gray-400">
                    <p><span class="text-emerald-400">services:</span></p>
                    <p class="pl-2"><span class="text-blue-300">opnform:</span> image: opnform/opnform</p>
                    <p class="text-gray-500">$ docker compose up -d</p>
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
            When self-hosting makes sense
          </p>
          <h2
            class="my-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
          >
            Use your infrastructure when the form layer is critical
          </h2>
          <p class="text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
            The best self-hosted form builder should be simple for business
            teams and explicit enough for IT, security, and procurement teams
            to review.
          </p>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="item in selfHostedReasons"
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
      eyebrow="Hosting model"
      title="Choose the level of control you actually need"
      description="Self-hosting is not the only privacy path. OpnForm lets teams start with managed cloud or run the product themselves when policy requires it."
      label-column-title="Decision point"
      :columns="hostingColumns"
      :rows="hostingRows"
    />

    <section class="px-8 sm:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-start">
          <div class="lg:col-span-5">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600">
              Secure form builder controls
            </p>
            <h2
              class="mt-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
            >
              Secure self-hosted forms should still connect to your stack
            </h2>
            <p class="mt-4 text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
              Teams usually choose self-hosting for data control, but the
              deployment also needs API access, webhooks, identity controls, and
              a clear GDPR review path.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
              <UButton
                :to="opnformConfig.links.api_docs"
                target="_blank"
                size="lg"
                label="View API docs"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
              <UButton
                :to="{ name: 'enterprise' }"
                size="lg"
                variant="outline"
                color="neutral"
                label="Enterprise security"
                class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 lg:col-span-7">
            <div
              v-for="feature in selfHostedCapabilities"
              :key="feature.title"
              class="rounded-3xl border border-gray-200 bg-gray-50 p-6"
            >
              <div class="flex items-start gap-4">
                <span
                  class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm"
                >
                  <UIcon :name="feature.icon" class="h-6 w-6" :class="feature.iconClass" />
                </span>
                <div>
                  <h3 class="text-lg font-semibold leading-7 tracking-[-0.6%] text-gray-950">
                    {{ feature.title }}
                  </h3>
                  <p class="mt-2 text-sm font-medium leading-6 tracking-[-0.6%] text-gray-600">
                    {{ feature.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 py-14 sm:px-12 sm:py-28 bg-white">
      <div class="mx-auto max-w-266">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-start">
          <div class="lg:col-span-5">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600">
              Deployment checklist
            </p>
            <h2
              class="mt-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
            >
              Plan the operational pieces before launch
            </h2>
            <p class="mt-4 text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
              A self-hosted form builder gives control, but your team owns the
              runtime, updates, backups, and data handling. Use this checklist
              to make that tradeoff before opening the deployment docs.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
              <UButton
                :to="opnformConfig.links.self_hosting"
                target="_blank"
                size="lg"
                label="Open Docker docs"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
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
            <div
              v-for="item in deploymentChecklist"
              :key="item.title"
              class="rounded-3xl border border-gray-200 bg-gray-50 p-6"
            >
              <div class="flex items-center gap-3">
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm"
                >
                  <UIcon :name="item.icon" class="h-5 w-5 text-blue-600" />
                </span>
                <h3 class="text-lg font-semibold leading-7 tracking-[-0.6%] text-gray-950">
                  {{ item.title }}
                </h3>
              </div>
              <p class="mt-4 text-sm font-medium leading-6 tracking-[-0.6%] text-gray-600">
                {{ item.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600">
            Community vs Enterprise
          </p>
          <h2
            class="my-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
          >
            Start free, upgrade when governance becomes the job
          </h2>
          <p class="text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
            The free self-hosted core is enough for many technical teams.
            Enterprise adds the controls and support organizations usually need
            when OpnForm becomes shared infrastructure.
          </p>
        </div>

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
          <div
            v-for="edition in editions"
            :key="edition.title"
            class="rounded-3xl border border-gray-200 bg-white p-8 shadow-sm"
            :class="edition.highlight ? 'ring-2 ring-blue-500' : ''"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-2xl font-semibold leading-8 tracking-[-0.6%] text-gray-950">
                  {{ edition.title }}
                </h3>
                <p class="mt-3 text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
                  {{ edition.description }}
                </p>
              </div>
              <span
                v-if="edition.badge"
                class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold leading-4 text-blue-700"
              >
                {{ edition.badge }}
              </span>
            </div>

            <div class="mt-7 text-3xl font-semibold leading-10 tracking-[-1%] text-gray-950">
              {{ edition.price }}
            </div>

            <ul class="mt-7 space-y-3 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-700">
              <li
                v-for="feature in edition.features"
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

            <div class="mt-8">
              <UButton
                v-bind="edition.cta"
                size="lg"
                class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 py-14 sm:px-12 sm:py-28 bg-white">
      <div class="mx-auto max-w-266">
        <div
          class="grid gap-10 rounded-4xl bg-gray-950 px-8 py-10 shadow-2xl lg:grid-cols-12 lg:items-center lg:px-14 lg:py-14"
        >
          <div class="lg:col-span-7">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-400">
              Open source category
            </p>
            <h2
              class="mt-4 text-3xl font-semibold tracking-[-1%] text-white sm:text-5xl sm:leading-14"
            >
              Need the category view before the deployment docs?
            </h2>
            <p class="mt-4 max-w-2xl text-base font-normal leading-7 tracking-[-1.1%] text-gray-400">
              The open-source form builder page explains how OpnForm compares
              with closed SaaS builders like Typeform, Jotform, Tally, Fillout,
              and Formstack.
            </p>
          </div>
          <div class="lg:col-span-5 lg:text-right">
            <UButton
              :to="{ name: 'open-source-form-builder' }"
              size="lg"
              label="Explore open-source forms"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
          </div>
        </div>
      </div>
    </section>

    <FaqSection
      :faqs="selfHostedFaqs"
      :title-lines="['Self-hosted form builder', 'questions']"
      description="Answers for teams evaluating OpnForm self-hosting, Community Edition, Enterprise licensing, and managed alternatives."
      id-prefix="self-hosted-form-builder-faq-answer"
      @contact="contactUs"
    />

    <section class="bg-white px-8 pb-14 sm:px-12 sm:pb-28">
      <div
        class="mx-auto max-w-336 overflow-hidden rounded-4xl bg-gray-950 px-8 py-10 shadow-2xl sm:px-10 lg:px-14 lg:py-14"
      >
        <div class="grid gap-8 lg:grid-cols-12 lg:items-center">
          <div class="lg:col-span-8">
            <p class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-400">
              Self-hosted OpnForm
            </p>
            <h2
              class="mt-4 text-3xl font-semibold tracking-[-1%] text-white sm:text-5xl sm:leading-14"
            >
              Run forms on your infrastructure when the policy calls for it.
            </h2>
            <p class="mt-4 max-w-2xl text-base font-normal leading-7 tracking-[-1.1%] text-gray-400 sm:text-lg">
              Start with the Docker deployment docs, or talk with us about
              Enterprise support, SSO, audit logs, and rollout planning.
            </p>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row lg:col-span-4 lg:justify-end">
            <UButton
              :to="opnformConfig.links.self_hosting"
              target="_blank"
              size="lg"
              label="View docs"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
            <UButton
              :to="opnformConfig.links.book_onboarding"
              target="_blank"
              size="lg"
              variant="outline"
              color="neutral"
              label="Book a demo"
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
import opnformConfig from "~/opnform.config.js"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "Self-Hosted Form Builder",
  description:
    "Deploy a secure self-hosted form builder with API access, webhooks, SSO, audit logs, GDPR-friendly data control, unlimited submissions, and Enterprise support.",
})

const heroProofs = [
  "Docker deployment",
  "API and webhooks",
  "SSO and audit logs",
  "GDPR-friendly control",
]

const heroMetrics = [
  { value: "Docker", label: "quick start" },
  { value: "Free", label: "OSS core" },
  { value: "SSO", label: "Enterprise" },
  { value: "100%", label: "data control" },
]

const architectureNodes = [
  {
    icon: "i-heroicons-cube-transparent",
    iconWrapClass: "bg-blue-400/15",
    iconClass: "text-blue-300",
    title: "Application",
    description: "Run OpnForm on your stack.",
  },
  {
    icon: "i-heroicons-circle-stack",
    iconWrapClass: "bg-emerald-400/15",
    iconClass: "text-emerald-300",
    title: "Database",
    description: "Own storage, backups, access.",
  },
  {
    icon: "i-heroicons-envelope",
    iconWrapClass: "bg-orange-400/15",
    iconClass: "text-orange-300",
    title: "SMTP",
    description: "Use your mail provider.",
  },
  {
    icon: "i-heroicons-lock-closed",
    iconWrapClass: "bg-violet-400/15",
    iconClass: "text-violet-300",
    title: "Identity",
    description: "Add OIDC or Enterprise SSO.",
  },
]

const selfHostedReasons = [
  {
    icon: "i-heroicons-building-library",
    iconClass: "text-blue-600",
    title: "Procurement requirements",
    description:
      "Use self-hosting when vendors need to pass strict internal hosting or ownership policies.",
  },
  {
    icon: "i-heroicons-lock-closed",
    iconClass: "text-violet-600",
    title: "Sensitive submissions",
    description:
      "Keep intake, HR, customer, or application data inside infrastructure your team controls.",
  },
  {
    icon: "i-heroicons-globe-europe-africa",
    iconClass: "text-emerald-600",
    title: "Data residency",
    description:
      "Choose the exact region and architecture instead of depending on a vendor-only cloud.",
  },
  {
    icon: "i-heroicons-wrench-screwdriver",
    iconClass: "text-orange-600",
    title: "Custom operations",
    description:
      "Integrate OpnForm with your deployment, observability, backup, and incident response process.",
  },
]

const hostingColumns = [
  {
    label: "Cloud",
    detail: "Managed",
    icon: "i-heroicons-cloud",
  },
  {
    label: "Community",
    detail: "Self-hosted",
    icon: "i-simple-icons-github",
    highlight: true,
  },
  {
    label: "Enterprise",
    detail: "Self-hosted",
    icon: "i-heroicons-shield-check",
  },
]

const hostingRows = [
  {
    label: "Setup speed",
    values: ["Fastest", "Technical setup", "Technical setup + support"],
  },
  {
    label: "Infrastructure owner",
    values: ["OpnForm", "Your team", "Your team"],
  },
  {
    label: "Data location",
    values: ["Managed cloud", "Your infrastructure", "Your infrastructure"],
  },
  {
    label: "User governance",
    values: ["Plan-based", "Up to 2 users", "Roles and permissions"],
  },
  {
    label: "API and webhooks",
    values: ["Available", "Available", "Available + support"],
  },
  {
    label: "SSO",
    values: ["Plan-based", "OIDC within user limit", "Enterprise identity controls"],
  },
  {
    label: "Audit and compliance",
    values: ["Managed controls", "Your operational process", "Audit logs + compliance features"],
  },
  {
    label: "Branding",
    values: ["Plan-based", "Branding required", "Branding removal"],
  },
  {
    label: "Support model",
    values: ["Managed support", "Community", "Priority support"],
  },
  {
    label: "Best for",
    values: [
      "Most teams",
      "Technical teams starting free",
      "Organizations rolling out at scale",
    ],
  },
]

const selfHostedCapabilities = [
  {
    icon: "i-heroicons-code-bracket-square",
    iconClass: "text-blue-600",
    title: "API access",
    description:
      "Create forms, retrieve submissions, and connect OpnForm to internal services without routing every workflow through a third-party SaaS.",
  },
  {
    icon: "material-symbols:webhook",
    iconClass: "text-emerald-600",
    title: "Webhooks",
    description:
      "Trigger downstream systems from new submissions, approvals, or intake flows while keeping the form database inside your environment.",
  },
  {
    icon: "i-heroicons-finger-print",
    iconClass: "text-violet-600",
    title: "SSO and access control",
    description:
      "Use OIDC in Community deployments within the user limit, then move to Enterprise for larger teams, roles, permissions, and identity governance.",
  },
  {
    icon: "i-heroicons-shield-check",
    iconClass: "text-orange-600",
    title: "Secure forms and GDPR",
    description:
      "Choose where data is stored, who can access it, how backups work, and how retention fits your legal and security policies.",
  },
]

const deploymentChecklist = [
  {
    icon: "i-heroicons-cube",
    title: "Runtime",
    description:
      "Decide where Docker containers run and who owns uptime, monitoring, and incident response.",
  },
  {
    icon: "i-heroicons-key",
    title: "Environment variables",
    description:
      "Treat app keys, database credentials, OAuth settings, and mail credentials as production secrets.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Database and backups",
    description:
      "Plan database durability, backup cadence, restore testing, and retention before collecting data.",
  },
  {
    icon: "i-heroicons-envelope",
    title: "Email delivery",
    description:
      "Configure SMTP for form notifications, verification messages, and workspace email flows.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Domains and SSL",
    description:
      "Use the domains, certificates, and network rules your organization already trusts.",
  },
  {
    icon: "i-heroicons-arrow-path",
    title: "Updates",
    description:
      "Decide who applies patches and how changes move from staging to production.",
  },
]

const editions = [
  {
    title: "Community Edition",
    description:
      "For developers and small teams that want to run the core product themselves.",
    price: "Free OSS",
    features: [
      "Unlimited forms and submissions",
      "File uploads, logic, computed fields",
      "Pre-fills and URL parameters",
      "Up to 2 users across the instance",
      "OIDC SSO within the 2-user limit",
      "Branding required",
      "Community support",
    ],
    cta: {
      label: "View docs",
      to: opnformConfig.links.self_hosting,
      target: "_blank",
      trailingIcon: "i-heroicons-arrow-up-right-20-solid",
      variant: "outline",
      color: "neutral",
    },
  },
  {
    title: "Enterprise License",
    description:
      "For organizations that need identity, governance, branding control, and deployment support.",
    price: "$1,999 / year",
    badge: "Scale path",
    highlight: true,
    features: [
      "Branding removal",
      "Custom SMTP",
      "Multi-workspace support",
      "Multi-user with roles and permissions",
      "SSO, audit logs, compliance features",
      "White-labeling and theming",
      "Packaged updates and migration tooling",
      "Priority support",
    ],
    cta: {
      label: "Buy license",
      to: { name: "self-hosted-license" },
      trailingIcon: "i-heroicons-arrow-up-right-20-solid",
    },
  },
]

const selfHostedFaqs = [
  {
    question: "Can I self-host OpnForm?",
    answer:
      "Yes. OpnForm can be deployed on your own infrastructure using the self-hosting documentation. The core product is open source, and Enterprise support is available when you need governance and rollout help.",
  },
  {
    question: "What is included in the free self-hosted Community Edition?",
    answer:
      "Community Edition includes the core product, unlimited forms and submissions, form logic, computed fields, pre-fills, basic workspace management, and community support. It is limited to 2 users across the instance.",
  },
  {
    question: "When do I need a self-hosted Enterprise license?",
    answer:
      "Use Enterprise when you need more users, roles and permissions, branding removal, custom SMTP, SSO, audit logs, white-labeling, packaged updates, migration tooling, or priority support.",
  },
  {
    question: "Can a self-hosted OpnForm deployment use API access and webhooks?",
    answer:
      "Yes. OpnForm supports API access and webhooks so teams can connect forms to internal tools, CRMs, ticketing systems, data pipelines, and custom workflows from a self-hosted deployment.",
  },
  {
    question: "Does self-hosted OpnForm support SSO?",
    answer:
      "Community deployments include OIDC SSO within the 2-user limit. Enterprise is the better fit when you need broader identity controls, more users, roles, permissions, and audit visibility.",
  },
  {
    question: "Is OpnForm a secure form builder for sensitive data?",
    answer:
      "OpnForm is built for teams that need secure forms and clearer data control. Self-hosting lets your team choose the infrastructure, network rules, backups, access controls, and retention policies used for sensitive submissions.",
  },
  {
    question: "Is there a Docker deployment?",
    answer:
      "Yes. OpnForm provides Docker deployment documentation covering quick start, architecture, images, and environment variables for self-hosted installs.",
  },
  {
    question: "How is self-hosting different from OpnForm Cloud?",
    answer:
      "OpnForm Cloud is managed by OpnForm, so your team does not operate the application, database, updates, or backups. Self-hosting means your team runs those pieces on your own infrastructure.",
  },
  {
    question: "Does self-hosting make OpnForm GDPR compliant?",
    answer:
      "Self-hosting can help with GDPR-friendly data control because you choose infrastructure, region, access, and retention. Compliance still depends on your legal basis, configuration, subprocessors, and internal policies.",
  },
]

useJsonLd("self-hosted-form-builder-schema", buildSchemaGraph([
  buildOrganizationSchema(),
  buildWebsiteSchema(),
  buildSoftwareApplicationSchema(),
  buildWebPageSchema({
    name: "Self-Hosted Form Builder",
    description:
      "Deploy a secure self-hosted form builder with API access, webhooks, SSO, audit logs, GDPR-friendly data control, unlimited submissions, and Enterprise support.",
    path: "/self-hosted-form-builder",
  }),
  buildBreadcrumbSchema([
    { name: "Home", path: "/" },
    { name: "Self-Hosted Form Builder", path: "/self-hosted-form-builder" },
  ]),
  buildFaqSchema(selfHostedFaqs),
]))

const contactUs = () => {
  useCrisp().openAndShowChat()
}
</script>
