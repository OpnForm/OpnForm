<template>
  <div>
    <div class="flex mt-6 mb-10">
      <div
        class="w-full md:max-w-6xl mx-auto px-4 flex items-center md:flex-row-reverse flex-wrap"
      >
        <div class="w-full max-w-lg lg:max-w-auto mx-auto lg:w-1/2 md:p-6">
          <app-sumo-register class="mb-10 p-6 lg:hidden" />
          <div class="border rounded-md p-6 shadow-md sticky top-4">
            <h2 class="font-semibold text-2xl">
              Create an account
            </h2>
            <p class="text-neutral-500 text-sm">
              Sign up in less than 2 minutes.
            </p>
            <template v-if="!useFeatureFlag('self_hosted') || isInvited">
              <register-form />
            </template>
            <div
              v-else
              class="my-6 p-3 rounded-lg border border-yellow-600 bg-yellow-200 text-yellow-600"
            >
              Registration is not allowed in self host mode.
            </div>
          </div>
        </div>
        <div class="w-full hidden lg:block lg:w-1/2 md:p-6 mt-8 md:mt-0">
          <app-sumo-register class="mb-10" />
          <h1 class="font-bold">
            Create beautiful forms and share them anywhere
          </h1>
          <p class="text-neutral-900 my-4 text-lg">
            It takes seconds, you don't need to know how to code and it's free.
          </p>
          <div class="flex flex-wrap justify-center">
            <p class="px-3 pb-3 text-sm text-neutral-500">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 inline"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              Unlimited forms
            </p>
            <p class="px-3 pb-3 text-sm text-neutral-500">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 inline"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              Unlimited fields
            </p>
            <p class="px-3 pb-3 text-sm text-neutral-500">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 inline"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              Unlimited submissions
            </p>
          </div>
        </div>
      </div>
    </div>
    <open-form-footer />
  </div>
</template>

<script>
import RegisterForm from "~/components/pages/auth/components/RegisterForm.vue"
import AppSumoRegister from "~/components/vendor/appsumo/AppSumoRegister.vue"

export default {
  components: {
    AppSumoRegister,
    RegisterForm,
  },

  setup() {
    useOpnSeoMeta({
      title: "Register",
    })

    definePageMeta({
      middleware: ["self-hosted", "guest"]
    })

    defineRouteRules({
      swr: 3600,
    })
    return {
      appStore: useAppStore(),
    }
  },

  data: () => ({}),

  computed: {
    isInvited() {
      return this.$route.query?.email && this.$route.query?.invite_token
    }
  },

  methods: {},
}
</script>
