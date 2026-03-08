<template>
  <div>
    <section class="relative">
      <div class="py-14 sm:py-28 px-8 sm:px-12 relative z-2">
        <div class="max-w-3xl mx-auto text-center">
          <h1
            class="text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950"
          >
            Powerful and secure form
            <br />
            builder for all your teams
          </h1>
          <p
            class="mt-4 text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600"
          >
            OpnForm Enterprise gives your organization full control over your
            data and infrastructure — whether you self-host or use our managed
            cloud.
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
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/logo-berkeley-2.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/UN_emblem_blue-2.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/Frame-47.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
              <div class="w-fit">
                <img
                  src="/img/pages/welcome/Zomato_Logo-2.png"
                  alt="Trusted Teams"
                  class="w-full"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div
        class="w-full h-full bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85% absolute inset-0"
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
            <span>Hosted in Europe</span>
          </div>
        </div>

        <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="item in securityFeatures"
            :key="item.title"
            class="rounded-3xl bg-gray-50 p-8"
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
        <div class="text-center">
          <div
            class="text-base leading-7 tracking-[-1.1%] font-medium text-blue-600"
          >
            Features for Enterprise Teams
          </div>

          <h2
            class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
          >
            Forms that adapt <br />
            to all your needs
          </h2>

          <p
            class="max-w-lg mx-auto text-base leading-7 tracking-[-1.1%] font-normal text-gray-600"
          >
            Build, automate, and integrate secure forms at scale — all without
            writing a single line of code.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in enterpriseFeatures"
            :key="item.title"
            class="overflow-hidden relative rounded-3xl bg-gray-50 p-8 flex flex-col justify-between"
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
            class="lg:col-span-5 w-full max-w-fit mx-auto lg:max-w-none rounded-3xl bg-gray-50 px-8 pt-8 pb-4"
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
          class="mx-auto w-full max-w-336 rounded-4xl bg-linear-to-br from-gray-950 via-gray-900 to-gray-950 shadow-2xl overflow-hidden py-8 sm:py-10 lg:py-14 xl:py-24 px-8 md:px-10 lg:px-14 xl:px-35"
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
      <div class="max-w-4xl mx-auto">
        <div class="text-center">
          <div
            class="text-blue-400 text-base tracking-[-1.1%] font-medium leading-7"
          >
            Case Studies
          </div>
          <h3
            class="mt-4 text-4xl sm:text-5xl font-semibold text-gray-950 tracking-[-1%] leading-14"
          >
            See how teams use <br class="hidden sm:block" />
            OpnForm at scale
          </h3>
        </div>
        <div
          class="grid grid-cols-1 md:grid-cols-7 gap-y-12 md:gap-12 my-12 sm:my-16"
        >
          <div
            class="col-span-3 max-h-70 md:max-h-50 max-w-100 mx-auto rounded-[12px] overflow-hidden flex items-center justify-centers"
          >
            <img src="/img/pages/enterprise/enterprise-7.png" alt="John Doe" />
          </div>
          <div class="col-span-4">
            <h3 class="text-xl leading-7 font-medium text-gray-950">
              “Scaled internal workflows without adding complexity”
            </h3>
            <p
              class="mt-3 text-base leading-7 font-normal tracking-[-1.1%] text-gray-600"
            >
              We use OpnForm across multiple teams for internal requests,
              approvals, and data collection. Having unlimited forms with clear
              access controls made it easy to standardize workflows without
              slowing teams down.
            </p>
            <div class="mt-8">
              <h4
                class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-950"
              >
                John Doe
              </h4>
              <p
                class="mt-1.5 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-600"
              >
                Head of Engineering, Vercel
              </p>
            </div>
          </div>
        </div>
        <div
          class="grid grid-cols-1 md:grid-cols-7 gap-y-12 md:gap-12 my-12 sm:my16"
        >
          <div
            class="col-span-3 max-h-70 md:max-h-50 max-w-100 mx-auto rounded-[12px] overflow-hidden flex items-center justify-centers"
          >
            <img
              src="/img/pages/enterprise/enterprise-8.png"
              alt="James Smith"
            />
          </div>
          <div class="col-span-4">
            <h3 class="text-xl leading-7 font-medium text-gray-950">
              “Finally had one system that worked for every team.”
            </h3>
            <p
              class="mt-3 text-base leading-7 font-normal tracking-[-1.1%] text-gray-600"
            >
              Marketing, HR, and Support were all using different tools. OpnForm
              gave us a single, flexible platform that everyone could adapt to
              their needs, while still keeping data centralized and secure.
            </p>
            <div class="mt-8">
              <h4
                class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-950"
              >
                James Smith
              </h4>
              <p
                class="mt-1.5 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-600"
              >
                Operations Lead, Airtable
              </p>
            </div>
          </div>
        </div>
        <div class="flex justify-center items-center">
          <UButton
            to="#"
            target="_blank"
            label="Read More Customer Stories"
            variant="outline"
            size="lg"
            class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </section>

    <OpenFormFooter />
  </div>
</template>

<script setup>
import opnformConfig from "~/opnform.config.js";

definePageMeta({
  layout: "default",
});

useOpnSeoMeta({
  title: "Enterprise",
  description:
    "Powerful and secure form builder for teams that need full control over data, security, and deployment.",
});

const securityFeatures = [
  {
    icon: "i-heroicons-check-badge",
    title: "Granular Access Control",
    description:
      "Assign roles and permissions across teams, projects, and forms.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Data Residency Options",
    description:
      "Keep data in your own servers or select EU/US data centers for compliance.",
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
];

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
];

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
];

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
];
</script>
