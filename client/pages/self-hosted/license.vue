<template>
  <div>
    <section class="bg-white">
      <div class="relative">
        <div class="relative z-2 mx-auto max-w-5xl px-8 py-14 text-center sm:py-24 lg:px-12">
          <UBadge
            color="primary"
            variant="subtle"
            class="mb-5"
          >
            Self-hosted Enterprise
          </UBadge>
          <h1 class="text-4xl font-semibold tracking-[-1%] text-neutral-950 sm:text-[56px] sm:leading-16">
            Buy a self-hosted license
          </h1>
          <p class="mx-auto mt-4 max-w-2xl text-lg font-normal leading-7 tracking-[-1.5%] text-neutral-600 sm:text-xl sm:leading-8">
            Unlock Enterprise features for your self-hosted OpnForm instance.
            Checkout is handled by Stripe and your license key is delivered by email.
          </p>
        </div>
        <div class="pointer-events-none absolute inset-0 h-full w-full bg-linear-to-b from-white from-20% via-blue-50 via-50% to-white to-80%" />
      </div>

      <div class="px-8 pb-16 lg:px-12">
        <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_420px]">
          <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-2xl font-semibold tracking-[-1%] text-neutral-950">
              What is included
            </h2>
            <p class="mt-3 text-base font-medium leading-7 tracking-[-1.1%] text-neutral-600">
              Self-hosted Enterprise is for teams that want to run OpnForm on
              their own infrastructure while unlocking advanced workspace,
              identity, and governance controls.
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <div
                v-for="feature in licenseFeatures"
                :key="feature.title"
                class="rounded-2xl border border-neutral-200 bg-neutral-50/60 p-4"
              >
                <Icon
                  :name="feature.icon"
                  class="h-5 w-5 text-blue-600"
                />
                <h3 class="mt-3 text-base font-semibold text-neutral-950">
                  {{ feature.title }}
                </h3>
                <p class="mt-1 text-sm leading-6 text-neutral-600">
                  {{ feature.description }}
                </p>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-blue-200 bg-white p-6 shadow-[0_18px_50px_rgba(59,130,246,0.12)] sm:p-8">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-2xl font-semibold tracking-[-1%] text-neutral-950">
                  Enterprise License
                </h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">
                  One license per self-hosted instance.
                </p>
              </div>
              <Icon
                name="heroicons:shield-check-20-solid"
                class="h-7 w-7 shrink-0 text-blue-600"
              />
            </div>

            <div class="mt-6">
              <MonthlyYearlySelector
                v-model="isYearly"
                :show-savings-badge="false"
              />
            </div>

            <div class="mt-8">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-[-1%] text-neutral-950">
                  ${{ monthlyDisplayPrice }}
                </span>
                <span class="pb-1 text-base font-medium leading-7 text-neutral-600">
                  /mo
                </span>
              </p>
              <p class="mt-2 text-sm leading-6 text-neutral-500">
                {{ billingSummary }}
              </p>
            </div>

            <form
              class="mt-8 space-y-4"
              @submit.prevent="startCheckout"
            >
              <TextInput
                name="billingEmail"
                :form="checkoutForm"
                label="Billing email"
                native-type="email"
                autocomplete="email"
                placeholder="you@company.com"
                :required="true"
              />

              <UAlert
                v-if="errorMessage"
                color="error"
                variant="subtle"
                icon="i-heroicons-exclamation-triangle"
                :description="errorMessage"
              />

              <UButton
                type="submit"
                block
                size="lg"
                icon="i-heroicons-credit-card"
                :loading="isLoading"
                :disabled="!checkoutForm.billingEmail || isLoading"
                class="h-12 rounded-2xl font-semibold"
              >
                Continue to Stripe
              </UButton>
            </form>

            <div class="mt-6 rounded-2xl bg-neutral-50 p-4 text-sm leading-6 text-neutral-600">
              Already bought a license? Open your self-hosted instance, go to
              <strong>User Settings</strong>, then activate it from the
              <strong>License</strong> tab.
            </div>

            <UButton
              variant="link"
              color="neutral"
              class="mt-4 p-0 font-medium"
              @click.prevent="contactUs"
            >
              Need invoice or procurement help?
            </UButton>
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter :show-cta="false" />
  </div>
</template>

<script setup>
import MonthlyYearlySelector from "~/components/pages/pricing/MonthlyYearlySelector.vue"

definePageMeta({
  layout: "default",
  middleware: ["self-hosted"],
})

useOpnSeoMeta({
  title: "Self-hosted Enterprise License",
  description: "Buy a self-hosted OpnForm Enterprise license with self-service Stripe checkout.",
})

const alert = useAlert()
const { tiers } = usePlanCatalog()
const { getPlanPrice } = useBillingUpsell()

const isYearly = ref(true)
const isLoading = ref(false)
const errorMessage = ref("")
const checkoutForm = useForm({
  billingEmail: "",
})

const yearlyPrice = computed(() => tiers.value.self_hosted?.price_yearly ?? 1999)
const monthlyPrice = computed(() => tiers.value.self_hosted?.price_monthly ?? 199)
const monthlyDisplayPrice = computed(() => {
  const catalogPrice = getPlanPrice("self_hosted", isYearly.value)
  if (catalogPrice !== null && catalogPrice !== undefined) return catalogPrice

  return isYearly.value ? Math.round(yearlyPrice.value / 12) : monthlyPrice.value
})
const formatUsd = (amount) => new Intl.NumberFormat("en-US").format(amount)
const billingSummary = computed(() => {
  if (isYearly.value) {
    return `Billed yearly at $${formatUsd(yearlyPrice.value)}/year.`
  }

  return `Billed monthly at $${formatUsd(monthlyPrice.value)}/month.`
})

const licenseFeatures = [
  {
    icon: "heroicons:users",
    title: "More than 2 users",
    description: "Add the team members your self-hosted instance needs.",
  },
  {
    icon: "heroicons:shield-check",
    title: "Enterprise SSO",
    description: "Unlock SAML and LDAP controls for centralized access.",
  },
  {
    icon: "heroicons:paint-brush",
    title: "Branding controls",
    description: "Remove OpnForm branding and use advanced workspace branding.",
  },
  {
    icon: "heroicons:document-text",
    title: "Audit and governance",
    description: "Use licensed controls for compliance-oriented teams.",
  },
  {
    icon: "heroicons:envelope",
    title: "Workspace SMTP",
    description: "Configure dedicated SMTP settings for individual workspaces.",
  },
  {
    icon: "heroicons:chat-bubble-left-right",
    title: "Priority support",
    description: "Get help for your self-hosted Enterprise deployment.",
  },
]

function startCheckout() {
  if (!checkoutForm.billingEmail || isLoading.value) return

  isLoading.value = true
  errorMessage.value = ""
  const cloudApiUrl = useRuntimeConfig().public.licenseApiEndpoint

  $fetch(`${cloudApiUrl}/licenses/create`, {
    method: "POST",
    body: {
      billingEmail: checkoutForm.billingEmail,
      plan: "self_hosted",
      period: isYearly.value ? "yearly" : "monthly",
    },
  }).then((response) => {
    window.location.href = response.checkoutUrl
  }).catch((error) => {
    errorMessage.value = error?.data?.error
      || error?.data?.message
      || "Failed to start checkout. Please try again."
    alert.error(errorMessage.value)
  }).finally(() => {
    isLoading.value = false
  })
}

function contactUs() {
  useCrisp().openAndShowChat()
}
</script>
