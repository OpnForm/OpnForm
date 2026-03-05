<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-50 px-4">
    <div class="max-w-lg w-full text-center space-y-6">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
        <Icon name="i-heroicons-check-circle" class="h-10 w-10 text-green-600" />
      </div>

      <h1 class="text-2xl font-bold text-neutral-900">Payment Received!</h1>
      <p class="text-neutral-600">
        Your Enterprise license key is on its way. Check your email for the license key.
      </p>

      <div class="bg-white rounded-xl border border-neutral-200 p-6 text-left space-y-4">
        <h2 class="text-lg font-semibold text-neutral-900">How to activate your license</h2>
        <ol class="space-y-3 text-sm text-neutral-700">
          <li class="flex gap-3">
            <span class="flex-shrink-0 flex items-center justify-center h-6 w-6 rounded-full bg-blue-100 text-blue-700 font-semibold text-xs">1</span>
            <span>Open your email and copy the license key.</span>
          </li>
          <li class="flex gap-3">
            <span class="flex-shrink-0 flex items-center justify-center h-6 w-6 rounded-full bg-blue-100 text-blue-700 font-semibold text-xs">2</span>
            <span>Go back to your self-hosted OpnForm instance.</span>
          </li>
          <li class="flex gap-3">
            <span class="flex-shrink-0 flex items-center justify-center h-6 w-6 rounded-full bg-blue-100 text-blue-700 font-semibold text-xs">3</span>
            <span>Click your avatar in the sidebar and open <strong>User Settings</strong>.</span>
          </li>
          <li class="flex gap-3">
            <span class="flex-shrink-0 flex items-center justify-center h-6 w-6 rounded-full bg-blue-100 text-blue-700 font-semibold text-xs">4</span>
            <span>Go to the <strong>License</strong> tab, paste your key, and click <strong>Activate License</strong>.</span>
          </li>
        </ol>
      </div>

      <p class="text-sm text-neutral-500">
        Didn't receive the email? Check your spam folder.
      </p>
      <UButton
        icon="i-heroicons-chat-bubble-left-right"
        @click="crisp.openChat"
      >
        Need help?
      </UButton>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'default',
  middleware: ['self-hosted'],
})

useOpnSeoMeta({
  title: 'License Purchase Successful',
})

const crisp = useCrisp()
</script>
