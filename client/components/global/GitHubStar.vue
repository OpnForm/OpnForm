<template>
  <div v-if="isVisible" class="relative group">
    <a class="github-button" href="https://github.com/OpnForm/OpnForm" data-color-scheme="no-preference: light; light: light; dark: dark;" data-size="large" data-show-count="true" aria-label="Star OpnForm on GitHub">Star</a>
    <UButton
      class="absolute -top-2 -right-2 z-10 bg-white dark:bg-gray-800 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
      aria-label="Hide"
      icon="i-heroicons-x-mark"
      variant="soft"
      color="error"
      size="xs"
      @click="hide"
    />
  </div>
</template>

<script setup>
const COOKIE_NAME = 'github_star_dismissed'
const COOKIE_EXPIRY_DAYS = 14

const dismissedCookie = useCookie(COOKIE_NAME, {
  default: () => false,
  maxAge: COOKIE_EXPIRY_DAYS * 24 * 60 * 60 * 1000, // 14 days in milliseconds
  sameSite: 'lax',
  secure: true,
  httpOnly: false,
})

const isVisible = computed(() => !dismissedCookie.value)

function hide() {
  dismissedCookie.value = true
}

const scriptTag = computed(() => {
  if (!isVisible.value) {
    return []
  }
  return [
    {
      src: 'https://buttons.github.io/buttons.js',
      async: true,
      defer: true,
    },
  ]
})

useHead({
  script: scriptTag,
})
</script> 