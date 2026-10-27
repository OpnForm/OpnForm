import { httpUrl } from '~/lib/self-hosted-public.js'

export function useSelfHostedPresentation() {
  const config = useRuntimeConfig().public

  return {
    name: config.selfHostedName || 'OpnForm',
    logoUrl: httpUrl(config.selfHostedLogoUrl) || '/img/logo.svg',
    privacyPolicyUrl: httpUrl(config.selfHostedPrivacyPolicyUrl),
    termsUrl: httpUrl(config.selfHostedTermsUrl),
  }
}
