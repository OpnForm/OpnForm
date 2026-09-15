export default defineNuxtPlugin((nuxtApp) => {
  const isIframe = useIsIframe()
  const router = useRouter()
  const crispWebsiteId = useRuntimeConfig().public.crispWebsiteId

  // Skip initialization in iframes or if no website ID
  if (isIframe || !crispWebsiteId) {
    return
  }

  const currentRoute = useRoute()
  const isPublicFormPage = () => currentRoute.name === 'forms-slug'

  // Initialize Crisp SDK and set up event listeners
  let crispPromise = null
  const initCrisp = () => {
    if (window.Crisp) return Promise.resolve(window.Crisp)
    if (crispPromise) return crispPromise

    crispPromise = import('crisp-sdk-web').then(({ Crisp }) => {
      Crisp.configure(crispWebsiteId)
      window.Crisp = Crisp

      const appStore = useAppStore()
      Crisp.chat.onChatOpened(() => {
        appStore.crisp.chatOpened = true
      })
      Crisp.chat.onChatClosed(() => {
        appStore.crisp.chatOpened = false
      })
      Crisp.chat.show()
      appStore.crisp.hidden = false
      return Crisp
    })

    return crispPromise
  }

  nuxtApp.provide('ensureCrisp', initCrisp)

  // If not on public form page, initialize immediately
  if (!isPublicFormPage()) {
    initCrisp()
  } else {
    // Lazy init: wait for navigation away from public form page
    const unwatch = router.afterEach((to) => {
      if (to.name !== 'forms-slug') {
        initCrisp()
        unwatch() // Stop watching after initialization
      }
    })
  }
})
