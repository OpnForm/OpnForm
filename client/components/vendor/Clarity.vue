<template />

<script setup>
import Clarity from '@microsoft/clarity'
import { useIsAuthenticated } from '~/composables/useAuthFlow'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const isIframe = useIsIframe()
const loaded = ref(false)
const runtimeConfig = useRuntimeConfig()
const clarityProjectId = computed(() => runtimeConfig.public.clarityProjectId || null)
const { isAuthenticated } = useIsAuthenticated()

async function loadClarity() {
  const projectId = clarityProjectId.value
  if (!projectId || !isAuthenticated.value || isIframe || loaded.value) return

  try {
    Clarity.init(projectId)
    if (user.value?.id) {
      Clarity.identify(String(user.value.id), null, null, String(user.value.id))
    }
    loaded.value = true
  } catch (error) {
    console.error('[Clarity] Failed to init', error)
  }
}

watch(isAuthenticated, (val) => {
  if (val) loadClarity()
})

watch(user, (val) => {
  if (import.meta.server) return
  if (!loaded.value || !isAuthenticated.value || isIframe) return
  if (val?.id) {
    try {
      Clarity.identify(String(val.id), null, null, String(val.id))
    } catch (error) {
      console.error('[Clarity] Failed to identify user', error)
    }
  }
})

onMounted(() => {
  if (import.meta.server) return
  loadClarity()
})
</script>


