<template>
  <div>
    <div class="flex mt-6 mb-10">
      <div
        class="w-full md:max-w-6xl mx-auto px-4 flex items-center md:flex-row-reverse flex-wrap"
      >
        <div class="w-full max-w-lg lg:max-w-auto mx-auto lg:w-1/2 md:p-6">
          <app-sumo-register class="mb-10 p-6 lg:hidden" />
          <div
            data-testid="register-page"
            class="border rounded-md p-6 shadow-md sticky top-4"
          >
            <h2 class="font-semibold text-2xl">
              Create an account
            </h2>
            <p class="mt-3 text-base font-normal leading-7 tracking-[-1.1%] text-neutral-600">
              Start in a few minutes and begin building forms right away.
            </p>

            <div v-if="isInvited" class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
              <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm">
                  <UIcon name="i-heroicons-envelope-open" class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-neutral-950">
                    Workspace invitation detected
                  </p>
                  <p class="mt-1 text-sm leading-6 text-neutral-600">
                    Finish registration to accept your invite and join the shared workspace.
                  </p>
                </div>
              </div>
            </div>

            <template v-if="!useFeatureFlag('self_hosted') || isInvited">
              <div class="mt-6">
                <RegisterForm />
              </div>
            </template>
            <div
              v-else
              class="mt-6 rounded-3xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-700"
            >
              Registration is not allowed in self host mode.
            </div>
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter />
  </div>
</template>

<script setup>
import RegisterForm from "~/components/pages/auth/components/RegisterForm.vue"
import AppSumoRegister from "~/components/vendor/appsumo/AppSumoRegister.vue"

definePageMeta({
  middleware: ["self-hosted", "guest"],
})

defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: "Register",
})

const route = useRoute()

const isInvited = computed(() => {
  return route.query?.email && route.query?.invite_token
})

const showAppSumoPanel = computed(() => {
  return Boolean(route.query.appsumo_license || route.query.appsumo_error)
})
</script>
