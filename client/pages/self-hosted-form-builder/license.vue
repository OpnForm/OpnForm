<template>
  <main class="flex-1 overflow-hidden bg-[#f7f9fc]">
    <section class="relative border-b border-neutral-200">
      <div
        aria-hidden="true"
        class="absolute inset-0 opacity-70 [background-image:linear-gradient(to_right,#dbe4f0_1px,transparent_1px),linear-gradient(to_bottom,#dbe4f0_1px,transparent_1px)] [background-size:32px_32px] [mask-image:linear-gradient(to_bottom,black,transparent_90%)]"
      />
      <div class="relative mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16 lg:px-12 lg:py-20">
        <NuxtLink
          :to="{ name: 'self-hosted-form-builder' }"
          class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-600 hover:text-neutral-950 hover:no-underline"
        >
          <UIcon name="i-heroicons-arrow-left-20-solid" class="h-4 w-4" />
          Self-hosted form builder
        </NuxtLink>

        <div class="mt-8 max-w-3xl">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">Self-hosted Enterprise</p>
          <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-neutral-950 sm:text-5xl lg:text-6xl lg:leading-[1.05]">
            Add governance to your self-hosted OpnForm instance
          </h1>
          <p class="mt-6 max-w-2xl text-lg leading-8 text-neutral-600 sm:text-xl sm:leading-9">
            Unlock more users, Enterprise identity, branding controls, audit visibility,
            workspace-level settings, and priority support. Stripe handles checkout and
            the license key is delivered by email.
          </p>
        </div>
      </div>
    </section>

    <section class="px-5 py-12 sm:px-8 sm:py-16 lg:px-12 lg:py-20">
      <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1fr_420px] lg:items-start">
        <div>
          <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">Included with Enterprise</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Controls for a shared production deployment</h2>
            <p class="mt-4 max-w-2xl leading-7 text-neutral-600">
              Community remains the right starting point for small technical teams.
              Enterprise adds the controls organizations need when more people and
              workspaces depend on the instance.
            </p>

            <div class="mt-8 grid gap-px overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-200 sm:grid-cols-2">
              <div v-for="feature in licenseFeatures" :key="feature.title" class="bg-white p-5">
                <UIcon :name="feature.icon" class="h-5 w-5 text-blue-600" />
                <h3 class="mt-4 font-semibold text-neutral-950">{{ feature.title }}</h3>
                <p class="mt-2 text-sm leading-6 text-neutral-600">{{ feature.description }}</p>
              </div>
            </div>
          </div>

          <div class="mt-6 rounded-3xl border border-neutral-200 bg-white p-6 sm:p-8">
            <h2 class="text-xl font-semibold text-neutral-950">Before you purchase</h2>
            <ul class="mt-5 space-y-4 text-sm leading-6 text-neutral-600">
              <li class="flex gap-3">
                <UIcon name="i-heroicons-check-circle-20-solid" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                The self-service subscription provides one active production instance activation.
              </li>
              <li class="flex gap-3">
                <UIcon name="i-heroicons-check-circle-20-solid" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                Community features stay available without a license; Enterprise features require an active subscription.
              </li>
              <li class="flex gap-3">
                <UIcon name="i-heroicons-check-circle-20-solid" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                Contact us before checkout for multiple active instances, procurement, or a custom written agreement.
              </li>
            </ul>
            <div class="mt-6 flex flex-wrap gap-4 text-sm font-semibold">
              <a
                href="https://docs.opnform.com/deployment/self-hosted-license"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-blue-600 hover:no-underline"
              >
                Review license features
                <UIcon name="i-heroicons-arrow-up-right-20-solid" class="h-4 w-4" />
              </a>
              <NuxtLink :to="{ name: 'terms-conditions' }" class="inline-flex items-center gap-1 text-blue-600 hover:no-underline">
                Read Enterprise terms
                <UIcon name="i-heroicons-arrow-right-20-solid" class="h-4 w-4" />
              </NuxtLink>
            </div>
          </div>
        </div>

        <aside class="rounded-3xl border border-blue-200 bg-white p-6 shadow-[0_24px_70px_-30px_rgba(37,99,235,0.4)] sm:p-8 lg:sticky lg:top-24">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.12em] text-blue-600">One active instance</p>
              <h2 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-950">Enterprise License</h2>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
              <UIcon name="i-heroicons-shield-check-20-solid" class="h-6 w-6" />
            </span>
          </div>

          <div class="mt-6">
            <MonthlyYearlySelector v-model="isYearly" :show-savings-badge="false" />
          </div>

          <div class="mt-8 border-b border-neutral-200 pb-7">
            <p class="flex items-end gap-2">
              <span class="text-4xl font-semibold tracking-tight text-neutral-950">${{ monthlyDisplayPrice }}</span>
              <span class="pb-1 text-base font-medium text-neutral-600">/mo</span>
            </p>
            <p class="mt-2 text-sm leading-6 text-neutral-500">{{ billingSummary }}</p>
          </div>

          <form class="mt-7 space-y-4" @submit.prevent="startCheckout">
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
              class="h-12 rounded-xl font-semibold"
            >
              Continue to secure checkout
            </UButton>
          </form>

          <p class="mt-4 text-center text-xs leading-5 text-neutral-500">You will review the final amount in Stripe before payment.</p>

          <div class="mt-6 rounded-2xl bg-neutral-50 p-4 text-sm leading-6 text-neutral-600">
            Already bought a license? Open <strong>User Settings</strong> in your
            self-hosted instance and activate the key from the <strong>License</strong> tab.
          </div>

          <UButton variant="link" color="neutral" class="mt-4 p-0 font-medium" @click.prevent="contactUs">
            Need invoice or procurement help?
          </UButton>
        </aside>
      </div>
    </section>

    <OpenFormFooter :show-cta="false" />
  </main>
</template>

<script setup>
import MonthlyYearlySelector from "~/components/pages/pricing/MonthlyYearlySelector.vue"

definePageMeta({
  layout: "default",
  middleware: ["self-hosted"],
})

useOpnSeoMeta({
  title: "Self-hosted Enterprise License",
  description: "Purchase an OpnForm Enterprise license for one active self-hosted production instance.",
  robots: "noindex, follow",
})

const alert = useAlert()
const { tiers } = usePlanCatalog()
const { getPlanPrice } = useBillingUpsell()

const isYearly = ref(true)
const isLoading = ref(false)
const errorMessage = ref("")
const checkoutForm = useForm({ billingEmail: "" })

const yearlyPrice = computed(() => tiers.value.self_hosted?.price_yearly ?? 1999)
const monthlyPrice = computed(() => tiers.value.self_hosted?.price_monthly ?? 199)
const monthlyDisplayPrice = computed(() => {
  const catalogPrice = getPlanPrice("self_hosted", isYearly.value)
  if (catalogPrice !== null && catalogPrice !== undefined) return catalogPrice

  return isYearly.value ? Math.round(yearlyPrice.value / 12) : monthlyPrice.value
})
const formatUsd = (amount) => new Intl.NumberFormat("en-US").format(amount)
const billingSummary = computed(() => isYearly.value
  ? `Billed yearly at $${formatUsd(yearlyPrice.value)}/year.`
  : `Billed monthly at $${formatUsd(monthlyPrice.value)}/month.`)

const licenseFeatures = [
  { icon: "i-heroicons-users-20-solid", title: "More than 2 users", description: "Add the team members your production instance needs." },
  { icon: "i-heroicons-finger-print-20-solid", title: "Enterprise identity", description: "Use SAML and LDAP alongside the self-hosted OIDC path." },
  { icon: "i-heroicons-paint-brush-20-solid", title: "Branding controls", description: "Remove OpnForm branding and use advanced workspace branding." },
  { icon: "i-heroicons-document-magnifying-glass-20-solid", title: "Audit visibility", description: "Add audit and governance controls for shared infrastructure." },
  { icon: "i-heroicons-envelope-20-solid", title: "Workspace SMTP", description: "Give individual workspaces dedicated email settings." },
  { icon: "i-heroicons-chat-bubble-left-right-20-solid", title: "Priority support", description: "Get help for your Enterprise deployment and rollout." },
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
