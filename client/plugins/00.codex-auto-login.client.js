import { authApi } from '~/api'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()

  const isCodexAutoLoginEnabled = config.public.codexAutoLogin
    && config.public.codexAutoLoginEmail
    && config.public.codexAutoLoginPassword
    && !navigator.webdriver
    && window.location.pathname === '/'

  if (!isCodexAutoLoginEnabled) {
    return
  }

  const authStore = useAuthStore()
  authStore.clearToken()

  return authApi.login({
    email: config.public.codexAutoLoginEmail,
    password: config.public.codexAutoLoginPassword,
  })
    .then((tokenData) => useAuthFlow().handleAuthSuccess(tokenData, 'codex'))
    .then(() => navigateTo({ name: 'home' }))
    .catch((error) => {
      console.warn('Codex auto-login failed.', error)
    })
})
