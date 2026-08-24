<template>
  <main class="flex-1 overflow-hidden bg-white">
    <MarketingHero
      eyebrow="Open-source form builder"
      description="Create forms, surveys, registrations, payments, and workflows with a polished no-code builder. Start free on OpnForm Cloud, or self-host the AGPLv3 core when you want to own the stack."
      :proofs="heroProofs"
    >
      <template #eyebrow>
        <NuxtLink
          :to="opnformConfig.links.github_url"
          target="_blank"
          class="group inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white/85 px-3.5 py-1.5 text-sm font-medium text-neutral-700 shadow-xs backdrop-blur transition-colors hover:border-blue-300 hover:bg-white hover:text-neutral-950 hover:no-underline"
          :aria-label="githubStarsLabel"
        >
          <UIcon name="i-simple-icons-github" class="h-4 w-4 shrink-0 text-neutral-950" />
          <template v-if="formattedGithubStars">
            <span class="font-semibold tabular-nums text-neutral-950">
              {{ formattedGithubStars }}
            </span>
            <UIcon name="i-heroicons-star-solid" class="h-3.5 w-3.5 shrink-0 text-amber-400" />
            <span class="text-neutral-600">on GitHub</span>
          </template>
          <span v-else>Open source on GitHub</span>
          <UIcon
            name="i-heroicons-arrow-up-right-20-solid"
            class="h-3.5 w-3.5 shrink-0 text-neutral-400 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5"
          />
        </NuxtLink>
      </template>

      <template #title>
        The open-source form builder
        <span class="text-blue-600">your whole team can use</span>
      </template>

      <template #actions>
        <UButton
          :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
          size="lg"
          label="Create a free form"
          trailing-icon="i-heroicons-arrow-up-right-20-solid"
          class="justify-center rounded-xl px-5"
        />
        <UButton
          :to="opnformConfig.links.github_url"
          target="_blank"
          size="lg"
          variant="outline"
          color="neutral"
          label="Explore the source"
          trailing-icon="i-simple-icons-github"
          class="justify-center rounded-xl px-5"
        />
      </template>

      <template #visual>
        <img
          src="/img/pages/open-source-form-builder/hero-open-source-builder-code-github-768.webp"
          srcset="/img/pages/open-source-form-builder/hero-open-source-builder-code-github-512.webp 512w, /img/pages/open-source-form-builder/hero-open-source-builder-code-github-768.webp 768w, /img/pages/open-source-form-builder/hero-open-source-builder-code-github-1024.webp 1024w"
          sizes="(min-width: 1280px) 620px, (min-width: 1024px) 50vw, 92vw"
          alt="OpnForm visual form builder connected to its source code and public GitHub repository"
          width="1024"
          height="1024"
          fetchpriority="high"
          decoding="async"
          class="mx-auto w-full max-w-[620px]"
        />
      </template>
    </MarketingHero>

    <MarketingProductShowcase
      id="product-showcase"
      eyebrow="A complete form product"
      title="Open source should not feel like a side project"
      description="OpnForm gives non-technical teams the builder they expect, while developers keep the APIs, source access, and deployment choices they need."
      :features="productFeatures"
      :proofs="productProofs"
      :cta="{ label: 'Explore every feature', to: { name: 'features' } }"
    />

    <MarketingSplitSection
      eyebrow="Freedom without friction"
      title="Own the option to self-host, without starting with the operational burden"
    >
      <p>
        Launch on OpnForm Cloud today and let us handle updates, backups, and uptime.
        The AGPLv3 core remains public, auditable, and available when your organization
        needs direct infrastructure control.
      </p>
      <p>
        When self-hosting becomes a real requirement, use the documented Docker path
        instead of rebuilding your forms in another product. Your team keeps the same
        builder, workflows, API, and submission model.
      </p>
      <p class="text-base text-neutral-500">
        Honest licensing: the core product is AGPLv3. Advanced team and governance
        features are available under a separate Enterprise license.
      </p>
      <div class="flex flex-col gap-3 pt-2 sm:flex-row">
        <UButton
          to="https://docs.opnform.com/deployment/docker"
          target="_blank"
          label="Read the Docker guide"
          trailing-icon="i-heroicons-arrow-up-right-20-solid"
          class="justify-center rounded-xl"
        />
        <UButton
          :to="opnformConfig.links.github_url"
          target="_blank"
          color="neutral"
          variant="outline"
          label="Inspect the source"
          trailing-icon="i-simple-icons-github"
          class="justify-center rounded-xl"
        />
      </div>
    </MarketingSplitSection>

    <MarketingNumberedSteps
      eyebrow="Why teams choose OpnForm"
      title="Open-source freedom without assembling your own form stack"
      description="Use one product from the first form to production workflows, self-hosting, and enterprise governance."
      :steps="opnformAdvantages"
    />

    <PillarComparisonTable
      eyebrow="Open-source benchmark"
      title="See why OpnForm is the stronger all-round choice"
      description="All five products publish meaningful source code. OpnForm stands out when you need a polished general-purpose builder for the whole team, not a survey specialist or a developer framework."
      caption="Comparison of OpnForm, Formbricks, HeyForm, LimeSurvey, and Form.io"
      :columns="openSourceCompetitorColumns"
      :rows="openSourceCompetitorRows"
      :sources="openSourceCompetitorSources"
      reviewed-at="August 23, 2026"
      note="Methodology: products are compared on license, product focus, builder experience, managed availability, integrations, and buyer fit using official documentation and repositories. Packaging changes over time, so verify critical requirements during your evaluation."
    >
      <template #before-table>
        <div class="mt-10 grid gap-4 text-left lg:grid-cols-[1.15fr_0.85fr]">
          <article class="rounded-3xl bg-blue-600 p-7 text-white shadow-[0_18px_45px_rgba(37,99,235,0.22)] sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.13em] text-blue-100">
              Best all-round choice
            </p>
            <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white sm:text-3xl">
              Choose OpnForm for forms your team runs every day
            </h3>
            <p class="mt-3 max-w-2xl leading-7 text-blue-50">
              OpnForm is built for registrations, applications, lead capture, payments,
              internal workflows, and embedded forms. You get a modern no-code product,
              unlimited submissions, and a credible Cloud-to-self-hosted path.
            </p>
            <UButton
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              color="neutral"
              label="Create a free form"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              class="mt-6 justify-center rounded-xl bg-white text-blue-700 hover:bg-blue-50"
            />
          </article>

          <article class="rounded-3xl border border-neutral-200 bg-neutral-50 p-7 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.13em] text-neutral-500">
              When a specialist fits better
            </p>
            <h3 class="mt-3 text-xl font-semibold text-neutral-950">
              Pick for the primary job, not the longest feature list
            </h3>
            <p class="mt-3 leading-7 text-neutral-600">
              Formbricks is more focused on product and experience surveys. LimeSurvey
              suits research-heavy survey programs. Form.io gives developers a deeper
              form-and-data framework. If those are your main use cases, compare them closely.
            </p>
          </article>
        </div>
      </template>
    </PillarComparisonTable>

    <section id="enterprise-governance" class="relative scroll-mt-20 overflow-hidden bg-neutral-950 py-16 text-white sm:py-20 lg:py-24">
      <div aria-hidden="true" class="absolute inset-0 bg-[linear-gradient(rgba(96,165,250,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(96,165,250,0.08)_1px,transparent_1px)] bg-[size:42px_42px]" />
      <div aria-hidden="true" class="absolute -right-32 top-8 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl" />
      <div class="relative mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[0.95fr_1.05fr] lg:items-center lg:px-12">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-300">
            Open core, enterprise ready
          </p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-5xl">
            Keep the product your team likes when governance gets serious
          </h2>
          <p class="mt-5 max-w-2xl text-lg leading-8 text-neutral-300">
            Do not replace a good form builder just because your organization grows.
            OpnForm Enterprise adds identity, access, audit, and rollout support on top
            of the same product and self-hosting path.
          </p>

          <ul class="mt-8 grid gap-3 sm:grid-cols-2">
            <li v-for="feature in enterpriseFeatures" :key="feature.title" class="flex gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
              <UIcon :name="feature.icon" class="mt-0.5 h-5 w-5 shrink-0 text-blue-300" />
              <div>
                <p class="font-semibold text-white">{{ feature.title }}</p>
                <p class="mt-1 text-sm leading-6 text-neutral-400">{{ feature.description }}</p>
              </div>
            </li>
          </ul>

          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <UButton
              :to="{ name: 'enterprise' }"
              size="lg"
              label="Explore OpnForm Enterprise"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              class="justify-center rounded-xl"
            />
            <UButton
              :to="{ name: 'self-hosted-form-builder-license' }"
              size="lg"
              color="neutral"
              variant="ghost"
              label="See self-hosted licensing"
              class="justify-center rounded-xl !border !border-white/15 !bg-white/10 !text-white hover:!bg-white/15"
            />
          </div>
        </div>

        <figure class="relative mx-auto w-full max-w-2xl">
          <img
            src="/img/pages/open-source-form-builder/enterprise-governance-960.webp"
            srcset="/img/pages/open-source-form-builder/enterprise-governance-640.webp 640w, /img/pages/open-source-form-builder/enterprise-governance-960.webp 960w, /img/pages/open-source-form-builder/enterprise-governance-1254.webp 1254w"
            sizes="(min-width: 1280px) 620px, (min-width: 1024px) 48vw, 92vw"
            alt="OpnForm connected to enterprise identity, role permissions, and audit controls"
            width="1254"
            height="1254"
            loading="lazy"
            decoding="async"
            class="w-full drop-shadow-[0_30px_60px_rgba(37,99,235,0.24)]"
          />
        </figure>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-[#f7f9fc] py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
              Detailed comparisons
            </p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
              See where OpnForm differs product by product
            </h2>
            <p class="mt-5 text-lg leading-8 text-neutral-600">
              Compare the workflows, hosting model, builder experience, and trade-offs
              that matter when your shortlist comes down to two products.
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <NuxtLink
              v-for="comparison in openSourceDetailedComparisons"
              :key="comparison.label"
              :to="comparison.to"
              class="group rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md hover:no-underline"
            >
              <UIcon name="i-heroicons-arrows-right-left-20-solid" class="h-6 w-6 text-blue-600" />
              <h3 class="mt-5 font-semibold text-neutral-950">{{ comparison.label }}</h3>
              <p class="mt-2 text-sm leading-6 text-neutral-600">{{ comparison.description }}</p>
              <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-blue-600">
                Read comparison
                <UIcon name="i-heroicons-arrow-right-20-solid" class="h-4 w-4 transition group-hover:translate-x-0.5" />
              </span>
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="mx-auto max-w-3xl text-center">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">One product, two paths</p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl lg:text-5xl">
            Start managed. Keep the option to own the stack.
          </h2>
          <p class="mt-5 text-lg leading-8 text-neutral-600">
            Most teams should start on Cloud. Self-host when policy, procurement, or
            architecture gives you a concrete reason to operate the application.
          </p>
        </div>

        <div class="mx-auto mt-12 grid max-w-5xl gap-6 lg:grid-cols-2">
          <article v-for="option in deploymentOptions" :key="option.title" class="rounded-3xl border border-neutral-200 bg-white p-7 shadow-sm sm:p-9">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl" :class="option.iconClass">
              <UIcon :name="option.icon" class="h-6 w-6" />
            </div>
            <p class="mt-6 text-sm font-semibold uppercase tracking-[0.12em] text-neutral-500">{{ option.eyebrow }}</p>
            <h3 class="mt-2 text-2xl font-semibold text-neutral-950">{{ option.title }}</h3>
            <p class="mt-3 leading-7 text-neutral-600">{{ option.description }}</p>
            <UButton v-bind="option.cta" size="lg" class="mt-7 justify-center rounded-xl px-5" />
          </article>
        </div>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-white py-14 sm:py-16">
      <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-12">
        <div class="grid gap-6 lg:grid-cols-[0.75fr_1.25fr] lg:items-center">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Switching from closed SaaS?</p>
            <h2 class="mt-3 text-2xl font-semibold text-neutral-950">Use a direct migration comparison</h2>
          </div>
          <div class="flex flex-wrap gap-2">
            <UButton
              v-for="comparison in closedSaasComparisons"
              :key="comparison.label"
              :to="comparison.to"
              color="neutral"
              variant="outline"
              :label="`${comparison.label} alternative`"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              class="rounded-xl"
            />
          </div>
        </div>
      </div>
    </section>

    <FaqSection
      variant="split"
      eyebrow="FAQ"
      title="Open-source form builder questions"
      description="Clear answers about licenses, Cloud, self-hosting, and where OpnForm fits."
      :faqs="openSourceFaqs"
      id-prefix="open-source-form-builder-faq"
      @contact="contactUs"
    />

    <MarketingCtaPanel
      eyebrow="No credit card, no submission cap"
      title="Build your first form with OpnForm"
      description="See how the builder feels before you compare deployment details. Start free on Cloud, then inspect or self-host the source when you need more control."
    >
      <UButton
        :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
        size="lg"
        color="neutral"
        label="Create a free form"
        trailing-icon="i-heroicons-arrow-right-20-solid"
        class="justify-center rounded-xl bg-white px-5 text-blue-700 hover:bg-blue-50"
      />
      <UButton
        :to="opnformConfig.links.github_url"
        target="_blank"
        size="lg"
        color="neutral"
        label="View GitHub"
        trailing-icon="i-simple-icons-github"
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
import MarketingNumberedSteps from "~/components/pages/marketing/MarketingNumberedSteps.vue"
import MarketingProductShowcase from "~/components/pages/marketing/MarketingProductShowcase.vue"
import MarketingSplitSection from "~/components/pages/marketing/MarketingSplitSection.vue"
import PillarComparisonTable from "~/components/pages/pillars/PillarComparisonTable.vue"
import {
  closedSaasComparisons,
  openSourceCompetitorColumns,
  openSourceCompetitorRows,
  openSourceCompetitorSources,
  openSourceDetailedComparisons,
} from "~/data/marketing/open-source-competitors.js"
import { useIsAuthenticated } from "~/composables/useAuthFlow"
import opnformConfig from "~/opnform.config.js"

definePageMeta({
  layout: "default",
})

defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: "Open Source Form Builder for Teams",
  description:
    "Build unlimited forms and workflows with OpnForm, the open-source no-code form builder. Start free on Cloud or self-host the AGPLv3 core.",
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const { data: githubRepository } = await useFetch("/api/github-stars", {
  key: "opnform-github-stars",
})

const githubStars = computed(() => {
  const stars = githubRepository.value?.stars
  return Number.isInteger(stars) && stars > 0 ? stars : null
})
const getGithubStarsAnimationStart = (target) => {
  if (!target) return null
  return Math.max(0, target - Math.min(600, Math.round(target * 0.2)))
}
const displayedGithubStars = ref(getGithubStarsAnimationStart(githubStars.value))
const githubStarsFormatter = new Intl.NumberFormat("en-US")
let githubStarsAnimationFrame = null

const formattedGithubStars = computed(() => {
  if (!Number.isInteger(displayedGithubStars.value)) return null
  return githubStarsFormatter.format(displayedGithubStars.value)
})

const githubStarsLabel = computed(() => {
  if (!githubStars.value) return "View the OpnForm repository on GitHub"

  return `${githubStarsFormatter.format(githubStars.value)} GitHub stars. View the OpnForm repository.`
})

const animateGithubStars = () => {
  const target = githubStars.value
  if (!target || !import.meta.client) return

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    displayedGithubStars.value = target
    return
  }

  if (githubStarsAnimationFrame) cancelAnimationFrame(githubStarsAnimationFrame)

  const start = getGithubStarsAnimationStart(target)
  const duration = 1800
  const startTime = performance.now()

  displayedGithubStars.value = start

  const tick = (currentTime) => {
    const progress = Math.min((currentTime - startTime) / duration, 1)
    const easedProgress = 1 - (1 - progress) ** 5

    displayedGithubStars.value = Math.round(start + (target - start) * easedProgress)

    if (progress < 1) {
      githubStarsAnimationFrame = requestAnimationFrame(tick)
      return
    }

    displayedGithubStars.value = target
    githubStarsAnimationFrame = null
  }

  githubStarsAnimationFrame = requestAnimationFrame(tick)
}

onMounted(animateGithubStars)

onBeforeUnmount(() => {
  if (githubStarsAnimationFrame) cancelAnimationFrame(githubStarsAnimationFrame)
})

const heroProofs = ["No-code builder", "Unlimited submissions", "Cloud or self-hosted", "Logic, API, and webhooks"]

const productFeatures = [
  {
    tone: "blue",
    title: "Build polished forms without code",
    description: "Give marketing, operations, HR, and product teams a visual builder they can use without waiting for engineering.",
    highlights: ["Conditional logic", "Multi-page forms", "Themes and embeds"],
    image: "/img/pages/open-source-form-builder/feature-no-code-builder-960.webp",
    imageSrcset: "/img/pages/open-source-form-builder/feature-no-code-builder-640.webp 640w, /img/pages/open-source-form-builder/feature-no-code-builder-960.webp 960w, /img/pages/open-source-form-builder/feature-no-code-builder-1280.webp 1280w",
    imageAlt: "OpnForm visual builder editing and styling a feedback form",
  },
  {
    tone: "mint",
    reverse: true,
    title: "Collect without response caps",
    description: "Create as many forms as you need and collect unlimited submissions on Cloud and self-hosted editions.",
    highlights: ["Unlimited forms", "Unlimited submissions", "Cloud or self-hosted"],
    image: "/img/pages/open-source-form-builder/feature-unlimited-submissions-960.webp",
    imageSrcset: "/img/pages/open-source-form-builder/feature-unlimited-submissions-640.webp 640w, /img/pages/open-source-form-builder/feature-unlimited-submissions-960.webp 960w, /img/pages/open-source-form-builder/feature-unlimited-submissions-1280.webp 1280w",
    imageAlt: "OpnForm submission inbox collecting responses without a monthly response cap",
  },
  {
    tone: "violet",
    title: "Automate what happens next",
    description: "Send submissions to Google Sheets, Zapier, Stripe, webhooks, or your own systems through the REST API.",
    highlights: ["Native integrations", "Webhooks", "REST API"],
    image: "/img/pages/open-source-form-builder/feature-automations-960.webp",
    imageSrcset: "/img/pages/open-source-form-builder/feature-automations-640.webp 640w, /img/pages/open-source-form-builder/feature-automations-960.webp 960w, /img/pages/open-source-form-builder/feature-automations-1280.webp 1280w",
    imageAlt: "OpnForm automation connecting a form submission to Google Sheets, Zapier, and Stripe",
  },
]

const productProofs = ["File uploads", "Payments", "Custom domains", "Exports and analytics"]

const opnformAdvantages = [
  {
    icon: "i-heroicons-cursor-arrow-rays-20-solid",
    title: "Build without code",
    description: "Let the people who own the workflow create and update forms themselves, with logic, calculations, uploads, payments, and embeds.",
  },
  {
    icon: "i-heroicons-rocket-launch-20-solid",
    title: "Launch without infrastructure",
    description: "Start free on managed Cloud and collect unlimited submissions before deciding whether operating the stack makes sense.",
  },
  {
    icon: "i-heroicons-arrows-right-left-20-solid",
    title: "Connect every submission",
    description: "Use native integrations, webhooks, embeds, and the REST API instead of exporting data by hand or building glue code for every form.",
  },
  {
    icon: "i-heroicons-building-office-2-20-solid",
    title: "Add governance when needed",
    description: "Keep the same product as your team grows, then add SSO, roles, audit visibility, branding controls, and priority support with Enterprise.",
  },
]

const enterpriseFeatures = [
  {
    icon: "i-heroicons-finger-print-20-solid",
    title: "SSO and identity",
    description: "Use OIDC, SAML, or LDAP depending on your edition and deployment.",
  },
  {
    icon: "i-heroicons-user-group-20-solid",
    title: "Roles and permissions",
    description: "Control who can view, edit, publish, and manage forms across workspaces.",
  },
  {
    icon: "i-heroicons-clipboard-document-check-20-solid",
    title: "Audit visibility",
    description: "Track important form and submission activity as more people collaborate.",
  },
  {
    icon: "i-heroicons-lifebuoy-20-solid",
    title: "Rollout support",
    description: "Get priority help with migration, deployment, and self-hosting questions.",
  },
]

const deploymentOptions = [
  {
    eyebrow: "Fastest path",
    title: "OpnForm Cloud",
    description: "Launch without operating application servers, databases, backups, or updates. The free plan keeps submissions unlimited.",
    icon: "i-heroicons-cloud-20-solid",
    iconClass: "bg-blue-100 text-blue-700",
    cta: { label: "Create a free form", to: { name: "forms-create-guest" } },
  },
  {
    eyebrow: "Infrastructure ownership",
    title: "Self-hosted OpnForm",
    description: "Run the core product in your environment when data location, procurement, or integration requirements justify the operational work.",
    icon: "i-heroicons-server-stack-20-solid",
    iconClass: "bg-emerald-100 text-emerald-700",
    cta: { label: "Explore self-hosting", to: { name: "self-hosted-form-builder" }, color: "neutral", variant: "outline" },
  },
]

const openSourceFaqs = [
  {
    question: "Is OpnForm really open source?",
    answer: "Yes. The OpnForm core is available under AGPLv3 and can be inspected, modified, and self-hosted under that license. Advanced Enterprise features use a separate commercial license, which makes OpnForm an open-core product.",
  },
  {
    question: "Which open-source form builders are closest to OpnForm?",
    answer: "Formbricks and HeyForm are the closest modern product comparisons. LimeSurvey is stronger for research-heavy survey programs, while Form.io is aimed at developers building form-based applications and data APIs.",
  },
  {
    question: "Why choose OpnForm over another open-source form builder?",
    answer: "Choose OpnForm when non-technical teams need a polished general-purpose form builder and developers still need APIs, webhooks, embeds, and a credible path from managed Cloud to self-hosting. It is especially strong for registrations, applications, lead capture, payments, internal workflows, and embedded forms.",
  },
  {
    question: "Can I use OpnForm for free?",
    answer: "Yes. OpnForm Cloud has a free plan with unlimited submissions, and Community self-hosting lets you run the open-source core for up to two users on the instance.",
  },
  {
    question: "Do I need to self-host to benefit from open source?",
    answer: "No. You can use the managed Cloud product while retaining the transparency and future deployment choice of an open-source core. Self-host only when you have a concrete infrastructure requirement.",
  },
  {
    question: "Is OpnForm an open-source Typeform alternative?",
    answer: "Yes for many registration, survey, lead, payment, and workflow use cases. The more useful distinction is that Typeform is closed SaaS, while OpnForm gives you an inspectable core and a self-hosting path.",
  },
  {
    question: "What should I verify before choosing an open-source form builder?",
    answer: "Verify the license, what is included in the community edition, deployment requirements, upgrade process, APIs, integrations, team controls, and the support path you will need in production.",
  },
]

useJsonLd("open-source-form-builder-schema", buildSchemaGraph([
  buildOrganizationSchema(),
  buildWebsiteSchema(),
  buildSoftwareApplicationSchema(),
  buildWebPageSchema({
    name: "Open Source Form Builder for Teams",
    description: "Build unlimited forms and workflows with OpnForm, a polished open-source no-code builder available on Cloud or as an AGPLv3 self-hosted core.",
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
