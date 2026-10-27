import { httpUrl } from '~/lib/self-hosted-public.js'

export function useSelfHostedPresentation() {
  const config = useRuntimeConfig().public.selfHosted

  return {
    name: config?.name || 'OpnForm',
    logoUrl: httpUrl(config?.logoUrl) || '/img/logo.svg',
    privacyPolicyUrl: httpUrl(config?.privacyPolicyUrl),
    termsUrl: httpUrl(config?.termsUrl),
  }
}
