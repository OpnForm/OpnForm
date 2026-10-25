<template>
  <div>
    <section class="relative">
      <div class="py-14 sm:py-28 px-8 sm:px-12 relative z-2">
        <div class="max-w-3xl mx-auto text-center">
          <h1
            class="text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950"
          >
            Secure forms for teams that need control
          </h1>
          <p
            class="mt-4 text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600"
          >
            Create forms, surveys, and internal workflows without giving up
            control of access, infrastructure, or submission data. Use OpnForm
            Cloud, hosting in France, or self-hosted deployment.
          </p>

          <div
            class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <UButton
              :to="opnformConfig.links.book_onboarding"
              target="_blank"
              label="Book a Demo"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              size="lg"
              class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
            <UButton
              :to="opnformConfig.links.self_hosting"
              target="_blank"
              label="Explore Self-Hosting"
              variant="outline"
              size="lg"
              class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
          </div>

          <div class="mt-12 sm:mt-16">
            <div
              class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600"
            >
              Trusted by teams that value transparency, flexibility, and
              security.
            </div>
            <div class="mt-6 flex items-center justify-center gap-12 flex-wrap">
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/vanguard-1.png"
                  alt="Vanguard logo"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/logo-berkeley-2.png"
                  alt="UC Berkeley logo"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/UN_emblem_blue-2.png"
                  alt="United Nations logo"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/Frame-47.png"
                  alt="American Red Cross logo"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/Zomato_Logo-2.png"
                  alt="Zomato logo"
                  class="w-full"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="mt-12 sm:mt-16 max-w-266 mx-auto">
          <div class="grid gap-4 sm:grid-cols-3">
            <div
              v-for="item in controlHighlights"
              :key="item.title"
              class="rounded-3xl bg-white border border-gray-200 p-6 shadow-sm"
            >
              <div
                class="h-12 w-12 rounded-[16px] flex items-center justify-center"
                :class="item.iconWrapClass"
              >
                <UIcon
                  :name="item.icon"
                  class="h-6 w-6"
                  :class="item.iconClass"
                />
              </div>
              <div
                class="mt-6 text-xl leading-7 tracking-[-0.6%] font-semibold text-gray-950"
              >
                {{ item.title }}
              </div>
              <p
                class="mt-3 text-sm leading-6 tracking-[-0.6%] font-medium text-gray-600"
              >
                {{ item.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
      <div
        class="pointer-events-none w-full h-full bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85% absolute inset-0"
      ></div>
    </section>

    <section class="py-14 sm:py-28 px-8 sm:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-lg mx-auto text-center">
          <div
            class="text-base leading-7 tracking-[-1.1%] font-medium text-blue-600"
          >
            Security &amp; Compliance
          </div>

          <h2
            class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
          >
            Your Data,
            <br />
            Your Infrastructure,
            <br />
            Your Rules
          </h2>

          <p
            class="text-base leading-7 tracking-[-1.1%] font-normal text-gray-600"
          >
            Choose how and where you host your forms — without compromising on
            enterprise-grade security.
          </p>
        </div>

        <div
          class="my-12 sm:my-16 flex flex-wrap items-center justify-center gap-4"
        >
          <div
            class="inline-flex items-center gap-2 rounded-full bg-white pl-1.5 pr-4 py-1.5 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950 shadow-sm border border-gray-200"
          >
            <span
              class="h-8 w-8 rounded-full bg-purple-50 flex items-center justify-center"
            >
              <UIcon
                name="i-heroicons-lock-closed"
                class="h-5.5 w-5.5 text-purple-600"
              />
            </span>
            <span>Single Sign-On (SSO) / SAML</span>
          </div>

          <div
            class="inline-flex items-center gap-2 rounded-full bg-white pl-1.5 pr-4 py-1.5 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950 shadow-sm border border-gray-200"
          >
            <span
              class="h-8 w-8 rounded-full bg-red-50 flex items-center justify-center"
            >
              <UIcon
                name="i-heroicons-shield-check"
                class="h-5.5 w-5.5 text-red-600"
              />
            </span>
            <span>GDPR</span>
          </div>

          <div
            class="inline-flex items-center gap-2 rounded-full bg-white pl-1.5 pr-4 py-1.5 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950 shadow-sm border border-gray-200"
          >
            <span
              class="h-8 w-8 rounded-full bg-blue-50 flex items-center justify-center"
            >
              <UIcon
                name="i-heroicons-globe-europe-africa"
                class="h-5.5 w-5.5 text-blue-500"
              />
            </span>
            <span>Hosted in France</span>
          </div>
        </div>

        <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="item in securityFeatures"
            :key="item.title"
            class="rounded-3xl bg-gray-50 border border-gray-200 p-8"
          >
            <div
              class="h-16 w-16 rounded-[20px] bg-white shadow-sm flex items-center justify-center"
            >
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div class="mt-8 text-xl leading-7 font-medium text-gray-950">
              {{ item.title }}
            </div>
            <div
              class="mt-4 text-base tracking-[-1.1%] font-medium leading-7 text-gray-600"
            >
              {{ item.description }}
            </div>
          </div>
        </div>

        <div class="mt-12 sm:mt-16 flex justify-center">
          <UButton
            :to="opnformConfig.links.tech_docs"
            target="_blank"
            variant="outline"
            label="Learn About OpnForm Security"
            trailing-icon="i-heroicons-arrow-right-20-solid"
            size="lg"
            class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </section>

    <section class="py-14 sm:py-28 px-8 sm:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <div
            class="text-base leading-7 tracking-[-1.1%] font-medium text-blue-600"
          >
            Deployment Options
          </div>

          <h2
            class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
          >
            Choose where your
            <br class="hidden sm:block" />
            form data lives
          </h2>

          <p
            class="text-base leading-7 tracking-[-1.1%] font-normal text-gray-600"
          >
            Start with managed cloud, keep hosted data in France, or run
            OpnForm on your own infrastructure when policy requires it.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-6 lg:grid-cols-3">
          <div
            v-for="option in deploymentOptions"
            :key="option.title"
            class="rounded-3xl bg-gray-50 border border-gray-200 p-8 shadow-sm"
          >
            <div
              class="h-16 w-16 rounded-[20px] bg-white shadow-sm flex items-center justify-center"
            >
              <UIcon :name="option.icon" class="h-8 w-8 text-blue-600" />
            </div>
            <h3
              class="mt-8 text-xl leading-7 tracking-[-0.6%] font-semibold text-gray-950"
            >
              {{ option.title }}
            </h3>
            <p
              class="mt-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
            >
              {{ option.description }}
            </p>
            <ul
              class="mt-6 space-y-3 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-700"
            >
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

    <section class="py-14 sm:py-28 px-8 sm:px-12 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="text-center">
          <div
            class="text-base leading-7 tracking-[-1.1%] font-medium text-blue-600"
          >
            Features for Enterprise Teams
          </div>

          <h2
            class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
          >
            Forms business teams can use,
            <br class="hidden sm:block" />
            with controls IT can trust
          </h2>

          <p
            class="max-w-lg mx-auto text-base leading-7 tracking-[-1.1%] font-normal text-gray-600"
          >
            Build, automate, and integrate secure forms across departments -
            all without writing a single line of code.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in enterpriseFeatures"
            :key="item.title"
            class="overflow-hidden relative rounded-3xl border border-gray-200 bg-gray-50 p-8 flex flex-col justify-between"
          >
            <div>
              <div
                class="text-xl leading-7 tracking-[-0.6%] font-semibold text-gray-950"
              >
                {{ item.title }}
              </div>
              <div
                class="mt-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
              >
                {{ item.description }}
              </div>
            </div>
            <div class="mt-4 -mx-8 -mb-8">
              <img
                :src="item.imageSrc"
                :alt="item.title"
                class="w-full max-w-84.5 mx-auto rounded-t-xl object-cover"
                loading="lazy"
              />
            </div>
            <div
              class="absolute bottom-0 left-0 right-0 min-h-1/6 bg-linear-to-b from-transparent to-[#F8FAFC]"
            ></div>
          </div>
        </div>

        <div class="mt-12 sm:mt-16 flex justify-center">
          <UButton
            :to="{ name: 'index', hash: '#features' }"
            variant="outline"
            label="Explore All Features"
            trailing-icon="i-heroicons-arrow-right-20-solid"
            size="lg"
            class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 py-12 sm:py-28 bg-white">
      <div class="max-w-266 mx-auto">
        <div
          class="grid grid-cols-1 lg:grid-cols-12 lg:gap-12 items-center gap-10"
        >
          <div class="lg:col-span-7">
            <h2
              class="text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
            >
              VIP support and
              <br />
              account management
            </h2>
            <p
              class="mt-4 text-base leading-7 tracking-[-1.1%] font-normal text-gray-600"
            >
              Get direct access to dedicated experts who help you deploy, scale,
              and operate OpnForm with confidence.
            </p>
          </div>
          <div
            class="lg:col-span-5 w-full max-w-fit mx-auto lg:max-w-none rounded-3xl border border-gray-200 bg-gray-50 px-8 pt-8 pb-4"
          >
            <div
              v-for="badge in vipSupportBadges"
              :key="badge.label"
              class="flex mb-4 max-w-fit sm:w-auto items-center gap-3 rounded-full border border-gray-200 bg-white pl-1.5 pr-4 py-1.5 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-800 shadow-sm"
            >
              <span
                class="h-8 w-8 rounded-full flex items-center justify-center shrink-0"
                :class="badge.badgeBg"
              >
                <UIcon
                  :name="badge.icon"
                  class="h-4 w-4"
                  :class="badge.iconColor"
                />
              </span>
              <span>{{ badge.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white">
      <div class="px-8 lg:px-12">
        <div
          class="mx-auto w-full max-w-336 rounded-4xl bg-gray-950 shadow-2xl overflow-hidden py-8 sm:py-10 lg:py-14 xl:py-24 px-8 md:px-10 lg:px-14 xl:px-35"
        >
          <div class="text-center">
            <div
              class="text-blue-400 text-base tracking-[-1.1%] font-medium leading-7"
            >
              Integrations
            </div>
            <h3
              class="my-4 text-4xl sm:text-5xl font-semibold text-white tracking-[-1%] leading-14"
            >
              Works with your stack
            </h3>
            <p
              class="text-base tracking-[-1.1%] font-medium leading-7 text-gray-400"
            >
              OpnForm fits into your workflow — connect to tools your team
              already uses.
            </p>
          </div>
          <div class="mt-12 sm:mt-16 flex justify-center">
            <div
              class="grid grid-cols-5 sm:grid-cols-5 gap-4 justify-center items-center"
            >
              <div
                v-for="app in integrationApps"
                :key="app.icon"
                class="h-14 sm:h-22 md:h-30 w-14 sm:w-22 md:w-30 rounded-2xl bg-gray-800 flex items-center justify-center"
              >
                <UIcon
                  :name="app.icon"
                  class="h-9 sm:h-12 md:h-14 w-9 sm:w-12 md:w-14"
                  :class="app.iconClass"
                />
              </div>
            </div>
          </div>
          <div class="mt-12 sm:mt-16 flex justify-center">
            <UButton
              :to="{ name: 'integrations' }"
              label="Explore All Integrations"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
          </div>
        </div>
      </div>
    </section>

    <section class="px-8 sm:px-12 py-14 sm:py-28 bg-white">
      <div class="max-w-266 mx-auto">
        <div class="max-w-2xl mx-auto text-center">
          <div
            class="text-base leading-7 tracking-[-1.1%] font-medium text-blue-600"
          >
            Why Enterprise
          </div>
          <h2
            class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
          >
            Control without slowing
            <br class="hidden sm:block" />
            teams down
          </h2>
          <p
            class="text-base leading-7 tracking-[-1.1%] font-normal text-gray-600"
          >
            Keep the builder simple for every team while giving IT the controls
            needed for access, data, and deployment.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in enterpriseProofBlocks"
            :key="item.title"
            class="rounded-3xl bg-gray-50 border border-gray-200 p-8 shadow-sm"
          >
            <div
              class="h-12 w-12 rounded-[16px] bg-white shadow-sm flex items-center justify-center"
            >
              <UIcon :name="item.icon" class="h-6 w-6 text-blue-600" />
            </div>
            <h3
              class="mt-6 text-xl leading-7 tracking-[-0.6%] font-semibold text-gray-950"
            >
              {{ item.title }}
            </h3>
            <p
              class="mt-3 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
            >
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <FaqSection
      :faqs="enterpriseFaqs"
      :title-lines="['Enterprise forms, security,', 'and deployment']"
      description="Clear answers for teams evaluating OpnForm for secure forms, data control, and managed or self-hosted deployment."
      id-prefix="enterprise-faq-answer"
      @contact="contactUs"
    />

    <section class="bg-white px-8 lg:px-12 pb-14 sm:pb-28">
      <div
        class="mx-auto w-full max-w-336 rounded-4xl bg-gray-950 shadow-2xl overflow-hidden py-8 sm:py-10 lg:py-14 px-8 md:px-10 lg:px-14"
      >
        <div class="grid gap-8 lg:grid-cols-12 lg:items-center">
          <div class="lg:col-span-7">
            <div
              class="text-blue-400 text-base tracking-[-1.1%] font-medium leading-7"
            >
              Enterprise
            </div>
            <h2
              class="mt-4 text-3xl sm:text-5xl sm:leading-14 font-semibold text-white tracking-[-1%]"
            >
              Bring secure forms to every team.
            </h2>
            <p
              class="mt-4 max-w-2xl text-base sm:text-lg leading-7 tracking-[-1.1%] font-normal text-gray-400"
            >
              Talk with us about SSO, data residency, self-hosting, and the
              rollout path that fits your organization.
            </p>
          </div>

          <div class="lg:col-span-5">
            <div class="flex flex-col sm:flex-row lg:justify-end gap-4">
              <UButton
                :to="opnformConfig.links.book_onboarding"
                target="_blank"
                label="Book a Demo"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                size="lg"
                class="w-fit pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
              />
              <UButton
                :to="opnformConfig.links.self_hosting"
                target="_blank"
                label="Explore Self-Hosting"
                variant="outline"
                color="neutral"
                size="lg"
                class="w-fit px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium bg-white text-gray-950 hover:bg-white/95"
              />
            </div>

            <div
              class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-300 lg:justify-end"
            >
              <span
                v-for="item in finalCtaProofs"
                :key="item"
                class="inline-flex items-center gap-2"
              >
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="h-5 w-5 text-emerald-400"
                />
                {{ item }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter :show-cta="false" />
  </div>
</template>

<script setup>
import FaqSection from "~/components/pages/FaqSection.vue"
import opnformConfig from "~/opnform.config.js"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "Secure Form Builder for Teams and Enterprises",
      description:
    "Build secure forms, surveys, and workflows with OpnForm Enterprise. Manage access, automate data collection, and choose cloud, France hosting, or self-hosted deployment.",
})

const controlHighlights = [
  {
    icon: "i-heroicons-lock-closed",
    iconWrapClass: "bg-violet-50",
    iconClass: "text-violet-600",
    title: "Access control",
    description: "Manage SSO, roles, permissions, and audit visibility.",
  },
  {
    icon: "i-heroicons-server-stack",
    iconWrapClass: "bg-blue-50",
    iconClass: "text-blue-600",
    title: "Data ownership",
    description: "Choose cloud, hosting in France, or self-hosted deployment.",
  },
  {
    icon: "i-heroicons-arrows-right-left",
    iconWrapClass: "bg-emerald-50",
    iconClass: "text-emerald-600",
    title: "Team workflows",
    description: "Automate forms, surveys, approvals, and integrations.",
  },
]

const securityFeatures = [
  {
    icon: "i-heroicons-check-badge",
    title: "Granular Access Control",
    description:
      "Assign roles and permissions across teams, projects, and forms.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "France Data Residency",
    description:
      "Keep hosted form data in France, or on your own servers when you self-host.",
  },
  {
    icon: "i-heroicons-lock-closed",
    title: "Encryption at Rest & In Transit",
    description: "Every submission is securely stored and transmitted.",
  },
  {
    icon: "i-heroicons-document-text",
    title: "Audit Logs & Security SLAs",
    description: "Get visibility and accountability at every step.",
  },
]

const deploymentOptions = [
  {
    icon: "i-heroicons-cloud",
    title: "Managed Cloud",
    description:
      "Let OpnForm handle hosting, updates, monitoring, and backups for your team.",
    features: ["Fastest setup", "Managed updates", "Live chat support"],
  },
  {
    icon: "i-heroicons-globe-europe-africa",
    title: "France Hosting",
    description:
      "Keep hosted form data in France while your team keeps the simplicity of cloud.",
    features: ["Hosted in France", "GDPR-friendly architecture", "No infrastructure work"],
  },
  {
    icon: "i-heroicons-server-stack",
    title: "Self-hosted / On-premise",
    description:
      "Run OpnForm on your own infrastructure when policy or compliance requires it.",
    features: ["Full infrastructure control", "Custom builds", "Self-hosting support"],
  },
]

const enterpriseFeatures = [
  {
    title: "Payments",
    description:
      "Collect payments securely with Stripe or PayPal, and automate invoices instantly.",
    imageSrc: "/img/pages/enterprise/enterprise-1.png",
  },
  {
    title: "Automations",
    description:
      "Connect OpnForm to your existing tools — send submissions to CRMs, trigger webhooks, or automate workflows with Zapier or Make.",
    imageSrc: "/img/pages/enterprise/enterprise-2.png",
  },
  {
    title: "E-Signatures",
    description:
      "Capture legally binding signatures directly within your forms.",
    imageSrc: "/img/pages/enterprise/enterprise-3.png",
  },
  {
    title: "Collaboration",
    description:
      "Invite your entire team, manage permissions, and review submissions together.",
    imageSrc: "/img/pages/enterprise/enterprise-4.png",
  },
  {
    title: "Custom Branding & Themes",
    description: "Fully white-label your forms to match your brand.",
    imageSrc: "/img/pages/enterprise/enterprise-5.png",
  },
  {
    title: "Flexible Deployment",
    description:
      "Use OpnForm Cloud for simplicity — or self-host for complete control over data and uptime.",
    imageSrc: "/img/pages/enterprise/enterprise-6.png",
  },
]

const vipSupportBadges = [
  {
    icon: "i-heroicons-lock-closed",
    label: "Migration Assistance",
    badgeBg: "bg-violet-50",
    iconColor: "text-violet-600",
  },
  {
    icon: "i-heroicons-wrench-screwdriver",
    label: "Self-hosting Technical Support",
    badgeBg: "bg-orange-50",
    iconColor: "text-orange-600",
  },
  {
    icon: "i-heroicons-user-circle",
    label: "Dedicated Customer Success Manager",
    badgeBg: "bg-blue-50",
    iconColor: "text-blue-600",
  },
]

const integrationApps = [
  { icon: "i-simple-icons-zendesk", iconClass: "text-[#ffffff]" },
  { icon: "heroicons:envelope-20-solid", iconClass: "text-[#3B82F6]" },
  { icon: "ic:baseline-discord", iconClass: "text-[#5865F2]" },
  { icon: "mdi:telegram", iconClass: "text-[#27A7E7]" },
  { icon: "material-symbols:webhook", iconClass: "text-[#0061FF]" },

  { icon: "i-simple-icons-zapier", iconClass: "text-[#FF4A00]" },
  { icon: "simple-icons:n8n", iconClass: "text-[#EA4B71]" },
  { icon: "opnform:activepieces", iconClass: "text-[#6366F1]" },
  { icon: "mdi:google-spreadsheet", iconClass: "text-[#34A853]" },
  { icon: "i-simple-icons-zoom", iconClass: "text-[#2D8CFF]" },

  // { icon: "i-simple-icons-linear", iconClass: "text-[#5E6AD2]" },
  // { icon: "i-simple-icons-evernote", iconClass: "text-[#00A82D]" },
  // { icon: "i-simple-icons-notion", iconClass: "text-white" },
  // { icon: "i-simple-icons-airtable", iconClass: "text-[#18BFFF]" },
  // { icon: "i-simple-icons-meta", iconClass: "text-[#0668E1]" },
]

const enterpriseProofBlocks = [
  {
    icon: "i-heroicons-arrows-pointing-out",
    title: "No response caps",
    description:
      "Collect unlimited submissions without turning every successful form into a pricing problem.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Open-source and auditable",
    description:
      "Review the product, inspect the code, and avoid relying on a black box for critical forms.",
  },
  {
    icon: "i-heroicons-lock-closed",
    title: "SSO and permissions",
    description:
      "Use enterprise identity controls and workspace roles to keep access aligned with your team.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Own your data path",
    description:
      "Choose managed cloud, hosting in France, or self-hosting depending on your internal requirements.",
  },
  {
    icon: "i-heroicons-paint-brush",
    title: "Brand control",
    description:
      "Use custom domains, branding removal, white-labeling, and themes for a polished experience.",
  },
  {
    icon: "i-heroicons-user-circle",
    title: "Migration support",
    description:
      "Get help with deployment, migration, and self-hosted technical questions as your rollout grows.",
  },
]

const enterpriseFaqs = [
  {
    question: "Can OpnForm be self-hosted?",
    answer:
      "Yes. OpnForm can be deployed on your own infrastructure, and Enterprise support can help with self-hosting questions, migration, and deployment planning.",
  },
  {
    question: "Does OpnForm support SSO?",
    answer:
      "Yes. OpnForm supports SSO options such as OIDC and Enterprise SSO controls such as SAML, depending on your setup and plan.",
  },
  {
    question: "Where is form data hosted?",
    answer:
      "Hosted OpnForm data is stored in France. You can also self-host OpnForm when your organization needs direct control over infrastructure and data residency.",
  },
  {
    question: "Can teams manage roles and permissions?",
    answer:
      "Yes. Enterprise is designed for multi-user workspaces with roles, permissions, and audit visibility for teams managing forms at scale.",
  },
  {
    question: "What support is included with Enterprise?",
    answer:
      "Enterprise includes priority support for rollout, migration, and self-hosting needs, with dedicated help for teams that need stronger operational guidance.",
  },
]

const enterpriseFaqSchema = {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  mainEntity: enterpriseFaqs.map((faq) => ({
    "@type": "Question",
    name: faq.question,
    acceptedAnswer: {
      "@type": "Answer",
      text: faq.answer,
    },
  })),
}

useHead({
  script: [
    {
      key: "enterprise-faq-schema",
      type: "application/ld+json",
      textContent: JSON.stringify(enterpriseFaqSchema),
    },
  ],
})

const finalCtaProofs = ["SSO/SAML", "France hosting", "Self-hosting support"]

const contactUs = () => {
  useCrisp().openAndShowChat()
}
</script>
