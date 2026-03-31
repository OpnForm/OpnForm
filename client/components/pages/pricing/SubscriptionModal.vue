<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-6xl' }"
    title=""
    :close="false"
  >
    <template #body>
      <div class="overflow-hidden">
        <section
          class="relative overflow-hidden rounded-[2rem] bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.10),_transparent_32%),linear-gradient(180deg,_#ffffff_0%,_#f8fbff_50%,_#ffffff_100%)] px-5 py-6 sm:px-8 sm:py-8"
        >
          <div class="pointer-events-none absolute inset-x-0 top-0 h-36 bg-[linear-gradient(180deg,rgba(59,130,246,0.08),transparent)]" />

          <div class="relative mx-auto max-w-5xl">
            <div class="mx-auto max-w-3xl text-center">
              <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-blue-100 bg-white/90 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.24em] text-blue-700 shadow-sm">
                <span class="h-2 w-2 rounded-full bg-emerald-500" />
                Upgrade options
              </div>
              <h1 class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 sm:text-5xl">
                {{ modalTitle }}
              </h1>
              <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                {{ modalDescription }}
              </p>
            </div>

            <div class="mt-8 flex flex-col items-center gap-3 sm:mt-10">
              <MonthlyYearlySelector v-model="isYearly" />
            </div>

            <div
              class="mt-8 gap-4 grid"
              :class="isSingleVisiblePlan ? 'grid-cols-1 justify-items-center' : 'lg:grid-cols-3'"
            >
              <article
                v-for="planOption in visiblePlanOptions"
                :key="planOption.key"
                class="group relative flex h-full flex-col overflow-hidden rounded-[1.9rem] border p-5 transition-all duration-200 sm:p-6"
                :class="[isSingleVisiblePlan ? 'max-w-lg' : '', getPlanCardClasses(planOption)]"
              >
                <div
                  class="pointer-events-none absolute inset-x-4 top-0 h-40 opacity-90 blur-2xl"
                  :class="planOption.isRequired ? PLAN_VISUALS.pro.glowClass : planOption.glowClass"
                />
                <div
                  v-if="planOption.isRequired"
                  class="absolute inset-x-6 top-0 h-1 rounded-b-full bg-gradient-to-r from-blue-400 via-sky-500 to-cyan-400"
                />

                <div class="relative flex h-full flex-col">
                  <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                      <h2 class="text-2xl font-semibold tracking-tight text-slate-950">
                        {{ planOption.label }}
                      </h2>
                      <div
                        v-if="showRequirementHints"
                        class="mt-3 flex flex-wrap items-center gap-2"
                      >
                        <span
                          v-if="planOption.isRequired"
                          class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-blue-700"
                        >
                          Best fit
                        </span>
                        <span
                          v-else-if="planOption.meetsRequirement"
                          class="whitespace-nowrap rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700"
                        >
                          Also unlocks this
                        </span>
                        <span
                          v-else
                          class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                          Missing this feature
                        </span>
                      </div>
                    </div>

                    <div
                      class="flex w-28 shrink-0 flex-col items-end bg-transparent px-1 py-1 text-right sm:w-30"
                    >
                      <div class="text-[1.9rem] font-semibold leading-none tracking-tight text-slate-950">
                        ${{ planOption.price }}
                      </div>
                      <div class="mt-2 text-[11px] font-medium leading-4 text-slate-500">
                        per month
                      </div>
                      <div class="mt-0.5 text-[11px] leading-4 text-slate-400">
                        {{ isYearly ? 'billed yearly' : 'billed monthly' }}
                      </div>
                    </div>
                  </div>

                  <p class="mt-4 text-sm leading-6 text-slate-500">
                    {{ planOption.subtitle }}
                  </p>

                  <ul class="mt-6 space-y-3.5 text-sm font-semibold leading-6 text-slate-800">
                    <li
                      v-for="benefit in planOption.highlights"
                      :key="benefit"
                      class="flex items-start gap-3"
                    >
                      <span class="mt-1 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-white text-blue-600 shadow-sm ring-1 ring-slate-200">
                        <UIcon name="heroicons:check-20-solid" class="h-3.5 w-3.5" />
                      </span>
                      <span>{{ benefit }}</span>
                    </li>
                  </ul>

                  <div v-if="!isSelfHosted" class="mt-auto pt-6 text-sm leading-6 text-slate-500">
                    And more on the
                    <ULink
                      to="/pricing"
                      target="_blank"
                      class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-950 hover:decoration-slate-500"
                    >
                      full pricing page
                    </ULink>
                  </div>

                  <TrackClick
                    v-if="canSelectPlan(planOption.key)"
                    name="upgrade_modal_select_plan"
                    :properties="{ plan: planOption.key, period: isYearly ? 'yearly' : 'monthly', required_plan: normalizedPlan }"
                    class="block pt-4"
                  >
                      <UButton
                        block
                        size="lg"
                        :color="planOption.isRequired ? 'primary' : 'neutral'"
                        :variant="planOption.isRequired ? 'solid' : 'outline'"
                        class="h-12 rounded-2xl font-semibold"
                        :loading="isPlanLoading(planOption.key)"
                        :disabled="checkoutLoading"
                        @click.prevent="startCheckout(planOption.key)"
                      >
                      {{ planOption.buttonLabel }}
                    </UButton>
                  </TrackClick>

                  <div v-else class="pt-4">
                    <UButton
                      block
                      size="lg"
                      :loading="billingLoading"
                      :to="{ name: 'redirect-billing-portal' }"
                      target="_blank"
                      class="h-12 rounded-2xl font-semibold"
                    >
                      Manage plan
                    </UButton>
                  </div>
                </div>
              </article>
            </div>

            <div
              v-if="!isSelfHosted"
              class="mt-7 flex justify-center"
            >
              <UButton
                class="font-semibold"
                :to="{ name: 'pricing' }"
                target="_blank"
                trailing-icon="heroicons:arrow-small-right"
                variant="link"
                color="neutral"
                label="See the full plan comparison"
              />
            </div>

            <div class="mt-8">
              <div class="rounded-[1.75rem] border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                  <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-sm">
                    <UIcon name="heroicons:sparkles" class="h-5 w-5" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">
                      What you unlock
                    </p>
                    <h3 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">
                      {{ selectedPlanName }} highlights
                    </h3>
                  </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                  <div
                    v-for="item in planFeatures"
                    :key="item.title"
                    class="rounded-[1.5rem] border border-slate-200 bg-[linear-gradient(180deg,_#ffffff_0%,_#f8fbff_100%)] p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_14px_32px_rgba(15,23,42,0.08)]"
                  >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                      <UIcon :name="item.icon" class="h-5 w-5 text-sky-600" />
                    </div>
                    <div class="mt-4 text-lg font-semibold tracking-tight text-slate-950">
                      {{ item.title }}
                    </div>
                    <div class="mt-2 text-sm leading-6 text-slate-600">
                      {{ item.description }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import TrackClick from '~/components/global/TrackClick.vue'

import { computed } from 'vue'

const PLAN_VISUALS = {
  pro: {
    accentClass: 'text-blue-700',
    glowClass: 'bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.18),_transparent_72%)]',
    cardClass: 'border-blue-200 bg-[linear-gradient(180deg,_rgba(239,246,255,0.96)_0%,_rgba(255,255,255,0.96)_100%)]',
    selectedCardClass: 'border-blue-400 shadow-[0_18px_50px_rgba(59,130,246,0.18)] ring-1 ring-blue-200',
    confirmationClass: 'bg-blue-50 border-blue-200',
    confirmationTextClass: 'text-blue-700',
  },
  business: {
    accentClass: 'text-amber-700',
    glowClass: 'bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.18),_transparent_72%)]',
    cardClass: 'border-amber-200 bg-[linear-gradient(180deg,_rgba(255,247,237,0.96)_0%,_rgba(255,255,255,0.96)_100%)]',
    selectedCardClass: 'border-amber-400 shadow-[0_18px_50px_rgba(245,158,11,0.18)] ring-1 ring-amber-200',
    confirmationClass: 'bg-amber-50 border-amber-200',
    confirmationTextClass: 'text-amber-700',
  },
  enterprise: {
    accentClass: 'text-fuchsia-700',
    glowClass: 'bg-[radial-gradient(circle_at_top,_rgba(168,85,247,0.18),_transparent_72%)]',
    cardClass: 'border-fuchsia-200 bg-[linear-gradient(180deg,_rgba(250,245,255,0.96)_0%,_rgba(255,255,255,0.96)_100%)]',
    selectedCardClass: 'border-fuchsia-400 shadow-[0_18px_50px_rgba(168,85,247,0.18)] ring-1 ring-fuchsia-200',
    confirmationClass: 'bg-fuchsia-50 border-fuchsia-200',
    confirmationTextClass: 'text-fuchsia-700',
  },
  self_hosted: {
    accentClass: 'text-fuchsia-700',
    glowClass: 'bg-[radial-gradient(circle_at_top,_rgba(168,85,247,0.18),_transparent_72%)]',
    cardClass: 'border-fuchsia-200 bg-[linear-gradient(180deg,_rgba(250,245,255,0.96)_0%,_rgba(255,255,255,0.96)_100%)]',
    selectedCardClass: 'border-fuchsia-400 shadow-[0_18px_50px_rgba(168,85,247,0.18)] ring-1 ring-fuchsia-200',
    confirmationClass: 'bg-fuchsia-50 border-fuchsia-200',
    confirmationTextClass: 'text-fuchsia-700',
  },
}

const PLAN_DETAILS = {
  pro: {
    subtitle: 'Branding control, custom domains, analytics, notifications, and core premium form tools.',
    highlights: [
      'Remove OpnForm branding',
      'Use one custom domain',
      'Unlock analytics, summaries, and premium integrations',
    ],
    summaryLine: 'Best when you need premium publishing and conversion features.',
    features: [
      { icon: 'mdi:star-outline', title: 'Remove OpnForm branding', description: 'Publish forms without the OpnForm watermark and make the experience feel truly yours.' },
      { icon: 'heroicons:globe-alt', title: '1 custom domain', description: 'Put forms on your own domain for a polished, trustworthy handoff.' },
      { icon: 'heroicons:bell', title: 'Pro integrations', description: 'Send alerts to Slack, Discord, Telegram, and unlock the rest of the Pro workflow tools.' },
    ],
  },
  business: {
    subtitle: 'Collaboration, advanced branding, richer analytics, and operational controls for a real team.',
    highlights: [
      'Multiple users, multiple workspaces',
      'Advanced branding with custom CSS and fonts',
      'Business-only analytics and submission flows',
    ],
    summaryLine: 'Best when multiple teammates need to operate forms together.',
    features: [
      { icon: 'heroicons:users', title: 'Roles for your team', description: 'Invite teammates with the right level of access instead of giving everyone the same permissions.' },
      { icon: 'heroicons:paint-brush', title: 'Advanced branding', description: 'Use custom CSS, fonts, and richer styling controls to match your product or campaign.' },
      { icon: 'heroicons:chart-bar-square', title: 'Deeper form insights', description: 'Unlock business-tier analytics and advanced submission workflows to optimize conversion.' },
    ],
  },
  enterprise: {
    subtitle: 'Security, compliance, and identity controls for teams that need centralized governance.',
    highlights: [
      'SSO and enterprise identity controls',
      'Audit logs and compliance-oriented visibility',
      'Infrastructure and storage flexibility',
    ],
    summaryLine: 'Best when security, compliance, and centralized access control are non-negotiable.',
    features: [
      { icon: 'heroicons:shield-check', title: 'Enterprise SSO', description: 'Connect OIDC, SAML, or LDAP so access is controlled from your identity provider.' },
      { icon: 'heroicons:document-text', title: 'Audit logs & compliance', description: 'Track important activity and support teams that need stronger internal controls.' },
      { icon: 'heroicons:server-stack', title: 'External storage', description: 'Route storage to your own infrastructure when you need tighter operational ownership.' },
    ],
  },
  self_hosted: {
    subtitle: 'Security, compliance, and identity controls for teams that need centralized governance.',
    highlights: [
      'Branding removal',
      'Custom domains and SMTP',
      'Invite unlimited users',
      'SSO and enterprise identity controls',
    ],
    summaryLine: 'Best when security, compliance, and centralized access control are non-negotiable.',
    features: [
      { icon: 'heroicons:globe-alt', title: 'Remove OpnForm branding', description: 'Publish forms without the OpnForm watermark and make the experience feel truly yours.' },
      { icon: 'heroicons:shield-check', title: 'Enterprise SSO', description: 'Connect OIDC, SAML, or LDAP so access is controlled from your identity provider.' },
      { icon: 'heroicons:document-text', title: 'Audit logs & compliance', description: 'Track important activity and support teams that need stronger internal controls.' },
    ],
  },
}

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  modal_title: {
    type: String,
    default: 'Choose your plan'
  },
  modal_description: {
    type: String,
    default: 'Unlock all features and get the most out of OpnForm.'
  },
  plan: {
    type: String,
    default: 'pro'
  },
  yearly: {
    type: Boolean,
    default: true
  },
  show_requirement_hints: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['close'])

const router = useRouter()
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('close', value)
})

const normalizedPlan = computed(() => {
  if (isSelfHosted.value) return 'self_hosted'
  if (!props.plan || props.plan === 'default') return 'pro'
  return props.plan
})

const showRequirementHints = computed(() => props.show_requirement_hints)

const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
const paidPlans = ['pro', 'business', 'enterprise', 'self_hosted']
const currentPlan = ref(normalizedPlan.value)
const isYearly = ref(props.yearly)
const loading = ref(false)
const billingLoading = ref(false)
const { isAuthenticated: authenticated } = useIsAuthenticated()
const { data: user } = useAuth().user()
const { getPlanPrice, userIsSubscribed, currentUserTier, tierMeetsRequirement, getTierDisplayName } = useBillingUpsell()
const { startCheckout: openStripeCheckout, isLoading: checkoutLoading, isPlanLoading } = useStripeCheckout()
const isSubscribed = computed(() => userIsSubscribed.value)

const selectedPlanName = computed(() => getTierDisplayName(currentPlan.value))

const requiredPlanKey = computed(() => paidPlans.find((plan) => plan === normalizedPlan.value) || 'pro')
const planOptions = computed(() => {
  return paidPlans.map((planKey) => {
    const details = PLAN_DETAILS[planKey]
    const isRequired = planKey === requiredPlanKey.value
    const meetsRequirement = tierMeetsRequirement(planKey, requiredPlanKey.value)
    const isSelected = currentPlan.value === planKey
    const currentUserCanAccess = tierMeetsRequirement(currentUserTier.value, planKey)

    function prepareButtonLabel() {
      if (planKey === 'self_hosted') {
        return 'Purchase license'
      }
      return currentUserCanAccess ? 'Current access level' : isRequired ? `Continue with ${getTierDisplayName(planKey)}` : `Choose ${getTierDisplayName(planKey)}`
    }
    return {
      key: planKey,
      label: getTierDisplayName(planKey),
      price: getPlanPrice(planKey, isYearly.value),
      monthlyPrice: getPlanPrice(planKey, false),
      subtitle: details.subtitle,
      highlights: details.highlights,
      summaryLine: details.summaryLine,
      features: details.features,
      isRequired,
      meetsRequirement,
      isSelected,
      currentUserCanAccess,
      glowClass: PLAN_VISUALS[planKey].glowClass,
      accentClass: PLAN_VISUALS[planKey].accentClass,
      buttonLabel: prepareButtonLabel(),
    }
  })
})

const visiblePlanOptions = computed(() => {
  if (isSelfHosted.value) {
    return [...planOptions.value].filter((option) => option.key === 'self_hosted')
  }
  return [...planOptions.value].sort((left, right) => {
    return paidPlans.indexOf(left.key) - paidPlans.indexOf(right.key)
  })
})

const isSingleVisiblePlan = computed(() => visiblePlanOptions.value.length === 1)

const modalTitle = computed(() => {
  if (props.modal_title && props.modal_title !== 'Choose your plan') {
    return props.modal_title
  }
  return `Upgrade to ${selectedPlanName.value}`
})

const modalDescription = computed(() => {
  if (props.modal_description && props.modal_description !== 'Unlock all features and get the most out of OpnForm.') {
    return props.modal_description
  }

  const descriptions = {
    pro: 'Remove branding, use custom domains, and unlock all Pro features.',
    business: 'Unlock the collaboration, branding, and analytics features built for teams.',
    enterprise: 'Unlock enterprise-grade security, identity, and compliance controls.',
    self_hosted: 'Unlock all features and get the most out of OpnForm.',
  }

  return descriptions[requiredPlanKey.value] || descriptions.pro
})

const planFeatures = computed(() => PLAN_DETAILS[currentPlan.value]?.features || PLAN_DETAILS.pro.features)

watch(() => props.modelValue, () => {
  if (props.modelValue) {
    currentPlan.value = normalizedPlan.value
    isYearly.value = props.yearly
  }
})

const closeModal = () => {
  isOpen.value = false
}

const canSelectPlan = () => {
  if (isSelfHosted.value) {
    return true
  }
  return !isSubscribed.value || currentUserTier.value === 'pro'
}

const getPlanCardClasses = (planOption) => {
  const palette = PLAN_VISUALS[planOption.key] || PLAN_VISUALS.pro
  return [
    planOption.isRequired
      ? `${PLAN_VISUALS.pro.selectedCardClass} scale-[1.01]`
      : planOption.meetsRequirement
        ? `${palette.cardClass} shadow-[0_12px_34px_rgba(15,23,42,0.06)]`
        : 'border-slate-200 bg-white/88 shadow-[0_10px_28px_rgba(15,23,42,0.04)]',
    !planOption.meetsRequirement ? 'opacity-90' : '',
    !planOption.isRequired ? 'hover:-translate-y-0.5 hover:shadow-[0_14px_42px_rgba(15,23,42,0.10)]' : '',
  ]
}

const startSelfHostedLicenseCheckout = () => {
  loading.value = true
  const cloudApiUrl = useRuntimeConfig().public.licenseApiEndpoint
  $fetch(`${cloudApiUrl}/licenses/create`, {
    method: 'POST',
    body: {
      billingEmail: user.value?.email,
      plan: 'self_hosted',
      period: isYearly.value ? 'yearly' : 'monthly',
      successUrl: 'https://opnform.com/self-hosted/checkout/success',
      cancelUrl: 'https://opnform.com/self-hosted/checkout/canceled',
    },
  }).then((response) => {
    window.open(response.checkoutUrl, '_blank')
  }).catch(() => {
    useAlert().error('Failed to start checkout. Please try again.')
  }).finally(() => {
    loading.value = false
  })
  return
}

const startCheckout = async (planName) => {
  if (!authenticated.value) {
    closeModal()
    router.push({ name: 'register' })
    return
  }

  if (isSelfHosted.value) {
    startSelfHostedLicenseCheckout()
    return
  }

  currentPlan.value = planName

  try {
    await openStripeCheckout(planName, {
      yearly: isYearly.value,
      closeModal,
      redirectToRegister: false,
    })
  } catch {
    loading.value = false
  }
}
</script>
