<template>
  <main class="flex-1 bg-white">
    <MarketingHero
      eyebrow="Self-hosted form builder"
      description="Create unlimited forms and submissions with logic, calculations, file uploads, integrations, embeds, and a polished no-code editor. Keep the product and data in your own environment."
      :proofs="heroProofs"
    >
      <template #title>
        The self-hosted form builder
        <span class="text-blue-600">your whole team can use</span>
      </template>

      <template #actions>
        <UButton
          to="#features"
          size="lg"
          label="Explore the features"
          trailing-icon="i-heroicons-arrow-down-20-solid"
          class="justify-center rounded-xl px-5"
        />
        <UButton
          to="#compare-editions"
          size="lg"
          color="neutral"
          variant="outline"
          label="Compare editions"
          trailing-icon="i-heroicons-arrow-down-20-solid"
          class="justify-center rounded-xl px-5"
        />
      </template>

      <template #visual>
        <img
          src="/img/pages/self-hosted-form-builder/hero-self-hosted-secure-native-v3-640.webp"
          srcset="/img/pages/self-hosted-form-builder/hero-self-hosted-secure-native-v3-640.webp 640w, /img/pages/self-hosted-form-builder/hero-self-hosted-secure-native-v3-1024.webp 1024w"
          sizes="(min-width: 1024px) 50vw, 100vw"
          alt="OpnForm submissions flowing to a selected and secured self-hosted server"
          width="1024"
          height="1024"
          fetchpriority="high"
          class="mx-auto w-full max-w-[620px]"
        />
      </template>
    </MarketingHero>

    <MarketingSplitSection
      eyebrow="A complete product, self-hosted"
      title="Control where it runs without compromising what it can do"
    >
      <p>
        Self-hosting changes where OpnForm runs, not what your team can build. Create
        branded forms, surveys, registrations, payments, and internal workflows with
        the same no-code experience as OpnForm Cloud.
      </p>
      <p>
        Community includes the core product for up to two users. Enterprise adds the
        collaboration, identity, branding, and governance features larger teams need.
      </p>
    </MarketingSplitSection>

    <Features :panels="selfHostedFeaturePanels" :show-explorer="false" />

    <div id="compare-editions" class="scroll-mt-20">
      <PillarComparisonTable
        eyebrow="Find the right edition"
        title="Cloud, Community, or Enterprise self-hosted"
        description="The core form-building experience stays familiar across editions. Choose based on where you want OpnForm to run and the team controls you need."
        label-column-title="Feature"
        caption="Comparison of OpnForm Cloud, Community Self-Hosted, and Enterprise Self-Hosted"
        :columns="selfHostedColumns"
        :rows="selfHostedRows"
        :sources="selfHostedSources"
        sources-label="Documentation"
        reviewed-at="August 23, 2026"
        note="Edition details are based on the current OpnForm pricing, deployment, and licensing documentation. Confirm your exact requirements before purchase."
      />
    </div>

    <section class="border-y border-neutral-200 bg-[#f7f9fc] py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
              Enterprise features
            </p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
              Add team controls when OpnForm grows with you
            </h2>
            <p class="mt-5 text-lg leading-8 text-neutral-600">
              Keep the form builder simple for everyone, then add the identity,
              permissions, branding, and oversight your organization requires.
            </p>
            <UButton
              :to="{ name: 'self-hosted-form-builder-license' }"
              label="Explore Enterprise"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              class="mt-7 justify-center rounded-xl"
            />
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <article
              v-for="capability in enterpriseCapabilities"
              :key="capability.title"
              class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm"
            >
              <span class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-700">
                <UIcon :name="capability.icon" class="h-5 w-5" />
              </span>
              <h3 class="mt-5 font-semibold text-neutral-950">{{ capability.title }}</h3>
              <p class="mt-2 text-sm leading-6 text-neutral-600">{{ capability.description }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="mx-auto max-w-3xl text-center">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">Choose your edition</p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl lg:text-5xl">
            Start free. Add governance when the rollout grows.
          </h2>
          <p class="mt-5 text-lg leading-8 text-neutral-600">
            Start with every core form feature in Community. Move to Enterprise when
            more people need advanced access, branding, identity, and oversight.
          </p>
        </div>

        <div class="mx-auto mt-12 grid max-w-5xl gap-6 lg:grid-cols-2">
          <article
            v-for="edition in editions"
            :key="edition.title"
            class="relative overflow-hidden rounded-3xl border bg-white p-7 shadow-sm sm:p-9"
            :class="edition.highlight ? 'border-blue-300 ring-1 ring-blue-100' : 'border-neutral-200'"
          >
            <div v-if="edition.highlight" class="absolute right-0 top-0 h-32 w-32 rounded-bl-full bg-blue-100/70" aria-hidden="true" />
            <p class="relative text-sm font-semibold uppercase tracking-[0.12em] text-neutral-500">{{ edition.eyebrow }}</p>
            <h3 class="relative mt-2 text-2xl font-semibold text-neutral-950">{{ edition.title }}</h3>
            <p class="relative mt-3 leading-7 text-neutral-600">{{ edition.description }}</p>
            <p class="relative mt-7 text-3xl font-semibold tracking-tight text-neutral-950">{{ edition.price }}</p>
            <ul class="relative mt-7 space-y-3">
              <li v-for="feature in edition.features" :key="feature" class="flex gap-3 text-sm leading-6 text-neutral-700">
                <UIcon name="i-heroicons-check-20-solid" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                {{ feature }}
              </li>
            </ul>
            <UButton v-bind="edition.cta" size="lg" class="relative mt-8 justify-center rounded-xl px-5" />
          </article>
        </div>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-neutral-950 py-12 text-white sm:py-14">
      <div class="mx-auto grid max-w-6xl gap-7 px-5 sm:px-8 lg:grid-cols-[1fr_auto] lg:items-center lg:px-12">
        <div class="max-w-3xl">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-300">Ready when your team is</p>
          <h2 class="mt-3 text-2xl font-semibold !text-white sm:text-3xl">A documented path from download to deployment</h2>
          <p class="mt-3 leading-7 text-neutral-300">
            The Docker guide covers setup, configuration, updates, and troubleshooting
            when your technical team is ready to deploy.
          </p>
        </div>
        <UButton
          :to="opnformConfig.links.self_hosting"
          target="_blank"
          color="neutral"
          variant="outline"
          label="Open Docker deployment guide"
          trailing-icon="i-heroicons-arrow-up-right-20-solid"
          class="justify-center rounded-xl border-neutral-600 bg-transparent text-white hover:bg-neutral-800"
        />
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-neutral-50 py-14 sm:py-16">
      <div class="mx-auto grid max-w-6xl gap-6 px-5 sm:px-8 lg:grid-cols-[1fr_auto] lg:items-center lg:px-12">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">Still comparing products?</p>
          <h2 class="mt-3 text-2xl font-semibold text-neutral-950">Review the open-source category before choosing a deployment</h2>
          <p class="mt-3 max-w-3xl leading-7 text-neutral-600">
            Compare OpnForm with Formbricks, HeyForm, LimeSurvey, and Form.io on the open-source form builder page.
          </p>
        </div>
        <UButton
          :to="{ name: 'open-source-form-builder' }"
          color="neutral"
          variant="outline"
          label="Compare open-source builders"
          trailing-icon="i-heroicons-arrow-right-20-solid"
          class="justify-center rounded-xl"
        />
      </div>
    </section>

    <FaqSection
      variant="split"
      eyebrow="FAQ"
      title="Self-hosted form builder questions"
      description="Answers about product features, Community limits, Enterprise licensing, data control, and deployment."
      :faqs="selfHostedFaqs"
      id-prefix="self-hosted-form-builder-faq"
      @contact="contactUs"
    />

    <MarketingCtaPanel
      eyebrow="Choose your self-hosted edition"
      title="Start free, or unlock Enterprise for your team"
      description="Deploy the complete Community form builder for free. Choose Enterprise when you need more users, SSO, advanced branding, audit logs, and priority support."
    >
      <UButton
        :to="opnformConfig.links.self_hosting"
        target="_blank"
        size="lg"
        color="neutral"
        label="Deploy Community"
        trailing-icon="i-heroicons-arrow-up-right-20-solid"
        class="justify-center rounded-xl bg-white px-5 text-blue-700 hover:bg-blue-50"
      />
      <UButton
        :to="{ name: 'self-hosted-form-builder-license' }"
        size="lg"
        color="neutral"
        label="Buy Enterprise license"
        trailing-icon="i-heroicons-arrow-right-20-solid"
        class="justify-center rounded-xl px-5 !bg-blue-950 !text-white hover:!bg-blue-900"
      />
    </MarketingCtaPanel>

    <OpenFormFooter :show-cta="false" />
  </main>
</template>

<script setup>
import FaqSection from "~/components/pages/FaqSection.vue"
import MarketingCtaPanel from "~/components/pages/marketing/MarketingCtaPanel.vue"
import MarketingHero from "~/components/pages/marketing/MarketingHero.vue"
import MarketingSplitSection from "~/components/pages/marketing/MarketingSplitSection.vue"
import PillarComparisonTable from "~/components/pages/pillars/PillarComparisonTable.vue"
import Features from "~/components/pages/welcome/Features.vue"
import {
  selfHostedColumns,
  selfHostedRows,
  selfHostedSources,
} from "~/data/marketing/self-hosted-editions.js"
import opnformConfig from "~/opnform.config.js"

definePageMeta({
  layout: "default",
})

defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: "Self-Hosted Form Builder",
  description:
    "Self-host OpnForm with unlimited forms and submissions, logic, file uploads, embeds, webhooks, API access, SSO, branding, audit logs, and control of your data.",
})

const { tiers } = usePlanCatalog()
const yearlyPrice = computed(() => tiers.value.self_hosted?.price_yearly ?? 1999)
const formatUsd = (amount) => new Intl.NumberFormat("en-US").format(amount)

const heroProofs = ["Unlimited forms and submissions", "Logic and calculations", "File uploads", "API and webhooks"]

const selfHostedFeaturePanels = [
  {
    eyebrow: "Modern form builder",
    eyebrowClass: "text-blue-600",
    title: "Build polished forms without giving your team a technical tool.",
    description:
      "Self-hosting changes where OpnForm runs. The people creating forms still get the same visual editor, flexible layouts, themes, and logic as OpnForm Cloud.",
    items: [
      {
        title: "Modern multi-step and single-page forms",
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
        title: "Conditional logic and calculations",
        icon: "i-heroicons-arrows-right-left",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
      {
        title: "Custom themes, brand colors, and fonts",
        icon: "i-heroicons-paint-brush",
        iconWrapClass: "bg-blue-50 ring-blue-100",
        iconClass: "text-blue-600",
      },
    ],
    imageSrc: "/img/pages/welcome/feature-1.png",
    link: null,
  },
  {
    eyebrow: "Unlimited forms and submissions",
    eyebrowClass: "text-emerald-600",
    title: "Collect every response without watching a usage meter.",
    description:
      "Community and Enterprise include unlimited forms and submissions, so a successful campaign or internal workflow does not create a new usage bill.",
    items: [
      {
        title: "Unlimited forms and submissions",
        icon: "i-ph-infinity-bold",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
      {
        title: "File uploads and rich inputs",
        icon: "i-heroicons-paper-clip",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
      {
        title: "Exports and email notifications",
        icon: "i-heroicons-arrow-down-tray",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
      {
        title: "Validation and computed fields",
        icon: "i-heroicons-calculator",
        iconWrapClass: "bg-emerald-50 ring-emerald-100",
        iconClass: "text-emerald-600",
      },
    ],
    imageSrc: "/img/pages/welcome/feature-2.png",
    link: null,
  },
  {
    eyebrow: "Integrations and automation",
    eyebrowClass: "text-violet-600",
    title: "Fit forms into the workflows your team already runs.",
    description:
      "Use standard integrations, webhooks, and the REST API to move each submission where it needs to go while OpnForm stays in your environment.",
    items: [
      {
        title: "Google Sheets and Zapier",
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
        title: "Slack, Discord, and Telegram",
        icon: "i-heroicons-chat-bubble-left-right",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
      {
        title: "Webhooks, REST API, and embeds",
        icon: "i-heroicons-link",
        iconWrapClass: "bg-violet-50 ring-violet-100",
        iconClass: "text-violet-600",
      },
    ],
    imageSrc: "/img/pages/welcome/feature-3.png",
    link: {
      to: { name: "features" },
      label: "Explore all features",
      class: "text-violet-600 hover:text-violet-700 hover:no-underline",
    },
  },
]

const enterpriseCapabilities = [
  {
    icon: "i-heroicons-user-group-20-solid",
    title: "Roles and multiple workspaces",
    description: "Give people the right level of access and keep teams organized as usage grows.",
  },
  {
    icon: "i-heroicons-finger-print-20-solid",
    title: "SAML and LDAP SSO",
    description: "Connect OpnForm to the identity systems your organization already uses.",
  },
  {
    icon: "i-heroicons-swatch-20-solid",
    title: "Branding and white-label",
    description: "Remove OpnForm branding and control how forms and outgoing email represent your organization.",
  },
  {
    icon: "i-heroicons-shield-check-20-solid",
    title: "Audit logs and data controls",
    description: "Add oversight, external storage, and custom code for more demanding workflows.",
  },
]

const editions = computed(() => [
  {
    eyebrow: "For individuals and small teams",
    title: "Community Self-Hosted",
    description: "Get the complete core form-building experience in your own environment without an Enterprise subscription.",
    price: "Free",
    features: [
      "Unlimited forms and submissions",
      "Logic, calculations, and validation",
      "File uploads, embeds, and exports",
      "API, webhooks, and standard integrations",
      "Up to 2 users across the instance",
      "OIDC within the Community user limit",
    ],
    cta: {
      label: "Read deployment docs",
      to: opnformConfig.links.self_hosting,
      target: "_blank",
      color: "neutral",
      variant: "outline",
      trailingIcon: "i-heroicons-arrow-up-right-20-solid",
    },
  },
  {
    eyebrow: "For growing teams",
    title: "Enterprise Self-Hosted",
    description: "Add advanced collaboration, identity, branding, and oversight to every core Community feature.",
    price: `$${formatUsd(yearlyPrice.value)} / year`,
    highlight: true,
    features: [
      "More than 2 users",
      "Roles and multiple workspaces",
      "SAML and LDAP single sign-on",
      "Branding removal and workspace SMTP",
      "Audit logs, external storage, and custom code",
      "Priority support",
    ],
    cta: {
      label: "Buy Enterprise license",
      to: { name: "self-hosted-form-builder-license" },
      trailingIcon: "i-heroicons-arrow-right-20-solid",
    },
  },
])

const selfHostedFaqs = [
  {
    question: "Can I self-host OpnForm for free?",
    answer: "Yes. Community Self-Hosted includes the AGPLv3 core, unlimited forms and submissions, and up to two users across the instance. You operate the infrastructure and use community support.",
  },
  {
    question: "When do I need an Enterprise license?",
    answer: "Use Enterprise when you need more than two users, SAML or LDAP, roles and permissions, audit logs, branding removal, workspace-level controls, or priority support.",
  },
  {
    question: "Why choose OpnForm as a self-hosted form builder?",
    answer: "OpnForm combines a polished no-code editor with unlimited forms and submissions, logic, calculations, file uploads, embeds, integrations, and API access. Self-hosting lets you keep that product experience while running OpnForm and storing submission data in your own environment.",
  },
  {
    question: "What does my team operate?",
    answer: "Your team owns the application runtime, database, file storage, SMTP, domains, TLS, backups, monitoring, updates, and incident response for the deployment.",
  },
  {
    question: "Does Community Self-Hosted include OIDC?",
    answer: "Yes. OIDC is available on Community Self-Hosted within the two-user limit. Enterprise is required for more users and adds SAML and LDAP options.",
  },
  {
    question: "Does self-hosting make a deployment compliant?",
    answer: "Self-hosting can support data residency and infrastructure-control requirements, but compliance still depends on your configuration, security controls, legal basis, retention rules, and operating process.",
  },
  {
    question: "How many instances can use one Enterprise license?",
    answer: "The self-service subscription currently provides one active production instance activation. Contact OpnForm before purchase if your rollout needs multiple active instances or a custom written agreement.",
  },
]

useJsonLd("self-hosted-form-builder-schema", buildSchemaGraph([
  buildOrganizationSchema(),
  buildWebsiteSchema(),
  buildSoftwareApplicationSchema(),
  buildWebPageSchema({
    name: "Self-Hosted Form Builder",
    description: "Build unlimited self-hosted forms with logic, file uploads, embeds, integrations, API access, and optional Enterprise team controls.",
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
