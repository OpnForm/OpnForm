import { Crisp } from "crisp-sdk-web"

export default defineNuxtPlugin(() => {
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
  const initCrisp = () => {
    if (window.Crisp) return // Already initialized

    Crisp.configure(crispWebsiteId)
    window.Crisp = Crisp

    // Set up Crisp callbacks (same as useCrisp().onCrispInit() + showChat())
    const appStore = useAppStore()
    Crisp.chat.onChatOpened(() => {
      appStore.crisp.chatOpened = true
    })
    Crisp.chat.onChatClosed(() => {
      appStore.crisp.chatOpened = false
    })
    Crisp.chat.show()
    appStore.crisp.hidden = false
  }

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
