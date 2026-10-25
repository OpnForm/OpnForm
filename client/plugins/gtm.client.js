import { createGtm, useGtm } from '@gtm-support/vue-gtm'

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const gtmId = config.public.gtm.id

  const route = useRoute()
  const isIframe = useIsIframe()
  const isPublicFormPage = route.name === 'forms-slug'
  
  nuxtApp.vueApp.use(createGtm({
    ...config.public.gtm,
    vueRouter: useRouter(),
  }))
  const gtm = useGtm()

  // Only enable GTM if not in a form page (for respondents) and not in an iframe
  if (gtmId && gtmId !== 'GTM-XXXXXX' && gtm && !isPublicFormPage && !isIframe) {
    
    // Override the enabled setting to true for this session
    gtm.enable(true)
        
    // Initialize Consent Mode
    window.dataLayer = window.dataLayer || []
    function gtag() {
      window.dataLayer.push(arguments)
    }
    
    gtag('consent', 'default', {
      'ad_storage': 'denied',
      'analytics_storage': 'denied',
      'functionality_storage': 'granted',
      'security_storage': 'granted'
    })

    gtag('consent', 'update', {
      'ad_storage': 'granted',
      'analytics_storage': 'granted',
    })
    
    // Enable IP anonymization
    gtag('set', 'anonymize_ip', true)
    
  }
  
  return {}
}) 