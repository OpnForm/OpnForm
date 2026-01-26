import { oidcApi } from "~/api"

export const useOidcLinking = () => {
  const router = useRouter()
  const route = useRoute()
  const alert = useAlert()

  const linkToken = computed(() => {
    const token = route.query.oidc_link_token
    if (Array.isArray(token)) {
      return token[0]
    }
    return token || null
  })

  const startLink = (token) => {
    if (!token) {
      return
    }

    router.push({
      name: 'login',
      query: { oidc_link_token: token },
    })
  }

  const clearLinkToken = () => {
    if (!linkToken.value) {
      return
    }

    const nextQuery = { ...route.query }
    delete nextQuery.oidc_link_token
    router.replace({ query: nextQuery })
  }

  const completeLinkIfNeeded = () => {
    if (!linkToken.value) {
      return Promise.resolve(true)
    }

    return oidcApi.link(linkToken.value)
      .then(() => {
        alert.success("Your SSO account has been linked.")
        return true
      })
      .catch((error) => {
        const errorMessage = error.response?._data?.message || 'Failed to link your SSO account.'
        alert.error(errorMessage)
        return false
      })
      .finally(() => {
        clearLinkToken()
      })
  }

  return {
    linkToken,
    startLink,
    clearLinkToken,
    completeLinkIfNeeded,
  }
}
