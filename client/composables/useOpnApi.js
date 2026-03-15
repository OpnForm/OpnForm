import { getDomain, getHost, customDomainUsed } from "~/lib/utils.js"

function addAuthHeader(request, options) {
  const authStore = useAuthStore()
  if (authStore.token) {
    options.headers = {
      Authorization: `Bearer ${authStore.token}`,
      ...options.headers,
    }
  }
}

function addPasswordToFormRequest(request, options) {
  if (!request || !request.startsWith("/forms/")) return

  const slug = request.split("/")[2]

  const passwordCookie = useCookie("password-" + slug, {
    maxAge: 60 * 60 * 24 * 30,
  }) // 30 days
  if (slug !== undefined && slug !== "" && passwordCookie.value !== undefined) {
    options.headers["form-password"] = passwordCookie.value
  }
}

/**
 * Add custom domain header if custom domain is used
 */
function addCustomDomainHeader(request, options) {
  if (!customDomainUsed()) return
  options.headers["x-custom-domain"] = getDomain(getHost())
}

export function getOpnRequestsOptions(request, opts) {
  const config = useRuntimeConfig()

  if (opts.body && opts.body instanceof FormData) {
    opts.headers = {
      charset: "utf-8",
      ...opts.headers,
    }
  }

  opts.headers = { accept: "application/json", ...opts.headers }

  // Authenticate requests coming from the server
  if (import.meta.server && config.apiSecret) {
    opts.headers["x-api-secret"] = config.apiSecret
  }

  addAuthHeader(request, opts)
  addPasswordToFormRequest(request, opts)
  addCustomDomainHeader(request, opts)

  if (!opts.baseURL) {
    // Use privateApiBase only on server side, fallback to public.apiBase on client
    opts.baseURL = (import.meta.server && config.privateApiBase) || config.public.apiBase
  }

  return {
    async onResponseError({ response }) {
      const { status } = response
      if (status === 401) {
        // Do not run token-expiry UX during SSR. Server-side requests can fail
        // for context-specific reasons and should be retried client-side first.
        if (import.meta.client) {
          const { handleTokenExpiry } = useAuthFlow()
          await handleTokenExpiry()
        }
      } else if (status === 420) {
        // If invalid domain, redirect to main domain
        console.warn("Invalid response from back-end - redirecting to main domain")
        window.location.href =
          config.public.appUrl + "?utm_source=failed_custom_domain_redirect"
      } else if (status >= 500) {
        console.error("Request error", status)
      }
    },
    ...opts,
  }
}

export const opnFetch = (request, opts = {}) => {
  return $fetch(request, getOpnRequestsOptions(request, opts))
}

export const useOpnApi = (request, opts = {}) => {
  return useFetch(request, getOpnRequestsOptions(request, opts))
}
