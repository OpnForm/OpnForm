<template>
  <div v-if="isCaptchaRequired" class="mb-3 px-2 mt-4 mx-auto w-max">
    <ClientOnly>
      <CaptchaInput
        ref="captchaInputRef"
        :provider="provider"
        :form="form"
        :language="language"
        :dark-mode="darkMode"
      />
      <template #fallback>
        <USkeleton class="h-[78px] w-[304px]" />
      </template>
    </ClientOnly>
  </div>
</template>

<script setup>
import CaptchaInput from './CaptchaInput.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  formManager: {
    type: Object,
    required: true
  }
})

const captchaInputRef = ref(null)
const runtimeConfig = useRuntimeConfig().public

// Get form and config from the formManager
const form = computed(() => props.formManager.form)
const config = computed(() => props.formManager.config.value)
const structure = props.formManager.structure
const isLastPage = computed(() => structure.value?.isLastPage?.value ?? true)
const language = computed(() => config.value?.language || 'en')
const provider = computed(() => config.value?.captcha_provider || 'recaptcha')
const darkMode = computed(() => props.formManager.darkMode.value)

// Determine if captcha should be shown
const isCaptchaRequired = computed(() => {
  if (!config.value?.use_captcha || !isLastPage.value) {
    return false
  }

  const captchaProvider = config.value.captcha_provider
  if (captchaProvider === 'recaptcha') {
    return !!runtimeConfig.reCaptchaSiteKey
  } else if (captchaProvider === 'hcaptcha') {
    return !!runtimeConfig.hCaptchaSiteKey
  }
  return false
})

// Get field name based on provider
const captchaFieldName = computed(() => {
  return provider.value === 'recaptcha' 
    ? 'g-recaptcha-response' 
    : 'h-captcha-response'
})

// Reset captcha when page changes
watch(() => props.formManager.state.currentPage, () => {
  if (captchaInputRef.value) {
    captchaInputRef.value.reset()
  }
})

// Simplified watcher for form submission
watch(() => props.formManager.state.isProcessing, (isProcessing, wasProcessing) => {
  // Case 1: Form is starting to process and captcha is required but missing
  if (isProcessing && isCaptchaRequired.value && !form.value[captchaFieldName.value]) {
    form.value.errors.set(captchaFieldName.value, 'Please complete the captcha verification')
  }
  
  // Case 2: Form submission just ended AND there are errors
  if (!isProcessing && wasProcessing && form.value.errors.any() && captchaInputRef.value) {
    // Reset the captcha when validation fails
    captchaInputRef.value.reset()
  }
})
</script> 