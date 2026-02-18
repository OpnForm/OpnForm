<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-5xl' }"
    title=""
    :close="false"
  >
    <template #body>
      <div class="overflow-hidden">
        <SlidingTransition
          :style="transitionContainerStyle"
          direction="horizontal"
          :step="currentStep"
          :speed="transitionDurationMs"
        >
          <div
            :key="currentStep"
            class="w-full"
          >
            <div
              v-if="currentStep === 1"
              key="step1"
              class="flex flex-col items-center px-4 rounded-2xl relative"
              ref="step1Ref"
            >
              <main class="flex flex-col mt-4 max-w-full text-center w-[591px] max-md:mt-10">
                <img
                  src="/img/subscription-modal-icon.svg"
                  alt="Subscription Icon"
                  class="self-center max-w-full aspect-[0.98] w-[107px]"
                >
                <section class="flex flex-col mt-2 max-md:max-w-full">
                  <h1 class="text-2xl font-bold tracking-tight leading-9 text-slate-800 max-md:max-w-full">
                    {{ modalTitle }}
                  </h1>
                  <p class="mt-4 text-base leading-6 text-slate-500 max-md:max-w-full">
                    {{ modalDescription }}
                  </p>
                </section>
              </main>
              <div class="mt-8 mb-4 flex items-center justify-center">
                <MonthlyYearlySelector
                  v-model="isYearly"
                />
              </div>
              <section class="flex flex-col w-full max-w-[800px] max-md:max-w-full">
                <div class="bg-white max-md:max-w-full">
                  <div class="flex gap-2 max-md:flex-col max-md:gap-0">
                    <article
                      v-if="!isSubscribed"
                      class="flex flex-col w-6/12 max-md:ml-0 max-md:w-full m-auto"
                    >
                      <div
                        class="flex flex-col grow justify-between p-6 w-full rounded-2xl max-md:px-5 max-md:mt-2"
                        :class="planCardClass"
                      >
                        <div class="flex flex-col items-center">
                          <div class="flex gap-2 py-px">
                            <h2 class="my-auto text-xl font-semibold tracking-tighter leading-5 text-slate-900">
                              {{ selectedPlanName }}
                            </h2>
                            <span
                              v-if="isYearly"
                              class="justify-center px-2 py-1 text-xs font-semibold tracking-wide text-center text-emerald-600 uppercase bg-emerald-50 rounded-md"
                            >
                              Save 15%
                            </span>
                          </div>
                          <div class="flex flex-col justify-end mt-4 leading-[100%]">
                            <p class="text-2xl font-semibold tracking-tight text-slate-900 text-center">
                              ${{ selectedPlanPrice }}
                            </p>
                            <p class="text-xs text-slate-500">
                              per month, billed {{ isYearly ? 'yearly' : 'monthly' }}
                            </p>
                          </div>
                        </div>
                        <TrackClick
                          v-if="!user?.is_subscribed"
                          name="upgrade_modal_start_trial"
                          :properties="{ plan: currentPlan, period: isYearly ? 'yearly' : 'monthly' }"
                          class="w-full"
                        >
                          <UButton
                            class="relative border border-white border-opacity-20 h-10 inline-flex px-4 items-center rounded-lg text-sm font-semibold w-full justify-center mt-4"
                            @click.prevent="onSelectPlan(currentPlan)"
                            :label="`Get ${selectedPlanName}`"
                          />
                        </TrackClick>
                        <UButton
                          v-else
                          :loading="billingLoading"
                          :to="{ name: 'redirect-billing-portal' }"
                          target="_blank"
                          class="relative border border-white border-opacity-20 h-10 inline-flex px-4 items-center rounded-lg text-sm font-semibold w-full justify-center mt-4"
                          label="Manage Plan"
                        />
                      </div>
                    </article>
                  </div>
                </div>
              </section>
              <section class="flex flex-col self-stretch mt-12 max-md:mt-10 max-md:max-w-full">
                <div class="justify-center max-md:pr-5 max-md:max-w-full">
                  <div class="flex gap-5 max-md:flex-col max-md:gap-0">
                    <div class="grid gap-2 grid-cols-3">
                      <div
                        v-for="item in planFeatures"
                        :key="item.title"
                        class="rounded-3xl bg-neutral-50 p-4 flex gap-4 items-start"
                      >
                        <div class="h-12 w-12 rounded-2xl bg-white shadow-sm ring-1 ring-neutral-200 flex items-center justify-center flex-shrink-0">
                          <UIcon :name="item.icon" class="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                          <div class="text-lg font-semibold text-neutral-900">
                            {{ item.title }}
                          </div>
                          <div class="mt-3 text-base font-medium leading-7 text-neutral-600">
                            {{ item.description }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </section>
              <footer class="justify-center py-1.5 mt-8 text-base font-medium leading-6 text-center text-blue-500 max-md:mt-10">
                <UButton
                  class="font-bold"
                  :to="{ name: 'pricing' }"
                  target="_blank"
                  trailing-icon="heroicons:arrow-small-right"
                  variant="link"
                  label="And much more. See full plans comparison"
                />
              </footer>
            </div>
            <section
              v-else-if="currentStep === 2"
              key="step2"
              class="flex flex-col items-center px-6 pb-4 bg-white rounded-2xl w-full"
            >
              <div class="flex gap-2 max-md:flex-wrap">
                <div class="flex justify-center items-center p-2 rounded-[1000px]">
                  <Icon
                    name="heroicons:chevron-left-16-solid"
                    class="h-6 w-6 cursor-pointer"
                    @click.prevent="goBackToStep1"
                  />
                </div>
                <h1 class="flex-1 my-auto text-xl font-bold leading-8 text-center text-slate-800 max-md:max-w-full">
                  Confirm
                  <template v-if="isSubscribed">
                    Upgrade
                  </template>
                  <template v-else>
                    Subscription
                  </template>
                </h1>
              </div>
              <div class="flex-grow w-full max-w-sm">
                <div
                  v-if="!isSubscribed"
                  class="rounded-md p-4 border flex flex-col my-4 gap-1"
                  :class="confirmationBoxClass"
                >
                  <div class="flex w-full">
                    <p
                      class="capitalize font-medium flex-grow"
                      :class="confirmationTextClass"
                    >
                      OpnForm - {{ selectedPlanName }} plan
                    </p>
                    <UBadge
                      :color="isYearly ? 'success' : 'warning'"
                      variant="subtle"
                    >
                      {{ !isYearly ? 'No Discount' : 'Discount Applied' }}
                    </UBadge>
                  </div>

                  <p class="text-sm leading-5 text-slate-500">
                    <span
                      v-if="isYearly && PLAN_PRICING[currentPlan]"
                      class="font-medium line-through mr-2"
                    >${{ PLAN_PRICING[currentPlan].monthly }}</span>
                    <span
                      class="font-medium"
                      :class="{ 'text-green-700': isYearly }"
                    >${{ selectedPlanPrice }}</span>
                    <span
                      class="text-xs"
                      :class="{ 'text-green-700': isYearly }"
                    >
                      per month, billed {{ isYearly ? 'yearly' : 'monthly' }}
                    </span>
                  </p>
                  <div v-if="shouldShowUpsell">
                    <v-form size="sm">
                      <toggle-switch-input
                        name=""
                        v-model="isYearly"
                        label="15% off with the yearly plan"
                        size="sm"
                        wrapper-class="mb-0"
                      />
                    </v-form>
                  </div>
                </div>
                <text-input
                  ref="companyName"
                  label="Company Name"
                  name="name"
                  :required="true"
                  :form="form"
                  help="Name that will appear on invoices"
                />
                <text-input
                  label="Invoicing Email"
                  name="email"
                  native-type="email"
                  :required="true"
                  :form="form"
                  help="Where invoices will be sent"
                />
                <div
                  class="flex gap-2 mt-6 w-full"
                >
                  <TrackClick
                    name="upgrade_modal_confirm_submit"
                    class="grow flex"
                    :properties="{ plan: currentPlan, period: isYearly ? 'yearly' : 'monthly' }"
                  >
                    <UButton
                      block
                      size="md"
                      class="w-auto flex-grow"
                      :loading="form.busy || loading"
                      :disabled="form.busy || loading"
                      :to="checkoutUrl"
                      target="_blank"
                    >
                      <template v-if="isSubscribed">
                        Upgrade to {{ selectedPlanName }}
                      </template>
                      <template v-else>
                        Subscribe to {{ selectedPlanName }}
                      </template>
                    </UButton>
                  </TrackClick>
                  <UButton
                    size="md"
                    color="neutral"
                    variant="outline"
                    @click="goBackToStep1"
                  >
                    Back
                  </UButton>
                </div>
              </div>
            </section>
          </div>
        </SlidingTransition>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import SlidingTransition from '~/components/global/transitions/SlidingTransition.vue'
import TrackClick from '~/components/global/TrackClick.vue'

import { useCheckoutUrl } from '@/composables/components/stripe/useCheckoutUrl'
import { PLAN_PRICING } from '~/composables/usePlanFeatures'
import { authApi } from '~/api'
import { computed, watchEffect } from 'vue'
import { useElementSize } from '@vueuse/core'

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
  }
})

const emit = defineEmits(['close'])

const router = useRouter()

// Normalize plan - map legacy 'default' to 'pro'
const normalizedPlan = computed(() => {
  if (!props.plan || props.plan === 'default') return 'pro'
  return props.plan
})

const currentPlan = ref(normalizedPlan.value)
const currentStep = ref(1)
const isYearly = ref(props.yearly)
const loading = ref(false)
const billingLoading = ref(false)
const shouldShowUpsell = ref(false)
const form = useForm({
  name: '',
  email: ''
})

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('close', value)
})

const closeModal = () => {
  isOpen.value = false
}

const subscribeBroadcast = useBroadcastChannel('subscribe')
const broadcastData = subscribeBroadcast.data
const confetti = useConfetti()
const { isAuthenticated: authenticated } = useIsAuthenticated()
const { data: user } = useAuth().user()
const isSubscribed = computed(() => user.value.is_pro)
const currency = 'usd'

// Get price for selected plan
const selectedPlanPrice = computed(() => {
  const pricing = PLAN_PRICING[currentPlan.value]
  if (!pricing) return null
  return isYearly.value ? pricing.yearly : pricing.monthly
})

const selectedPlanName = computed(() => {
  const names = { pro: 'Pro', business: 'Business', enterprise: 'Enterprise' }
  return names[currentPlan.value] || 'Pro'
})

// Dynamic modal title based on plan
const modalTitle = computed(() => {
  if (props.modal_title && props.modal_title !== 'Choose your plan') {
    return props.modal_title
  }
  return `Upgrade to ${selectedPlanName.value}`
})

// Dynamic modal description based on plan
const modalDescription = computed(() => {
  if (props.modal_description && props.modal_description !== 'Unlock all features and get the most out of OpnForm.') {
    return props.modal_description
  }
  const descriptions = {
    pro: 'Remove branding, use custom domains, and unlock all Pro features.',
    business: 'Get team roles, advanced analytics, and Business-tier integrations.',
    enterprise: 'Enterprise-grade security with SSO, audit logs, and compliance features.'
  }
  return descriptions[currentPlan.value] || descriptions.pro
})

// Plan card background class
const planCardClass = computed(() => {
  const classes = {
    pro: 'bg-blue-50',
    business: 'bg-orange-50',
    enterprise: 'bg-purple-50'
  }
  return classes[currentPlan.value] || 'bg-blue-50'
})

// Confirmation box styling
const confirmationBoxClass = computed(() => {
  const classes = {
    pro: 'bg-blue-50 border-blue-200',
    business: 'bg-orange-50 border-orange-200',
    enterprise: 'bg-purple-50 border-purple-200'
  }
  return classes[currentPlan.value] || 'bg-blue-50 border-blue-200'
})

const confirmationTextClass = computed(() => {
  const classes = {
    pro: 'text-blue-500',
    business: 'text-orange-600',
    enterprise: 'text-purple-600'
  }
  return classes[currentPlan.value] || 'text-blue-500'
})

// Features to display based on plan
const planFeatures = computed(() => {
  const proFeatures = [
    { icon: 'mdi:star-outline', title: 'Remove OpnForm branding', description: 'Remove our watermark, create forms that match your brand.' },
    { icon: 'heroicons:globe-alt', title: '1 custom domain', description: 'Host your form on your own domain for a professional look.' },
    { icon: 'heroicons:bell', title: 'Pro integrations', description: 'Setup Slack, Discord, Telegram notifications and more.' }
  ]
  
  const businessFeatures = [
    { icon: 'heroicons:users', title: 'Multi-user with roles', description: 'Collaborate with your team with granular permissions.' },
    { icon: 'ion:brush-outline', title: 'Advanced branding', description: 'Custom CSS, fonts, and full design control.' },
    { icon: 'heroicons:chart-bar', title: 'Analytics dashboard', description: 'Track form performance with detailed insights.' }
  ]
  
  const enterpriseFeatures = [
    { icon: 'heroicons:shield-check', title: 'SSO (SAML, OIDC, LDAP)', description: 'Enterprise authentication for your organization.' },
    { icon: 'heroicons:document-text', title: 'Audit logs & compliance', description: 'Track all actions for security and compliance.' },
    { icon: 'heroicons:server', title: 'External storage', description: 'Store files in your own S3 or GCS buckets.' }
  ]
  
  const features = {
    pro: proFeatures,
    business: businessFeatures,
    enterprise: enterpriseFeatures
  }
  
  return features[currentPlan.value] || proFeatures
})

const transitionDurationMs = 300
// Measure Step 1 height and apply as fixed height to the container
const step1Ref = ref(null)
const { height: step1Height } = useElementSize(step1Ref)
const cachedStep1Height = ref(0)
watchEffect(() => {
  if (step1Height?.value) {
    cachedStep1Height.value = step1Height.value
  }
})
const transitionContainerStyle = computed(() => {
  const h = cachedStep1Height.value
  return h ? { height: h + 'px' } : {}
})

const checkoutUrl = useCheckoutUrl(
  computed(() => form.name),
  computed(() => form.email),
  currentPlan,
  isYearly,
  currency
)

// When opening modal - set plan and billing period from props
watch(() => props.modelValue, () => {
  if (props.modelValue) {
    // Reset to step 1
    currentStep.value = 1
    
    // Set plan from props (normalized)
    currentPlan.value = normalizedPlan.value
    isYearly.value = props.yearly
    
    // Update user form data
    updateUser()
    
    // If user is already subscribed, stay on step 1
    // Otherwise, if plan is provided, can skip to step 2 for direct checkout
    if (!user.value?.is_subscribed && props.plan) {
      shouldShowUpsell.value = !isYearly.value
      currentStep.value = 2
    }
  }
})

watch(broadcastData, () => {
  if (import.meta.server || !props.modelValue || !broadcastData.value || !broadcastData.value.type) {
    return
  }

  if (broadcastData.value.type === 'success') {
    // Now we need to reload workspace and user
    authApi.user.get().then((_userData) => {
      useAuth().invalidateUser()

      try {
        const eventData = {
          plan: currentPlan.value
        }
        useAmplitude().logEvent('subscribed', eventData)
        useCrisp().pushEvent('subscribed', eventData)
        useGtm().trackEvent({ event: 'subscribed', ...eventData })
        if (import.meta.client && window.rewardful) {
          window.rewardful('convert', { email: user.value.email })
        }
        console.log('Subscription registered 🎊')
      } catch (error) {
        console.error('Failed to register subscription event 😔', error)
      }
    })
    const { invalidateAll } = useWorkspaces()
    invalidateAll() // Refresh all workspace data

    const planMessages = {
      enterprise: 'You now have access to all Enterprise features including SSO, audit logs, and compliance features.',
      business: 'You now have access to all Business features including team roles, analytics, and advanced integrations.',
      pro: 'You now have access to all Pro features including branding removal, custom domains, and more.'
    }

    const message = planMessages[currentPlan.value] || planMessages.pro
    useAlert().success(
      `Awesome! Your subscription to OpnForm ${selectedPlanName.value} is now confirmed! ${message} Feel free to contact us if you have any question 🙌`
    )
    confetti.play()
    closeModal()
  } else {
    useAlert().error(
      'Unfortunately we could not confirm your subscription. Please try again and contact us if the issue persists.'
    )
    currentStep.value = 1
    shouldShowUpsell.value = true
  }
  subscribeBroadcast.close()
})

onMounted(() => {
  updateUser()
})

// Update form with user data - sets company name to user name by default
const updateUser = () => {
  if (user.value) {
    // Set company name to user name by default
    if (user.value.name && !form.name) {
      form.name = user.value.name
    }
    
    // Set email if available
    if (user.value.email && !form.email) {
      form.email = user.value.email
    }
  }
}

// Watch for user changes
watch(user, () => {
  updateUser()
}, { immediate: true })

const onSelectPlan = (planName) => {
  if (!authenticated.value) {
    closeModal()
    router.push({ name: "register" })
    return
  }

  loading.value = false
  currentPlan.value = planName
  shouldShowUpsell.value = !isYearly.value
  currentStep.value = 2
}

const goBackToStep1 = () => {
  currentStep.value = 1
}
</script>
