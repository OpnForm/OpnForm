<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-50 px-4">
    <div class="max-w-lg w-full text-center space-y-6">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-neutral-100">
        <Icon name="i-heroicons-x-circle" class="h-10 w-10 text-neutral-400" />
      </div>

      <h1 class="text-2xl font-bold text-neutral-900">Checkout Canceled</h1>
      <p class="text-neutral-600">
        Your checkout was canceled. No charges were made.
      </p>
      <p class="text-sm text-neutral-500">
        You can try again from your self-hosted OpnForm instance.
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
  title: 'Checkout Canceled',
})

const crisp = useCrisp()
</script>
