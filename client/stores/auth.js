import { defineStore } from "pinia"
import { authApi } from "~/api"

const AUTH_COOKIE_NAME = "opnform_token"
const LEGACY_AUTH_COOKIE_NAME = "token"
const ADMIN_AUTH_COOKIE_NAME = "opnform_admin_token"
const LEGACY_ADMIN_AUTH_COOKIE_NAME = "admin_token"

export const useAuthStore = defineStore("auth", {
  state: () => {
    return {
      token: null,
      admin_token: null,
      user: null,
    }
  },
  getters: {
    check: (state) => state.user !== null && state.user !== undefined,
    has_active_license: (state) => state.user !== null && state.user !== undefined && state.user.active_license !== null,
    isImpersonating: (state) =>
      state.admin_token !== null && state.admin_token !== undefined,
  },
  actions: {
    // Stores admin token temporarily for impersonation
    startImpersonating() {
      this.setAdminToken(this.token)
    },
    // Stop admin impersonation
    stopImpersonating() {
      // When stopping impersonation, we don't have expiration info for the admin token
      // Use a default long expiration (24 hours) to ensure the admin can continue working
      this.setToken(this.admin_token, 60 * 60 * 24)
      this.setAdminToken(null)
    },

    setToken(token, expiresIn) {
      // Set cookie with expiration if provided
      const cookieOptions = {}
      
      if (expiresIn) {
        // expiresIn is in seconds, maxAge also needs to be in seconds
        cookieOptions.maxAge = expiresIn
      }
      
      this.setCookie(AUTH_COOKIE_NAME, token, cookieOptions)
      this.token = token
    },

    setAdminToken(token) {
      this.setCookie(ADMIN_AUTH_COOKIE_NAME, token)
      this.admin_token = token
    },

    setCookie(name, value, options = {}) {
      if (import.meta.client) {
        const secureDefault = (typeof window !== 'undefined') ? window.location.protocol === 'https:' : true
        const embedded = typeof window !== 'undefined' && window.top !== window.self
        const safeOptions = {
          path: options.path ?? '/',
          sameSite: options.sameSite ?? (embedded ? 'none' : 'lax'),
          secure: options.secure ?? (embedded ? true : secureDefault),
          ...options,
        }
        useCookie(name, safeOptions).value = value
      }
    },

    initStore(token, adminToken) {
      // Prefer explicit values from cookies, but do not clobber a live in-memory
      // token during client-side navigation when cookie reactivity lags behind.
      if (token !== undefined) {
        this.token = token ?? this.token
      }

      if (adminToken !== undefined) {
        this.admin_token = adminToken ?? this.admin_token
      }
    },

    setUser(user) {
      this.user = user
    },

    updateUser(payload) {
      this.user = payload
    },

    logout() {
      authApi.logout().catch(() => {})

      this.user = null
      
      this.clearToken()
    },

    clearToken(){
      this.setCookie(AUTH_COOKIE_NAME, null, { maxAge: 0 })
      this.setCookie(LEGACY_AUTH_COOKIE_NAME, null, { maxAge: 0 })
      this.token = null
    },

    clearTokens(){
      this.clearToken()
      this.setCookie(ADMIN_AUTH_COOKIE_NAME, null, { maxAge: 0 })
      this.setCookie(LEGACY_ADMIN_AUTH_COOKIE_NAME, null, { maxAge: 0 })
      this.admin_token = null
    },
  },
})
