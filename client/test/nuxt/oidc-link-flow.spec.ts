import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import OidcCallbackPage from '~/pages/auth/[slug]/callback.vue'
import LoginForm from '~/components/pages/auth/components/LoginForm.vue'

const { startLinkSpy, completeLinkIfNeededSpy } = vi.hoisted(() => ({
    startLinkSpy: vi.fn(),
    completeLinkIfNeededSpy: vi.fn(() => Promise.resolve(true)),
}))

vi.mock('~/middleware/01.check-auth.global', () => ({
    default: () => { },
}))

vi.mock('~/plugins/pinia-history', () => ({
    default: () => { },
}))

vi.mock('~/plugins/pinia-history.js', () => ({
    default: () => { },
}))

vi.mock('~/composables/useAuthFlow', () => ({
    useAuthFlow: () => ({
        showTwoFactorModal: ref(false),
        pendingAuthToken: ref(null),
        handleTwoFactorVerified: vi.fn(() => Promise.resolve()),
        handleTwoFactorCancel: vi.fn(),
        handleTwoFactorError: vi.fn(() => null),
    }),
    useIsAuthenticated: () => ({
        isAuthenticated: ref(false),
    }),
}))

vi.mock('~/composables/query/useOAuth', () => ({
    useOAuth: () => ({
        guestConnect: vi.fn(),
    }),
}))

vi.mock('~/composables/query/useAuth', () => ({
    useAuth: () => ({
        login: () => vi.fn(),
    }),
}))

vi.mock('~/composables/useOidcLinking', () => ({
    useOidcLinking: () => ({
        linkToken: ref(null),
        startLink: startLinkSpy,
        clearLinkToken: vi.fn(),
        completeLinkIfNeeded: completeLinkIfNeededSpy,
    }),
}))

vi.mock('~/api', () => ({
    oidcApi: {
        callback: vi.fn(),
        link: vi.fn(),
    },
}))

describe('OIDC link flow', () => {
    const setupGlobals = (routeOverrides = {}) => {
        const router = {
            push: vi.fn(),
            replace: vi.fn(),
        }

        const route = {
            params: { slug: 'test-sso' },
            query: {},
            ...routeOverrides,
        }

        vi.stubGlobal('useRouter', () => router)
        vi.stubGlobal('useRoute', () => route)
        vi.stubGlobal('useAuthFlow', () => ({
            showTwoFactorModal: ref(false),
            pendingAuthToken: ref(null),
            handleTwoFactorVerified: vi.fn(() => Promise.resolve()),
            handleTwoFactorCancel: vi.fn(),
            handleTwoFactorError: vi.fn(() => null),
        }))
        vi.stubGlobal('useAlert', () => ({
            success: vi.fn(),
            error: vi.fn(),
        }))
        vi.stubGlobal('useAuthStore', () => ({
            token: null,
            initStore: vi.fn(),
            clearToken: vi.fn(),
        }))
        vi.stubGlobal('useQueryClient', () => ({
            getQueryData: vi.fn(),
            clear: vi.fn(),
        }))
        vi.stubGlobal('useAuth', () => ({
            user: () => ({ suspense: vi.fn() }),
        }))
        vi.stubGlobal('useWorkspaces', () => ({
            list: () => ({ suspense: vi.fn() }),
        }))
        vi.stubGlobal('useFeatureFlag', () => false)
        vi.stubGlobal('useForm', () => ({
            email: '',
            password: '',
            remember: false,
            busy: false,
            post: vi.fn(),
            mutate: vi.fn(),
        }))
        vi.stubGlobal('useAuth', () => ({
            login: () => vi.fn(),
        }))
        vi.stubGlobal('useWindowMessage', () => ({
            listen: vi.fn(),
            send: vi.fn(),
        }))

        return { router, route }
    }

    beforeEach(() => {
        vi.clearAllMocks()
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('shows link CTA when callback returns link required error', async () => {
        vi.useFakeTimers()
        const apiModule = await import('~/api') as { oidcApi: any }
        const oidcApi = apiModule.oidcApi
        setupGlobals()

        oidcApi.callback.mockRejectedValue({
            response: {
                _data: {
                    error: 'oidc_account_link_required',
                    link_token: 'token-123',
                    message: 'Link required',
                },
            },
        })

        const wrapper = mount(OidcCallbackPage, {
            global: {
                stubs: {
                    TwoFactorVerificationModal: true,
                    Loader: true,
                    UAlert: {
                        template: '<div class="alert">{{ description }}</div>',
                        props: ['description'],
                    },
                    UButton: {
                        template: '<button>{{ label }}<slot /></button>',
                        props: ['label', 'color', 'variant', 'to'],
                        emits: ['click'],
                    },
                },
            },
        })

        await flushPromises()
        vi.runAllTimers()
        await flushPromises()

        expect(wrapper.text()).toContain('Link existing account')
        vi.useRealTimers()
    })

    it('links account after two-factor verification when token is present', async () => {
        setupGlobals({
            query: { oidc_link_token: 'link-token-123' },
        })

        const wrapper = mount(LoginForm, {
            global: {
                stubs: {
                    ForgotPasswordModal: true,
                    TwoFactorVerificationModal: true,
                    VForm: {
                        template: '<form><slot /></form>',
                        props: ['form'],
                    },
                    TextInput: true,
                    CheckboxInput: true,
                    UButton: true,
                    VTransition: true,
                    NuxtLink: true,
                    ClientOnly: true,
                    GoogleOneTap: true,
                },
            },
        })

        const vm = wrapper.vm as any
        await vm.handleTwoFactorVerifiedAndRedirect({ token: 'verified-token' })

        expect(completeLinkIfNeededSpy).toHaveBeenCalled()
    })
})
