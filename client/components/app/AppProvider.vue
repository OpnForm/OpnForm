<template>
  <UApp :toaster="toasterConfig" :tooltip="tooltipConfig">
    <slot />
  </UApp>
</template>

<script setup>
import { useAppStore } from "~/stores/app"

const appStore = useAppStore()

// Get Crisp chat state from the store
const crispChatOpened = computed(() => appStore.crisp.chatOpened)
const crispHidden = computed(() => appStore.crisp.hidden)

// Configure toaster positioning based on Crisp chat state
const toasterConfig = computed(() => {
  const baseConfig = {
    position: 'bottom-right',
    duration: 5000,
    expand: true,
  }

  // Dynamically adjust the UI class based on Crisp chat state
  if (crispHidden.value) {
    // Crisp is hidden: normal bottom-right position
    return {
      ...baseConfig,
      ui: {
        viewport: 'end-4 bottom-4',
      },
    }
  }

  if (crispChatOpened.value) {
    // Crisp chat is opened: keep default bottom-right (chat overlay already shifted)
    return {
      ...baseConfig,
      ui: {
        viewport: 'end-4 bottom-4',
      },
    }
  }

  // Crisp chat is closed but visible: move toasts above the chat button
  return {
    ...baseConfig,
    ui: {
      viewport: 'end-4 bottom-24',
    },
  }
})

const tooltipConfig = {
  delayDuration: 100
}

</script> 