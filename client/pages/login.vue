<template>
  <div class="bg-white">
    <section class="relative overflow-hidden">
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-linear-to-b from-white via-blue-50 to-white" />
        <div class="absolute left-0 top-0 h-80 w-80 rounded-full bg-blue-100/70 blur-3xl" />
        <div class="absolute bottom-0 right-0 h-96 w-96 rounded-full bg-sky-100/70 blur-3xl" />
      </div>

      <div class="relative px-6 py-10 sm:px-8 sm:py-14 lg:px-12 lg:py-16">
        <div class="mx-auto max-w-xl">
          <div class="rounded-[32px] border border-white/80 bg-white/90 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.12)] backdrop-blur sm:p-8">
            <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
              <UIcon name="i-heroicons-lock-closed" class="h-4 w-4" />
              Account access
            </div>
            <h2
              data-testid="login-page"
              class="mt-4 text-3xl font-semibold tracking-[-1%] text-neutral-950"
            >
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
