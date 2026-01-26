import { computed, ref } from 'vue'
import { vi } from 'vitest'

vi.mock('~/middleware/auth', () => ({
  default: () => {},
}))

vi.mock('~/middleware/auth.js', () => ({
  default: () => {},
}))

globalThis.useAuthStore = () => ({
  token: null,
  initStore: vi.fn(),
  clearToken: vi.fn(),
})

globalThis.useQueryClient = () => ({
  getQueryData: vi.fn(),
  clear: vi.fn(),
})

globalThis.useAuth = () => ({
  user: () => ({ suspense: vi.fn() }),
})

globalThis.useWorkspaces = () => ({
  list: () => ({ suspense: vi.fn() }),
})

globalThis.useOverlay = () => ({
  create: () => ({
    open: vi.fn(),
    close: vi.fn(),
  }),
})

globalThis.useRoute = () => ({
  query: {},
})

globalThis.useRouter = () => ({
  replace: vi.fn(),
  push: vi.fn(),
})

globalThis.useRouteQuery = () => ref(null)

globalThis.useIsAuthenticated = () => ({
  isAuthenticated: computed(() => false),
})

globalThis.useGtm = () => ({
  trackEvent: vi.fn(),
})

globalThis.navigateTo = vi.fn()

globalThis.useCookie = () => ({
  value: null,
})
