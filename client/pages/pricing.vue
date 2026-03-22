<template>
  <div>
    <section class="bg-white">
      <div class="relative">
        <div class="px-8 lg:px-12 py-14 sm:py-28 text-center relative z-2">
          <h1 class="text-4xl sm:text-[56px] sm:leading-16 tracking-[-1%] font-semibold text-gray-950">
            Simple pricing
            <br class="hidden sm:block" />
            based on your needs
          </h1>
          <p class="text-lg sm:text-xl leading-7 tracking-[-1.5%] sm:leading-8 font-normal text-gray-600">
            No locked-in contracts. Upgrade or cancel anytime.
          </p>
        </div>
        <div class="w-full h-full bg-linear-to-b from-white from-20% via-blue-50 via-50% to-white to-80% absolute inset-0"></div>
      </div>
      <div class="px-8 lg:px-12">
        <div class="flex justify-center">
          <div class="w-full max-w-[240px]">
            <MonthlyYearlySelector v-model="pricingIsYearly" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 max-w-266 mx-auto">
          <!-- Free -->
          <div class="p-6 sm:p-8 bg-white border shadow-sm rounded-3xl border-gray-200">
            <div class="flex items-center gap-4">
              <span class="inline-flex items-center justify-center w-5 h-5">
                <Icon
                  class="w-3.75 h-[17.76px] text-blue-500"
                  name="heroicons:bolt-20-solid"
                />
              </span>
              <h3 class="text-xl leading-7 font-medium text-gray-950">Free</h3>
            </div>

            <p class="mt-4 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-600">
              Start collecting unlimited responses with no friction.
            </p>

            <div class="mt-6">
              <p class="flex items-center gap-2">
                <span class="text-3xl sm:text-[40px] sm:leading-12 font-medium tracking-[-1%] text-gray-950">{{ planPriceDisplay.free }}</span>
              </p>
            </div>

            <div class="mt-6 flex justify-center sm:block sm:justify-normal">
              <UButton
                v-if="!authenticated"
                class="w-fit sm:w-full justify-center px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                variant="outline"
                :to="{ name: 'register' }"
                label="Get started free"
                color="neutral"
              />
              <UButton
                v-else
                color="neutral"
                class="w-fit sm:w-full justify-center px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                :to="{ name: 'home' }"
                label="Go to app"
              />
            </div>

            <div class="mt-6">
              <p class="text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950">
                Includes
              </p>
              <ul class="mt-4 space-y-4 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-700">
                <li
                  v-for="feature in planFeatures.free"
                  :key="feature"
                  class="flex items-center gap-2.5"
                >
                  <Icon
                    class="w-4 h-5 text-emerald-600"
                    name="heroicons:check-20-solid"
                  />
                  {{ feature }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Pro -->
          <div class="relative p-6 sm:p-8 bg-white border-2 shadow-sm rounded-3xl border-blue-500">
            <div class="absolute top-6 right-6">
              <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 rounded-full ring-1 ring-blue-200">
                Most popular
              </span>
            </div>

            <div class="flex items-center gap-4">
              <span class="inline-flex items-center justify-center w-5 h-5">
                <Icon
                  class="w-3.75 h-[17.76px] text-blue-500"
                  name="heroicons:sparkles-20-solid"
                />
              </span>
              <h3 class="text-xl leading-7 font-medium text-gray-950">Pro</h3>
            </div>

            <p class="mt-4 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-600">
              A polished, professional experience for serious work.
            </p>

            <div class="mt-6">
              <p class="flex items-center gap-2">
                <span class="text-3xl sm:text-[40px] sm:leading-12 font-medium tracking-[-1%] text-gray-950">
                  {{ planPriceDisplay.pro }}
                </span>
                <span class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600">/mo</span>
              </p>
            </div>

            <div class="mt-6 flex justify-center sm:block sm:justify-normal">
              <UButton
                class="w-fit sm:w-full justify-center px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                label="Get started free"
                :loading="isPlanLoading('pro')"
                @click.prevent="handleProCta"
              />
            </div>

            <div class="mt-6">
              <p class="text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950">
                Everything in Free, plus
              </p>
              <ul class="mt-4 space-y-4 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-700">
                <li
                  v-for="feature in planFeatures.pro"
                  :key="feature"
                  class="flex items-center gap-2.5"
                >
                  <Icon
                    class="w-4 h-5 text-emerald-600"
                    name="heroicons:check-20-solid"
                  />
                  {{ feature }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Business -->
          <div class="p-6 sm:p-8 bg-white border shadow-sm rounded-3xl border-gray-200">
            <div class="flex items-center gap-4">
              <span class="inline-flex items-center justify-center w-5 h-5">
                <Icon
                  class="w-3.75 h-[17.76px] text-blue-500"
                  name="heroicons:building-office-20-solid"
                />
              </span>
              <h3 class="text-xl leading-7 font-medium text-gray-950">
                Business
              </h3>
            </div>

            <p class="mt-4 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-600">
              Built for teams and agencies managing forms at scale.
            </p>

            <div class="mt-6">
              <p class="flex items-center gap-2">
                <span
                  class="text-3xl sm:text-[40px] sm:leading-12 font-medium tracking-[-1%] text-gray-950"
                >
                  {{ planPriceDisplay.business }}
                </span>
                <span class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600">/mo</span>
              </p>
            </div>

            <div class="mt-6 flex justify-center sm:block sm:justify-normal">
              <UButton
                class="w-fit sm:w-full justify-center px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                variant="outline"
                label="Get started free"
                color="neutral"
                :loading="isPlanLoading('business')"
                @click.prevent="handleBusinessCta"
              />
            </div>

            <div class="mt-6">
              <p class="text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950">
                Everything in Pro, plus
              </p>
              <ul class="mt-4 space-y-4 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-700">
                <li
                  v-for="feature in planFeatures.business"
                  :key="feature"
                  class="flex items-center gap-2.5"
                >
                  <Icon
                    class="w-4 h-5 text-emerald-600"
                    name="heroicons:check-20-solid"
                  />
                  {{ feature }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Enterprise -->
          <div class="p-6 sm:p-8 bg-white border shadow-sm rounded-3xl border-gray-200">
            <div class="flex items-center gap-4">
              <span class="inline-flex items-center justify-center w-5 h-5">
                <Icon
                  class="w-3.75 h-[17.76px] text-blue-500"
                  name="heroicons:globe-alt-20-solid"
                />
              </span>
              <h3 class="text-xl leading-7 font-medium text-gray-950">
                Enterprise
              </h3>
            </div>

            <p class="mt-4 text-sm font-medium leading-5 tracking-[-0.6%] text-gray-600">
              Enterprise-grade security, compliance, and control.
            </p>

            <div class="mt-6">
              <p class="flex items-center gap-2">
                <span class="text-3xl sm:text-[40px] sm:leading-12 font-medium tracking-[-1%] text-gray-950">
                  {{ planPriceDisplay.enterprise }}
                </span>
                <span class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600">/mo</span>
              </p>
            </div>

            <div class="mt-6 flex justify-center sm:block sm:justify-normal">
              <UButton
                class="w-fit sm:w-full justify-center px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                variant="outline"
                label="Request a quote"
                color="neutral"
                @click.prevent="contactUs"
              />
            </div>

            <div class="mt-6">
              <p class="text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950">
                Everything in Business, plus
              </p>
              <ul class="mt-4 space-y-4 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-700">
                <li
                  v-for="feature in planFeatures.enterprise"
                  :key="feature"
                  class="flex items-center gap-2.5"
                >
                  <Icon
                    class="w-4 h-5 text-emerald-600"
                    name="heroicons:check-20-solid"
                  />
                  {{ feature }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="pt-16 bg-white">
      <TrustedTeams />
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <FeatureComparison />
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <Testimonials />
    </section>

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div class="text-center">
          <h2 class="text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950">
            Self-host OpnForm
          </h2>
          <p class="mt-4 text-base font-normal tracking-[-1.1%] leading-7 text-gray-600">
            The self-hosted commercial licenses are the same price as hosted plans.
          </p>
        </div>

        <div class="mt-12 sm:mt-16 space-y-6">
          <div class="p-6 sm:p-8 bg-white border shadow-sm rounded-3xl border-gray-200">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <div>
                <div class="flex items-center gap-4">
                  <span class="inline-flex items-center justify-center w-5 h-5">
                    <Icon
                      class="w-5 h-5 text-blue-500"
                      name="heroicons:users-20-solid"
                    />
                  </span>
                  <h3 class="text-xl leading-7 font-medium text-gray-950">
                    Community Edition
                  </h3>
                </div>

                <p class="mt-4 text-base font-medium tracking-[-1.1%] leading-7 text-gray-600">
                  Perfect for individuals and teams who want full control and community-driven software.
                </p>

                <div class="mt-6">
                  <p class="text-3xl sm:text-[40px] sm:leading-12 font-medium tracking-[-1%] text-gray-950">
                    Free OSS
                  </p>
                </div>

                <div class="mt-6">
                  <UButton
                    size="lg"
                    variant="outline"
                    color="neutral"
                    label="Request a quote"
                    @click.prevent="contactUs"
                    class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                  />
                </div>
              </div>

              <div>
                <ul class="space-y-4 text-base leading-7 tracking-[-1.1%] font-medium text-gray-700">
                  <li
                    v-for="feature in communityEditionFeatures"
                    :key="feature"
                    class="flex items-center gap-3"
                  >
                    <Icon
                      class="w-4 h-5 text-emerald-600"
                      name="heroicons:check-20-solid"
                    />
                    {{ feature }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="p-6 sm:p-8 bg-white border shadow-sm rounded-3xl border-gray-200">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <div>
                <div class="flex items-center gap-4">
                  <span class="inline-flex items-center justify-center w-5 h-5">
                    <Icon
                      class="w-5 h-5 text-blue-600"
                      name="heroicons:shield-check-20-solid"
                    />
                  </span>
                  <h3 class="text-xl leading-7 font-medium text-gray-950">
                    Enterprise License
                  </h3>
                </div>

                <p class="mt-4 text-base font-medium tracking-[-1.1%] leading-7 text-gray-600">
                  Built for organizations that need governance, customization, and long-term reliability.
                </p>

                <div class="mt-6">
                  <p class="flex items-center gap-3">
                    <span class="text-3xl sm:text-[40px] sm:leading-12 font-medium tracking-[-1%] text-gray-950">
                      $1,990
                    </span>
                    <span class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600">
                      /year per instance
                    </span>
                  </p>
                </div>

                <div class="mt-6">
                  <UButton
                    size="lg"
                    variant="outline"
                    color="neutral"
                    label="Request a quote"
                    @click.prevent="contactUs"
                    class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                  />
                </div>
              </div>

              <div>
                <ul class="space-y-4 text-base leading-7 tracking-[-1.1%] font-medium text-gray-700">
                  <li
                    v-for="feature in enterpriseLicenseFeatures"
                    :key="feature"
                    class="flex items-center gap-3"
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

    <section class="py-14 sm:py-28 px-8 lg:px-12 bg-white">
      <div class="mx-auto max-w-266">
        <div class="text-center">
          <p class="text-base leading-7 tracking-[-1.1%] font-semibold text-blue-600">
            Frequently Asked Questions
          </p>
          <h2 class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950">
            Everything you need to
            <br class="hidden sm:block" />
            know
          </h2>
          <p class="text-base leading-7 font-normal tracking-[-1.1%] text-gray-600">
            Find answers about plans, onboarding, roles, and how teams use our tool every day.
          </p>
        </div>

        <div class="mt-12 sm:mt-16">
          <div class="space-y-4 sm:space-y-5">
            <div
              v-for="(q, i) in faqs"
              :key="q.question"
              class="bg-gray-50 rounded-3xl transition-colors duration-200"
            >
              <button
                type="button"
                class="w-full p-6 text-left rounded-3xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                @click="toggleFaq(i)"
              >
                <div class="flex items-center gap-4 sm:gap-16">
                  <span
                    class="w-6 text-lg leading-8 tracking-[-1.5%] font-medium transition-colors duration-200"
                    :class="openFaqIndex === i ? 'text-gray-700' : 'text-gray-400'"
                  >
                    {{ String(i + 1).padStart(2, "0") }}
                  </span>
                  <div class="flex items-center justify-between flex-1 gap-8 sm:gap-16">
                    <p
                      class="text-lg leading-8 tracking-[-1.5%] font-medium transition-colors duration-200"
                      :class="openFaqIndex === i ? 'text-gray-900' : 'text-gray-600'"
                    >
                      {{ q.question }}
                    </p>
                    <span
                      class="inline-flex items-center justify-center w-6 h-6 rounded-full transition-colors duration-200"
                      :class="openFaqIndex === i ? 'text-gray-700' : 'text-gray-400'"
                    >
                      <Icon
                        v-if="openFaqIndex !== i"
                        class="w-6 h-6"
                        name="heroicons:plus-20-solid"
                      />
                      <Icon
                        v-else
                        class="w-6 h-6"
                        name="heroicons:x-mark-20-solid"
                      />
                    </span>
                  </div>
                </div>
              </button>

              <div
                v-show="openFaqIndex === i"
                class="faq-answer px-6"
                :class="openFaqIndex === i ? 'faq-answer-open pb-6' : 'faq-answer-closed pb-0'"
              >
                <div class="pl-10 sm:pl-21 overflow-hidden">
                  <p class="text-sm font-medium leading-6 text-gray-600 pt-0.5">
                    {{ q.answer }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-12 text-center sm:mt-16">
            <p class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600">
              Didn't find the answer?
              <a
                href="#"
                class="text-blue-600 hover:underline"
                @click.prevent="contactUs"
                >Contact Us</a
              >
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

const { isAuthenticated: authenticated } = useIsAuthenticated()
const { getPlanPrice } = useBillingUpsell()
const { startCheckout, isPlanLoading } = useStripeCheckout()

const pricingIsYearly = ref(true)

const formatPlanPrice = (plan) => {
  const price = getPlanPrice(plan, pricingIsYearly.value)
  if (price == null) return null
  const suffix = plan === 'enterprise' ? '+' : ''
  return `$${price}${suffix}`
}

const planPriceDisplay = computed(() => ({
  free: formatPlanPrice('free'),
  pro: formatPlanPrice('pro'),
  business: formatPlanPrice('business'),
  enterprise: formatPlanPrice('enterprise'),
}))

const planFeatures = {
  free: [
    "Unlimited forms & submissions",
    "File uploads (basic quota)",
    "Form logic & validation",
    "Computed fields (calculations)",
    "Pre-fills, URL parameters",
    "Multi-user access (all admins, no roles)",
    "1 workspace only",
    "Branding required",
    "Community support",
    "API",
    "Basic integrations",
  ],
  pro: [
    "Remove branding",
    "Custom domains",
    "Custom SMTP",
    "Discord, Slack, Telegram",
    "Password-protected forms",
    "Form expiration",
    "Captcha",
    "Multiple workspaces",
  ],
  business: [
    "Multi-user with roles & permissions",
    "Advanced branding (CSS, fonts, favicons)",
    "Higher file upload size limits",
    "Priority support",
    "Partial submissions",
    "Versioning",
    "Analytics dashboard",
    "Advanced integrations",
  ],
  enterprise: [
    "SSO (SAML, OIDC, LDAP)",
    "Audit logs & compliance features",
    "External storage support",
    "White-label hosting option",
    "SLA & onboarding support",
  ],
}

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
  {
    question: "Can I get a Nonprofit or Student Discount?",
    answer:
      "Yes — we offer a 50% discount for registered nonprofits and students. Contact us to verify your eligibility and get set up.",
  },
]

const handlePlanCta = (plan) => {
  if (!authenticated.value) {
    return navigateTo({ name: "register" })
  }
  return startCheckout(plan, { yearly: pricingIsYearly.value })
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

<style scoped>
.faq-answer {
  display: grid;
  transition: grid-template-rows 180ms ease, opacity 180ms ease, padding-bottom 180ms ease;
}

.faq-answer-open {
  grid-template-rows: 1fr;
  opacity: 1;
}

.faq-answer-closed {
  grid-template-rows: 0fr;
  opacity: 0;
}
</style>
