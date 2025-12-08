<template>
  <div class="w-full">
    <section class="relative">
      <div
        v-if="!homePage"
        class="absolute inset-0 grid"
        aria-hidden="true"
      >
        <div class="bg-neutral-100" />
        <div class="bg-white" />
      </div>

      <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div
          class="max-w-5xl mx-auto bg-white shadow-xl rounded-3xl ring-1 ring-neutral-200 lg:flex isolate"
        >
          <div class="p-8 sm:p-8 lg:flex-auto">
            <h3
              v-if="homePage"
              class="text-3xl font-semibold tracking-tight text-neutral-950"
            >
              Check out our
              <span class="ml-2 text-blue-500">
                <svg
                  class="inline w-10 h-10"
                  viewBox="0 0 32 32"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <g opacity="0.12">
                    <path
                      d="M15.9998 27.3333L10.6665 12H21.3332L15.9998 27.3333Z"
                      fill="currentColor"
                    />
                    <path
                      d="M13.3332 4H9.33317L2.6665 12L10.6665 12L13.3332 4Z"
                      fill="currentColor"
                    />
                    <path
                      d="M18.6665 4H22.6665L29.3332 12L21.3332 12L18.6665 4Z"
                      fill="currentColor"
                    />
                  </g>
                  <path
                    d="M3.33345 12H28.6668M13.3334 4L10.6668 12L16.0001 27.3333L21.3334 12L18.6668 4M16.8195 27.0167L28.7644 12.6829C28.9668 12.4399 29.0681 12.3185 29.1067 12.1829C29.1408 12.0633 29.1408 11.9367 29.1067 11.8171C29.0681 11.6815 28.9668 11.5601 28.7644 11.3171L22.9866 4.3838C22.8691 4.24273 22.8103 4.17219 22.7382 4.12148C22.6744 4.07654 22.6031 4.04318 22.5277 4.02289C22.4426 4 22.3508 4 22.1672 4H9.83305C9.64941 4 9.55758 4 9.4725 4.02289C9.39711 4.04318 9.32586 4.07654 9.26202 4.12148C9.18996 4.17219 9.13118 4.24273 9.01361 4.3838L3.23583 11.3171C3.0334 11.5601 2.93218 11.6815 2.8935 11.8171C2.85939 11.9366 2.85939 12.0633 2.8935 12.1829C2.93218 12.3185 3.0334 12.4399 3.23583 12.6829L15.1807 27.0167C15.4621 27.3544 15.6028 27.5232 15.7713 27.5848C15.919 27.6388 16.0812 27.6388 16.229 27.5848C16.3974 27.5232 16.5381 27.3544 16.8195 27.0167Z"
                    stroke="currentColor"
                    stroke-width="2.66667"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
                Pro Features
              </span>
            </h3>
            <h3
              v-else
              class="text-3xl font-semibold tracking-tight text-neutral-950"
            >
              Pro Plan
            </h3>
            <p class="mt-2 text-base font-medium leading-7 text-neutral-600">
              OpnForm Pro offers empowering features tailored to the advanced
              needs of teams and creators.
            </p>

            <div class="flex items-center mt-6 gap-x-4">
              <h4
                class="flex-none text-sm font-semibold leading-6 tracking-widest text-neutral-400 uppercase"
              >
                What's included
              </h4>
              <div class="flex-auto h-px bg-neutral-200" />
            </div>

            <ul
              role="list"
              class="grid grid-cols-1 gap-4 mt-4 text-sm font-medium leading-6 text-neutral-900 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-2"
            >
              <li
                v-for="(title, i) in pricingInfo"
                :key="i"
                class="flex gap-x-3"
              >
                <svg
                  aria-hidden="true"
                  class="w-5 h-5 shrink-0 stroke-blue-600"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M20 6L9 17L4 12"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
                {{ title }}
              </li>
              <slot
                name="pricing-table"
                :is-yearly="isYearly"
              />
            </ul>
          </div>

          <div
            class="p-2 -mt-2 flex-col lg:mt-0 lg:w-full lg:max-w-md lg:flex-shrink-0"
          >
            <div
              class="grow h-full py-10 text-center rounded-2xl bg-neutral-50 ring-1 ring-inset ring-neutral-900/5 lg:flex lg:flex-col lg:justify-center lg:py-12"
            >
              <div class="max-w-xs px-8 mx-auto space-y-6">
                <div class="flex items-center justify-center mb-10">
                  <monthly-yearly-selector v-model="isYearly" />
                </div>
                <!-- lg+ -->

                <p class="flex flex-col items-center">
                  <span
                    class="text-6xl font-semibold tracking-tight text-neutral-950"
                  >
                    <template v-if="isYearly">$16</template>
                    <template v-else>$19</template>
                  </span>
                  <span class="text-sm font-medium leading-6 text-neutral-600">
                    per month
                  </span>
                </p>

                <div class="flex justify-center">
                  <UButton
                    v-if="!authenticated"
                    class="mr-1"
                    :to="{ name: 'register' }"
                    trailing-icon="i-heroicons-arrow-right"
                    label="Get Pro"
                  />
                  <UButton
                    v-else-if="authenticated && user && user.is_subscribed"
                    class="mr-1"
                    trailing-icon="i-heroicons-arrow-right"
                    :to="{ name: 'redirect-billing-portal' }"
                    target="_blank"
                    label="View Billing"
                  />
                  <UButton
                    v-else
                    class="mr-1"
                    trailing-icon="i-heroicons-arrow-right"
                    @click.prevent="openSubscriptionModal({ plan: 'default', yearly: isYearly })"
                    label="Get Pro"
                  />
                </div>
                <p
                  v-if="!homePage"
                  class="text-xs font-medium leading-5 text-neutral-600"
                >
                  Invoices and receipts available for easy company
                  reimbursement.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <custom-plan
      v-if="!homePage"
      class="mt-8"
    />
  </div>
</template>

<script>
import MonthlyYearlySelector from "./MonthlyYearlySelector.vue"
import CustomPlan from "./CustomPlan.vue"
import { useIsAuthenticated } from "~/composables/useAuthFlow"

MonthlyYearlySelector.compatConfig = { MODE: 3 }

export default {
  components: {
    MonthlyYearlySelector,
    CustomPlan,
  },
  props: {
    homePage: {
      type: Boolean,
      default: false,
    },
  },
  setup() {
    const { openSubscriptionModal } = useAppModals()
    const { isAuthenticated: authenticated } = useIsAuthenticated()
    const { data: user } = useAuth().user()
    
    return {
      openSubscriptionModal,
      authenticated,
      user,
    }
  },
  data: () => ({
    isYearly: true,
    pricingInfo: [
      "Unlimited workspaces",
      "Form confirmation emails",
      "Slack notifications",
      "Discord notifications",
      "Editable submissions",
      "1 Custom domain",
      "Custom code",
      "Larger file uploads (50mb)",
      "Remove OpnForm branding",
      "Priority support",
      "Form Analytics",
      "Custom sender email (SMTP)"
    ],
  }),

  computed: {},

  methods: {},
}
</script>
