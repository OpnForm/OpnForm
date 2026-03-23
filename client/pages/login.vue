<template>
  <div>
    <div class="flex mt-6 mb-10">
      <div
        class="w-full md:max-w-6xl mx-auto px-4 flex md:flex-row-reverse flex-wrap"
      >
        <div class="w-full md:w-1/2 md:p-6">
          <div
            data-testid="login-page"
            class="border rounded-md p-6 shadow-md sticky top-4"
          >
            <h2 class="font-semibold text-2xl">
              Login to OpnForm
            </h2>
            <p class="text-sm text-neutral-500">
              Welcome back! Please enter your details.
            </p>

      <div class="relative px-6 py-10 sm:px-8 sm:py-14 lg:px-12 lg:py-16">
        <div class="mx-auto max-w-xl">
          <div
            data-testid="login-page"
            class="rounded-[32px] border border-white/80 bg-white/90 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.12)] backdrop-blur sm:p-8"
          >
            <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
              <UIcon name="i-heroicons-lock-closed" class="h-4 w-4" />
              Account access
            </div>
            <h2 class="mt-4 text-3xl font-semibold tracking-[-1%] text-neutral-950">
              Log in to OpnForm
            </h2>

            <div class="mt-6">
              <LoginForm />
            </div>
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter />
  </div>
</template>

<script setup>
import LoginForm from "~/components/pages/auth/components/LoginForm.vue"

definePageMeta({
  middleware: "guest",
})

useOpnSeoMeta({
  title: "Login",
})
</script>
