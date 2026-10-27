<template>
  <footer class="mx-auto flex w-full max-w-5xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted sm:flex-row">
    <a :href="cloudUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 hover:text-highlighted">
      <img src="/img/logo.svg" alt="" class="size-4" />
      Powered by OpnForm
      <UIcon name="i-heroicons-arrow-up-right" class="size-3.5" />
    </a>
    <nav aria-label="Instance resources" class="flex flex-wrap justify-center gap-x-5 gap-y-2">
      <a href="https://docs.opnform.com" target="_blank" rel="noopener noreferrer" class="hover:text-highlighted">Documentation</a>
      <a href="https://github.com/OpnForm/OpnForm" target="_blank" rel="noopener noreferrer" class="hover:text-highlighted">GitHub</a>
      <a v-if="privacyPolicyUrl" :href="privacyPolicyUrl" class="hover:text-highlighted">Privacy policy</a>
      <a v-if="termsUrl" :href="termsUrl" class="hover:text-highlighted">Terms</a>
    </nav>
  </footer>
</template>

<script setup>
const { privacyPolicyUrl, termsUrl } = useSelfHostedPresentation()
const cloudUrl = 'https://opnform.com?utm_source=self_hosted&utm_medium=instance&utm_campaign=footer'
</script>
