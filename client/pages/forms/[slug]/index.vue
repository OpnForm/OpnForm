<template>
  <div
    id="public-form"
    data-testid="public-form-page"
    class="flex flex-col min-h-screen"
  >
    <div class="w-full mx-auto flex flex-col grow h-full">
      <div
        v-if="formUnavailable"
        class="flex grow items-center justify-center p-6"
      >
        <div class="max-w-md text-center">
          <h1 class="text-2xl font-semibold text-neutral-900">
            This form is temporarily unavailable
          </h1>
          <p class="mt-3 text-neutral-500">
            We couldn't load the form right now. Please check your connection and try again.
          </p>
          <button
            type="button"
            class="mt-6 rounded-lg bg-blue-500 px-4 py-2 font-medium text-white hover:bg-blue-600"
            @click="retryForm"
          >
            Try again
          </button>
        </div>
      </div>
      <div v-else-if="formNotFound || (!formLoading && !form)">
        <NotFoundForm />
      </div>
      <div v-else-if="formLoading">
        <p class="text-center mt-6 p-4">
          <loader class="h-6 w-6 text-blue-500 mx-auto" />
        </p>
      </div>
      <template v-else>
        <FormAnalyticsScript
          v-if="form.analytics?.provider && form.analytics?.tracking_id"
          ref="analyticsScriptRef"
          :form="form"
        />
        <OpenCompleteForm
          ref="openCompleteFormRef"
          :form="form"
          class="w-full grow min-h-0"
          :dark-mode="darkMode"
          :mode="FormMode.LIVE"
          @password-entered="passwordEntered"
          @submitted="onFormSubmitted"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
import OpenCompleteForm from "~/components/open/forms/OpenCompleteForm.vue"
import FormAnalyticsScript from "~/components/open/forms/FormAnalyticsScript.vue"
import sha256 from 'js-sha256'
import { onBeforeRouteLeave } from 'vue-router'
import {
  disableDarkMode,
  handleDarkMode,
  handleTransparentMode,
  focusOnFirstFormElement,
  useDarkMode
} from '~/lib/forms/public-page'
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import { formsApi } from '~/api'
import { customDomainUsed } from '~/lib/utils.js'
import {
  getPublicFormResponseStatus,
  isPublicFormNotFoundError,
  publicFormRetryDelay,
  shouldRetryPublicFormRequest,
} from '~/lib/forms/public-form-loading.js'

const crisp = useCrisp()
const appStore = useAppStore()
const darkMode = useDarkMode()
const isIframe = useIsIframe()
const slug = useRoute().params.slug
const { t } = useI18n()
const { performRedirect } = useSubdomainRedirect()

// Use TanStack Query to load the form
const {
  data: form,
  isLoading: formLoading,
  error: formError,
  refetch: refetchForm,
  suspense,
} = useForms().detail(slug, {
  retry: shouldRetryPublicFormRequest,
  retryDelay: publicFormRetryDelay,
  refetchOnWindowFocus: false,
  requestOptions: {
    retry: false,
  },
})

const retainedFormErrorStatus = useState(`public-form-error-status:${slug}`, () => null)
const formErrorStatus = computed(() => {
  return formError.value
    ? getPublicFormResponseStatus(formError.value)
    : retainedFormErrorStatus.value
})
const formNotFound = computed(() => formErrorStatus.value === 404)
const formUnavailable = computed(() => formErrorStatus.value === 503)
const retryingForm = ref(false)

const retryForm = () => {
  if (retryingForm.value) return

  retryingForm.value = true
  refetchForm().finally(() => {
    retryingForm.value = false
  })
}

if (import.meta.server) {
  await suspense()
}

const passwordError = ref(null)

// Provide password error state for child component
provide('passwordError', passwordError)
const analyticsScriptRef = ref(null)

const onFormSubmitted = () => {
  if (analyticsScriptRef.value?.trackFormSubmit) {
    analyticsScriptRef.value.trackFormSubmit()
  }
}

const passwordEntered = function (password) {
  const usesHttps = import.meta.client && window.location.protocol === 'https:'
  const cookie = useCookie('password-' + slug, {
    maxAge: 60 * 60 * 7,
    // Secure cookies are rejected on plain HTTP self-hosted instances.
    sameSite: usesHttps ? 'none' : 'lax',
    secure: usesHttps
  })
  cookie.value = sha256(password)
  
  // Clear any previous error
  passwordError.value = null
  
  nextTick(() => {
    refetchForm().then(() => {
      if (form.value?.is_password_protected) {
        // Set error message - child component will pick it up
        passwordError.value = t('forms.invalid_password')
      } else {
        trackFormView()
      }
    })
  })
}

// Preserve real 404s while exposing transient upstream failures as retryable 503s.
if (import.meta.server && formError.value) {
  const event = useRequestEvent()
  const responseStatus = getPublicFormResponseStatus(formError.value)
  retainedFormErrorStatus.value = responseStatus
  console.error(`Error loading form [${slug}]:`, formError.value)

  if (responseStatus === 404) {
    await performRedirect({ skipIfIframe: true })
  }

  setResponseStatus(
    event,
    responseStatus,
    responseStatus === 404 ? 'Page Not Found' : 'Service Unavailable',
  )
}

// Adapt page to form: colors, custom code etc when form is loaded
watch(form, (newForm) => {
  if (newForm) {
    retainedFormErrorStatus.value = null
    handleDarkMode(newForm?.dark_mode)
    handleTransparentMode(newForm?.transparent_background)

    // Remove 'hidden' class from html tag if present
    nextTick(() => {
      if (import.meta.client) {
        window.document.documentElement.classList.remove('hidden')
      }
    })
  }
}, { immediate: true })

watch(formError, (error) => {
  if (error) {
    retainedFormErrorStatus.value = getPublicFormResponseStatus(error)
  }
})

// Handle client-side 404 redirects for forms (subdomain redirect feature)
watch([formLoading, formError], async ([loading, error]) => {
  if (import.meta.client && !loading && isPublicFormNotFoundError(error)) {
    await performRedirect({ skipIfIframe: true })
  }
})

onMounted(() => {
  crisp.hideChat()
  appStore.hideFeatureBaseButton()
  document.body.classList.add('public-page')
  if (form.value) {
    handleDarkMode(form.value?.dark_mode)
    handleTransparentMode(form.value?.transparent_background)

    // Remove 'hidden' class from html tag if present
    nextTick(() => {
      if (import.meta.client) {
        window.document.documentElement.classList.remove('hidden')
      }
    })

    if (import.meta.client) {
      const allowSelfHosted = !!useFeatureFlag('custom_code.enable_self_hosted', false)
      const isSelfHosted = !!useFeatureFlag('self_hosted', false)
      const isCustomDomain = customDomainUsed()

      const canExecuteCustomCode = isCustomDomain || (isSelfHosted && allowSelfHosted)

      // Concatenate workspace and form custom code when injecting
      const codeToInject = effectiveCustomCode.value
      if (codeToInject && canExecuteCustomCode) {
        const scriptEl = document.createRange().createContextualFragment(codeToInject)
        try {
          document.head.append(scriptEl)
        } catch (e) {
          console.error('Error appending custom code', e)
        }
      }
      if (!isIframe && form.value?.auto_focus) focusOnFirstFormElement()
    }

    trackFormView()
  }
})

  // Track form view
let hasViewedForm = false
const trackFormView = () => {
  if (import.meta.client && !form.value?.is_password_protected && !hasViewedForm) {
    hasViewedForm = true
    nextTick(() => {
      formsApi.view(form.value.slug)
    })
  }
}

onBeforeRouteLeave(() => {
  appStore.showFeatureBaseButton()
  document.body.classList.remove('public-page')
  crisp.showChat()
  disableDarkMode()
})

const pageMeta = computed(() => {
  if (form.value?.seo_meta) {
    return form.value.seo_meta
  }
  return {}
})

const getFontUrl = computed(() => {
  if(!form.value || !form.value.font_family) return null
  const family = form.value.font_family.replace(/ /g, '+')
  return `https://fonts.googleapis.com/css?family=${family}:wght@400,500,700,800,900&display=swap`
})

const headLinks = computed(() => {
  const links = []
  if (form.value && form.value.font_family) {
    links.push({
        rel: 'stylesheet',
        href: getFontUrl.value
    })
  }
  if (pageMeta.value.page_favicon) {
    links.push({
        rel: 'icon', type: 'image/x-icon',
        href: pageMeta.value.page_favicon
    })
    links.push({
        rel: 'apple-touch-icon',
        type: 'image/png',
        href: pageMeta.value.page_favicon
    })
    links.push({
      rel: 'shortcut icon',
      href: pageMeta.value.page_favicon
    })
  }
  return links
})
    
useOpnSeoMeta({
  title: () => {
    if (pageMeta.value.page_title) {
      return pageMeta.value.page_title
    }
    return form.value ? form.value.title : 'Create beautiful forms'
  },
  description: () => {
    if (pageMeta.value.page_description) {
      return pageMeta.value.page_description
    }
    return null
  },
  ogImage: () => {
    if (pageMeta.value.page_thumbnail) {
      return pageMeta.value.page_thumbnail
    }
    return (form.value && form.value?.cover_picture) ? form.value?.cover_picture : null
  },
  robots: () => {
    return (form.value && form.value?.can_be_indexed) ? null : 'noindex, nofollow'
  }
}, true)

const getHtmlClass = computed(() => {
  return {
    dark: form.value?.dark_mode === 'dark',
    hidden: form.value?.dark_mode === 'auto' && import.meta.server,
  }
})

// Concatenate workspace and form custom code (workspace first, then form)
// Only when actually injecting into head tag
const effectiveCustomCode = computed(() => {
  const workspaceSettings = form.value?.workspace?.settings || {}
  const workspaceCode = workspaceSettings.custom_code || ''
  const formCode = form.value?.custom_code || ''
  
  if (!workspaceCode && !formCode) return null
  
  return (workspaceCode + '\n' + formCode).trim()
})

const effectiveCustomCss = computed(() => {
  const workspaceSettings = form.value?.workspace?.settings || {}
  const workspaceCss = workspaceSettings.custom_css || ''
  const formCss = form.value?.custom_css || ''
  
  if (!workspaceCss && !formCss) return null
  
  return (workspaceCss + '\n' + formCss).trim()
})

// Check if SDK should be loaded for custom code support
const shouldLoadLocalSdk = computed(() => !!effectiveCustomCode.value)

useHead({
  htmlAttrs: {
    dir: () => form.value?.layout_rtl ? 'rtl' : 'ltr',
    class: getHtmlClass.value,
    lang: () => form.value?.language || 'en'
  },
  titleTemplate: (titleChunk) => {
    if (pageMeta.value.page_title) {
      // Disable template if custom SEO title
      return titleChunk
    }
    return titleChunk ? `${titleChunk} - OpnForm` : 'OpnForm'
  },
  link: headLinks.value,
  meta: pageMeta.value.page_favicon ? [
    {
      name: 'mobile-web-app-capable',
      content: 'yes'
    },
    {
      name: 'apple-mobile-web-app-status-bar-style',
      content: 'black-translucent'
    },
  ] : {},
  script: computed(() => {
    const scripts = [{ src: '/widgets/iframeResizer.contentWindow.min.js' }]
    // Load local SDK stub before custom code if needed
    if (shouldLoadLocalSdk.value) {
      scripts.unshift({ src: '/widgets/opnform-local.js' })
    }
    return scripts
  }),
  style: computed(() => effectiveCustomCss.value ? [
    { key: 'custom-css', textContent: effectiveCustomCss.value }
  ] : [])
})

definePageMeta({
  layout: 'empty'
})
</script>
