<template>
  <div>
    <section class="py-12 bg-white">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-3xl mx-auto text-center">
          <h1 class="text-5xl font-semibold tracking-tight text-neutral-950">
            Simple pricing
            <br class="hidden sm:block">
            based on your needs
          </h1>
          <p class="max-w-2xl mx-auto mt-4 text-lg font-medium leading-7 text-neutral-600">
            No locked-in contracts. Upgrade or cancel anytime.
          </p>

          <div class="flex items-center justify-center gap-3 mt-10">
            <span class="text-sm font-semibold text-neutral-700">
              Monthly
            </span>
            <button
              type="button"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="pricingIsYearly ? 'bg-blue-600' : 'bg-neutral-200'"
              @click="pricingIsYearly = !pricingIsYearly"
              aria-label="Toggle yearly billing"
            >
              <span
                class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                :class="pricingIsYearly ? 'translate-x-5' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm font-semibold text-neutral-700">
              Annually
            </span>
            <span class="hidden sm:inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 rounded-full">
              Save 15% with yearly billing
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 mt-12 lg:grid-cols-4">
          <!-- Free -->
          <div class="p-6 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:bolt-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Free</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              Start collecting unlimited responses with no friction.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">$0</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                v-if="!authenticated"
                class="w-full justify-center"
                variant="soft"
                :to="{ name: 'register' }"
                label="Get started free"
              />
              <UButton
                v-else
                class="w-full justify-center"
                :to="{ name: 'home' }"
                label="Go to app"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Includes</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Unlimited forms & submissions
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  File uploads (basic quota)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Form logic & validation
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Computed fields (calculations)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Pre-fills, URL parameters
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Multi-user access (all admins, no roles)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  1 workspace only
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Branding required
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Community support
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  API
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Basic integrations
                </li>
              </ul>
            </div>
          </div>

          <!-- Pro (Most popular) -->
          <div class="relative p-6 bg-white border-2 shadow-sm rounded-3xl border-blue-600">
            <div class="absolute top-6 right-6">
              <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 rounded-full ring-1 ring-blue-200">
                Most popular
              </span>
            </div>

            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:sparkles-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Pro</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              A polished, professional experience for serious work.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                  ${{ pricingIsYearly ? PLAN_PRICING.pro.yearly : PLAN_PRICING.pro.monthly }}
                </span>
                <span class="pb-1 text-sm font-semibold text-neutral-600">/mo</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                class="w-full justify-center"
                label="Get started free"
                @click.prevent="handleProCta"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Everything in Free, plus</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Remove branding
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Custom domains
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Custom SMTP
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Discord, Slack, Telegram
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Password-protected forms
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Form expiration
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Captcha
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Multiple workspaces
                </li>
              </ul>
            </div>
          </div>

          <!-- Business -->
          <div class="p-6 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:building-office-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Business</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              Built for teams and agencies managing forms at scale.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                  ${{ pricingIsYearly ? PLAN_PRICING.business.yearly : PLAN_PRICING.business.monthly }}
                </span>
                <span class="pb-1 text-sm font-semibold text-neutral-600">/mo</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                class="w-full justify-center"
                variant="soft"
                label="Get started free"
                @click.prevent="handleBusinessCta"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Everything in Pro, plus</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Multi-user with roles & permissions
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Advanced branding (CSS, fonts, favicons)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Higher file upload size limits
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Priority support
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Partial submissions
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Versioning
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Analytics dashboard
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Advanced integrations
                </li>
              </ul>
            </div>
          </div>

          <!-- Enterprise -->
          <div class="p-6 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:globe-alt-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Enterprise</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              Enterprise-grade security, compliance, and control.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                  ${{ pricingIsYearly ? PLAN_PRICING.enterprise.yearly : PLAN_PRICING.enterprise.monthly }}+
                </span>
                <span class="pb-1 text-sm font-semibold text-neutral-600">/mo</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                class="w-full justify-center"
                variant="soft"
                label="Request a quote"
                @click.prevent="contactUs"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Everything in Business, plus</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  SSO (SAML, OIDC, LDAP)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Audit logs & compliance features
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  External storage support
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  White-label hosting option
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  SLA & onboarding support
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="flex items-start gap-4 max-w-3xl p-6 mx-auto bg-yellow-50 ring ring-inset ring-yellow-200 rounded-3xl">
        <UIcon name="i-heroicons-shield-check" class="h-8 w-8 shrink-0 text-yellow-500" />
        <div>
          <p class="text-lg font-semibold text-yellow-600">
            Nonprofit & Student Discount — 50%
          </p>
          <p class="mt-1 text-base font-medium leading-7 text-yellow-600">
            Whether your nonprofit is large or small, OpnForm's online Form
            Builder helps your organization help others. It takes just a few
            minutes to create and publish your forms online. As an exclusive
            benefit, we offer nonprofits & students a 50-percent discount!
          </p>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <TrustedTeams />
    </section>

    <section class="py-12 bg-white">
      <FeatureComparison />
    </section>

    <section class="py-12 bg-white">
      <Testimonials />
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-3xl mx-auto text-center">
          <h2 class="text-4xl font-semibold tracking-tight text-neutral-950 sm:text-5xl">
            Self-host OpnForm
          </h2>
          <p class="max-w-2xl mx-auto mt-4 text-base font-medium leading-7 text-neutral-600 sm:text-lg sm:leading-8">
            The self-hosted commercial licenses are the same price as hosted plans.
          </p>
        </div>

        <div class="max-w-5xl mx-auto mt-12 space-y-8 sm:mt-16">
          <div class="p-8 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-12">
              <div>
                <div class="flex items-center gap-3">
                  <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                    <Icon class="w-5 h-5 text-blue-600" name="heroicons:users-20-solid" />
                  </span>
                  <h3 class="text-xl font-semibold text-neutral-950">
                    Community Edition
                  </h3>
                </div>

                <p class="mt-4 text-base font-medium leading-7 text-neutral-600">
                  Perfect for individuals and teams who want full control and community-driven software.
                </p>

                <div class="mt-8">
                  <p class="text-4xl font-semibold tracking-tight text-neutral-950">
                    Free OSS
                  </p>
                </div>

                <div class="mt-8">
                  <UButton
                    variant="outline"
                    label="Request a quote"
                    @click.prevent="contactUs"
                  />
                </div>
              </div>

              <div class="lg:pt-2">
                <ul class="space-y-4 text-sm font-medium text-neutral-700">
                  <li
                    v-for="feature in communityEditionFeatures"
                    :key="feature"
                    class="flex gap-3"
                  >
                    <Icon
                      class="w-5 h-5 text-emerald-600"
                      name="heroicons:check-20-solid"
                    />
                    {{ feature }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="p-8 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-12">
              <div>
                <div class="flex items-center gap-3">
                  <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                    <Icon class="w-5 h-5 text-blue-600" name="heroicons:shield-check-20-solid" />
                  </span>
                  <h3 class="text-xl font-semibold text-neutral-950">
                    Enterprise License
                  </h3>
                </div>

                <p class="mt-4 text-base font-medium leading-7 text-neutral-600">
                  Built for organizations that need governance, customization, and long-term reliability.
                </p>

                <div class="mt-8">
                  <p class="flex items-end gap-3">
                    <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                      $1,990
                    </span>
                    <span class="pb-2 text-sm font-semibold text-neutral-600">
                      /year per instance
                    </span>
                  </p>
                </div>

                <div class="mt-8">
                  <UButton
                    variant="outline"
                    label="Request a quote"
                    @click.prevent="contactUs"
                  />
                </div>
              </div>

              <div class="lg:pt-2">
                <ul class="space-y-4 text-sm font-medium text-neutral-700">
                  <li
                    v-for="feature in enterpriseLicenseFeatures"
                    :key="feature"
                    class="flex gap-3"
                  >
                    <Icon
                      class="w-5 h-5 text-emerald-600"
                      name="heroicons:check-20-solid"
                    />
                    {{ feature }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white sm:py-16 lg:py-20 xl:py-24">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-3xl mx-auto text-center">
          <p class="text-sm font-semibold tracking-wide text-blue-600 uppercase">
            Frequently Asked Questions
          </p>
          <h2 class="mt-4 text-4xl font-semibold tracking-tight text-neutral-950 sm:text-5xl">
            Everything you need to
            <br class="hidden sm:block">
            know
          </h2>
          <p class="max-w-2xl mx-auto mt-4 text-base font-medium leading-7 text-neutral-600 sm:text-lg sm:leading-8">
            Find answers about plans, onboarding, roles, and how teams use our tool every day.
          </p>
        </div>

        <div class="max-w-4xl mx-auto mt-12 sm:mt-16">
          <div class="space-y-4">
            <div
              v-for="(q, i) in faqs"
              :key="q.question"
              class="bg-neutral-50 rounded-2xl"
            >
              <button
                type="button"
                class="w-full px-6 py-5 text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                @click="toggleFaq(i)"
              >
                <div class="flex items-center gap-4">
                  <span class="w-10 text-sm font-semibold text-neutral-400">
                    {{ String(i + 1).padStart(2, '0') }}
                  </span>
                  <div class="flex items-center justify-between flex-1 gap-4">
                    <p class="text-base font-semibold text-neutral-900">
                      {{ q.question }}
                    </p>
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white text-neutral-500">
                      <Icon
                        v-if="openFaqIndex !== i"
                        class="w-5 h-5"
                        name="heroicons:plus-20-solid"
                      />
                      <Icon
                        v-else
                        class="w-5 h-5"
                        name="heroicons:x-mark-20-solid"
                      />
                    </span>
                  </div>
                </div>
              </button>

              <div
                v-if="openFaqIndex === i"
                class="px-6 pb-6"
              >
                <div class="pl-14">
                  <p class="text-sm font-medium leading-6 text-neutral-600">
                    {{ q.answer }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-10 text-center sm:mt-12">
            <p class="text-sm font-medium text-neutral-600">
              Didn't find the answer?
              <a
                href="#"
                class="font-semibold text-blue-600 hover:underline"
                @click.prevent="contactUs"
              >Contact Us</a>
            </p>
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter />
  </div>
</template>

<script setup>
import FeatureComparison from "~/components/pages/pricing/FeatureComparison.vue"
import { PLAN_PRICING } from "~/composables/usePlanFeatures"
import { useIsAuthenticated } from "~/composables/useAuthFlow"

definePageMeta({
  layout: "default",
  middleware: ["self-hosted"],
})

useOpnSeoMeta({
  title: "Pricing",
  description:
    "All of our core features are free, and there is no quantity limit. You can also created more advanced and customized forms with OpnForms Pro.",
})

const { openSubscriptionModal } = useAppModals()
const { isAuthenticated: authenticated } = useIsAuthenticated()

const pricingIsYearly = ref(true)

const communityEditionFeatures = [
  "Unlimited forms & submissions",
  "File uploads, logic, computed fields",
  "Pre-fills, URL parameters",
  "Multi-user access allowed (all admins, no roles)",
  "Unlimited workspaces",
  "Branding required",
  "Community support",
]

const enterpriseLicenseFeatures = [
  "Branding removal",
  "Custom SMTP",
  "Advanced integrations (when ready)",
  "Multi-workspace support",
  "Multi-user with roles & permissions",
  "SSO, audit logs, compliance features",
  "White-labeling & theming",
  "Packaged updates + migration tooling",
  "Priority support",
]

const openFaqIndex = ref(2)
const faqs = [
  {
    question: "Is there any submission limit?",
    answer:
      "No — submissions are unlimited on all plans. The Free plan gives you access to most features without restrictive usage caps.",
  },
  {
    question: "Are integrations included in the Free plan?",
    answer:
      "Yes — basic integrations like webhooks and API access are available on the Free plan. Some advanced integrations are available on higher tiers.",
  },
  {
    question: "Can I hide the OpnForm branding?",
    answer:
      "Yes. You can remove the “Made with OpnForm” footer and add your own branding on the Pro plan or higher.",
  },
  {
    question: "Is there a difference between monthly and yearly billing?",
    answer:
      "Yearly billing is discounted compared to paying monthly. You’ll be billed once per year and save versus the monthly plan.",
  },
  {
    question: "How can I pay for my subscription?",
    answer:
      "We support card payments via Stripe. You’ll get invoices/receipts automatically for your records.",
  },
  {
    question: "Do you offer discounts for non-profits or education?",
    answer:
      "Yes — we offer discounted pricing for non-profits and students. Contact us and we’ll help you get set up.",
  },
  {
    question: "Can I cancel my subscription anytime?",
    answer:
      "Yes. You can cancel anytime from the billing portal. Your subscription remains active until the end of the current billing period.",
  },
  {
    question: "Can I switch between plans?",
    answer:
      "Yes — you can upgrade or downgrade at any time. Changes apply immediately, and billing adjusts accordingly.",
  },
  {
    question: "Do you offer refunds?",
    answer:
      "If something isn’t working as expected, reach out and we’ll do our best to help. Refunds are handled case-by-case.",
  },
  {
    question: "What’s included when I self-host OpnForm?",
    answer:
      "Self-hosting includes the core OpnForm app and lets you run it on your own infrastructure. Some hosted-only services (like managed billing) may not apply.",
  },
  {
    question: "Do you offer a free trial of paid features?",
    answer:
      "We don’t currently offer an automated trial, but you can contact us if you’d like to evaluate a paid plan for your team.",
  },
  {
    question: "Is there an API, and is it free?",
    answer:
      "Yes — OpnForm has an API and API access tokens. They’re available on the Free plan, with higher tiers unlocking more advanced capabilities.",
  },
  {
    question: "Can I collaborate with my team?",
    answer:
      "Yes — multi-user collaboration is supported. Higher tiers add roles and permissions for larger teams.",
  },
]

const handlePlanCta = (plan) => {
  if (!authenticated.value) {
    return navigateTo({ name: "register" })
  }
  openSubscriptionModal({ plan, yearly: pricingIsYearly.value })
}

const handleProCta = () => handlePlanCta("pro")
const handleBusinessCta = () => handlePlanCta("business")

const contactUs = () => {
  useCrisp().openAndShowChat()
}

const toggleFaq = (index) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}
</script>
