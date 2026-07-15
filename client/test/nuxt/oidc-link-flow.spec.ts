import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import OidcCallbackPage from '~/pages/auth/[slug]/callback.vue'
import LoginForm from '~/components/pages/auth/components/LoginForm.vue'

const {
    startLinkSpy,
    completeLinkIfNeededSpy,
    redirectToOidcProviderSpy,
    canAutomaticallyRetryOidcSignInSpy,
    clearOidcAutomaticRetrySpy,
    consumeOidcStateVerifierSpy,
    featureFlagValues,
    markOidcAutomaticRetrySpy,
    storeOidcStateVerifierSpy,
} = vi.hoisted(() => ({
    startLinkSpy: vi.fn(),
    completeLinkIfNeededSpy: vi.fn(() => Promise.resolve(true)),
    redirectToOidcProviderSpy: vi.fn(),
    canAutomaticallyRetryOidcSignInSpy: vi.fn(() => true),
    clearOidcAutomaticRetrySpy: vi.fn(),
    consumeOidcStateVerifierSpy: vi.fn(() => null),
    featureFlagValues: {},
    markOidcAutomaticRetrySpy: vi.fn(),
    storeOidcStateVerifierSpy: vi.fn(),
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
        redirect: vi.fn(),
        link: vi.fn(),
    },
}))

vi.mock('~/lib/oidc/redirect', () => ({
    redirectToOidcProvider: redirectToOidcProviderSpy,
}))

vi.mock('~/lib/oidc/state-verifier', () => ({
    canAutomaticallyRetryOidcSignIn: canAutomaticallyRetryOidcSignInSpy,
    clearOidcAutomaticRetry: clearOidcAutomaticRetrySpy,
    consumeOidcStateVerifier: consumeOidcStateVerifierSpy,
    markOidcAutomaticRetry: markOidcAutomaticRetrySpy,
    storeOidcStateVerifier: storeOidcStateVerifierSpy,
}))

vi.mock('~/composables/useFeatureFlag.js', () => ({
    useFeatureFlag: (flag, defaultValue = null) => featureFlagValues[flag] ?? defaultValue,
}))

describe('OIDC link flow', () => {
    const setupGlobals = (routeOverrides = {}, { featureFlags = {}, formOverrides = {} } = {}) => {
        const router = {
            push: vi.fn(),
            replace: vi.fn(),
        }

        const route = {
            params: { slug: 'test-sso' },
            query: {},
            ...routeOverrides,
        }
        const form = {
            email: '',
            password: '',
            remember: false,
            busy: false,
            post: vi.fn(),
            mutate: vi.fn(),
            ...formOverrides,
        }
        Object.assign(featureFlagValues, featureFlags)

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
        vi.stubGlobal('useFeatureFlag', (flag) => featureFlags[flag] ?? false)
        vi.stubGlobal('useForm', () => form)
        vi.stubGlobal('useAuth', () => ({
            login: () => vi.fn(),
        }))
        vi.stubGlobal('useWindowMessage', () => ({
            listen: vi.fn(),
            send: vi.fn(),
        }))

        return { router, route, form }
    }

    beforeEach(() => {
        vi.clearAllMocks()
        Object.keys(featureFlagValues).forEach((key) => delete featureFlagValues[key])
        canAutomaticallyRetryOidcSignInSpy.mockReturnValue(true)
    })

    afterEach(() => {
        sessionStorage.clear()
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

    it('automatically starts one fresh sign-in when an authorization code was already used', async () => {
        vi.useFakeTimers()
        const apiModule = await import('~/api') as { oidcApi: any }
        const oidcApi = apiModule.oidcApi
        setupGlobals()

        oidcApi.callback.mockRejectedValue({
            message: 'AADSTS54005: Authorization code was already redeemed',
        })
        oidcApi.redirect.mockResolvedValue({
            redirect_url: 'https://idp.example.com/authorize',
            state: 'fresh-state',
            state_verifier: 'fresh-verifier',
        })

        const wrapper = mount(OidcCallbackPage, {
            global: {
                stubs: {
                    TwoFactorVerificationModal: true,
                    Loader: true,
                    UAlert: {
                        template: '<div class="alert">{{ title }} {{ description }}</div>',
                        props: ['title', 'description'],
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

        expect(wrapper.text()).toContain('Reconnecting securely...')
        expect(oidcApi.redirect).toHaveBeenCalledOnce()
        expect(markOidcAutomaticRetrySpy).toHaveBeenCalledOnce()
        expect(storeOidcStateVerifierSpy).toHaveBeenCalledOnce()
        expect(redirectToOidcProviderSpy).toHaveBeenCalledWith('https://idp.example.com/authorize')
        vi.useRealTimers()
    })

    it('shows a recovery action instead of retrying a second time', async () => {
        vi.useFakeTimers()
        const apiModule = await import('~/api') as { oidcApi: any }
        const oidcApi = apiModule.oidcApi
        setupGlobals()
        canAutomaticallyRetryOidcSignInSpy.mockReturnValue(false)

        oidcApi.callback.mockRejectedValue({
            message: 'AADSTS54005: Authorization code was already redeemed',
        })

        const wrapper = mount(OidcCallbackPage, {
            global: {
                stubs: {
                    TwoFactorVerificationModal: true,
                    Loader: true,
                    UAlert: {
                        template: '<div class="alert">{{ title }} {{ description }}</div>',
                        props: ['title', 'description'],
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

        expect(oidcApi.redirect).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('We could not reconnect automatically')
        expect(wrapper.text()).toContain('Back to sign in')
        vi.useRealTimers()
    })

    it('shows the retry delay instead of retrying a throttled callback', async () => {
        vi.useFakeTimers()
        const apiModule = await import('~/api') as { oidcApi: any }
        const oidcApi = apiModule.oidcApi
        setupGlobals()

        oidcApi.callback.mockRejectedValue({
            response: {
                status: 429,
                headers: { 'retry-after': '42' },
                _data: { retry_after: 42 },
            },
        })

        const wrapper = mount(OidcCallbackPage, {
            global: {
                stubs: {
                    TwoFactorVerificationModal: true,
                    Loader: true,
                    UAlert: {
                        template: '<div class="alert">{{ title }} {{ description }}</div>',
                        props: ['title', 'description'],
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

        expect(oidcApi.redirect).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('Too many sign-in requests. Please try again in 42 seconds.')
        vi.useRealTimers()
    })

    it('shows the retry delay when the automatic OIDC recovery is rate limited', async () => {
        vi.useFakeTimers()
        const apiModule = await import('~/api') as { oidcApi: any }
        const oidcApi = apiModule.oidcApi
        setupGlobals()

        oidcApi.callback.mockRejectedValue({
            message: 'AADSTS54005: Authorization code was already redeemed',
        })
        oidcApi.redirect.mockRejectedValue({
            response: {
                status: 429,
                headers: { 'retry-after': '30' },
                _data: { retry_after: 30 },
            },
        })

        const wrapper = mount(OidcCallbackPage, {
            global: {
                stubs: {
                    TwoFactorVerificationModal: true,
                    Loader: true,
                    UAlert: {
                        template: '<div class="alert">{{ title }} {{ description }}</div>',
                        props: ['title', 'description'],
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

        expect(oidcApi.redirect).toHaveBeenCalledOnce()
        expect(wrapper.text()).toContain('Too many sign-in requests. Please try again in 30 seconds.')
        vi.useRealTimers()
    })

    it('disables the SSO continue action until the rate-limit delay expires', async () => {
        vi.useFakeTimers()
        setupGlobals({}, {
            featureFlags: {
                'oidc.available': true,
                'oidc.forced': true,
            },
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
                    TextInput: {
                        template: '<input :name="name" />',
                        props: ['name'],
                    },
                    CheckboxInput: true,
                    UAlert: {
                        template: '<div class="alert">{{ title }} {{ description }}</div>',
                        props: ['title', 'description'],
                    },
                    UButton: {
                        template: '<button :disabled="disabled">{{ label }}</button>',
                        props: ['label', 'disabled', 'color', 'variant', 'to'],
                    },
                    VTransition: {
                        template: '<div><slot /></div>',
                    },
                    NuxtLink: true,
                    ClientOnly: true,
                    GoogleOneTap: true,
                },
            },
        })

        const form = (wrapper.vm as any).form
        form.email = 'user@company.com'
        form.post = vi.fn()
            .mockResolvedValueOnce({ action: 'redirect', slug: 'company-sso' })
            .mockRejectedValueOnce({
                response: {
                    status: 429,
                    headers: { 'retry-after': '30' },
                    _data: { retry_after: 30 },
                },
            })

        await (wrapper.vm as any).checkOidcOptions()
        await flushPromises()

        expect(wrapper.text()).toContain('Please wait before trying again')
        expect(wrapper.text()).toContain('Try again in 30s')
        expect(wrapper.find('button').attributes('disabled')).toBeDefined()
        expect(wrapper.find('input[name="password"]').exists()).toBe(false)

        vi.advanceTimersByTime(1000)
        await flushPromises()
        expect(wrapper.text()).toContain('Try again in 29s')
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
