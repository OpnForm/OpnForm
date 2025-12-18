<template>
  <div
    v-if="form"
    class="open-complete-form flex flex-col min-h-full"
    :dir="form?.layout_rtl ? 'rtl' : 'ltr'"
    :style="formStyle"
  >
    <v-transition name="fade" mode="out-in">
      <div v-if="isAutoSubmit" key="auto-submit" class="text-center p-6">
        <Loader class="h-6 w-6 text-blue-500 mx-auto" />
      </div>

      <component
        v-else-if="form && formManager && form"
        :key="'form'+form.presentation_style"
        :is="FormComponent"
        :form-manager="formManager"
        @submit="triggerSubmit"
        class="flex flex-col grow"
      >
        <template #password v-if="isPublicFormPage && form.is_password_protected" >
          <div class="w-full">
            <div class="form-group flex flex-wrap w-full max-w-sm mx-auto">
              <div class="relative w-full px-2">
                <text-input :form="passwordForm" name="password" native-type="password" label="Password" :help="t('forms.password_protected')" @keydown.enter.prevent="passwordEntered" />
              </div>
            </div>
            <div class="flex flex-wrap justify-center w-full text-center">
              <open-form-button :form="form" class="my-4 px-8" @click="passwordEntered">
                {{ t('forms.submit') }}
              </open-form-button>
            </div>
          </div>
        </template>
        
        <template
          #alerts
          v-if="isPublicFormPage && (form.is_closed || form.visibility=='closed' || form.max_number_of_submissions_reached)"
        >
          <UAlert
            v-if="form.is_closed || form.visibility=='closed'"
            icon="i-heroicons-lock-closed-20-solid"
            color="warning"
            variant="subtle"
            class="m-2 my-4"
          >
            <template #description>
              <div class="break-words whitespace-break-spaces" v-html="form.closed_text" />
            </template>
          </UAlert>
          <UAlert
            v-else
            icon="i-heroicons-lock-closed-20-solid"
            color="warning"
            variant="subtle"
            class="m-2 my-4"
          >
            <template #description>
              <div class="break-words whitespace-break-spaces" v-html="form.max_submissions_reached_text" />
            </template>
          </UAlert>
        </template>

        <template #cleanings>
          <form-cleanings
            v-if="showFormCleanings"
            :hideable="true"
            class="mb-4 mx-2"
            :form="form"
            :specify-form-owner="true"
            :use-cookie-dismissal="true"
          />
        </template>

        <!-- Submitted view provided to renderer -->
        <template #after-submit>
          <TextBlock
            v-if="form.submitted_text"
            class="form-description text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap"
            :content="form.submitted_text"
            :mentions-allowed="true"
            :form="form"
            :form-data="submittedData"
          />
          <div class="flex w-full gap-2 items-center mt-4">
            <open-form-button
              v-if="form.re_fillable"
              :form="form"
              icon="i-lucide-rotate-ccw"
              @click="restart"
            >
              {{ form.re_fill_button_text || t('forms.buttons.re_fill') }}
            </open-form-button>
            <open-form-button
              v-if="form.editable_submissions && submissionId"
              :form="form"
              @click="editSubmission"
            >
              {{ form.editable_submissions_button_text }}
            </open-form-button>
          </div>
        </template>
      </component>
    </v-transition>

    <template v-if="!isAutoSubmit">
      <FirstSubmissionModal
        :show="showFirstSubmissionModal"
        :form="form"
        @close="showFirstSubmissionModal=false"
      />
    </template>
  </div>
</template>

<script setup>
import { useFormManager } from '~/lib/forms/composables/useFormManager'
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import OpenForm from './OpenForm.vue'
import OpenFormFocused from './OpenFormFocused.vue'
import OpenFormButton from './OpenFormButton.vue'
import FormCleanings from '../../pages/forms/show/FormCleanings.vue'
import VTransition from '~/components/global/transitions/VTransition.vue'
import FirstSubmissionModal from '~/components/open/forms/components/FirstSubmissionModal.vue'
import { useForm } from '~/composables/useForm'
import { useAlert } from '~/composables/useAlert'
import { useI18n } from 'vue-i18n'
import { useIsIframe } from '~/composables/useIsIframe'
import Loader from '~/components/global/Loader.vue'
import { tailwindcssPaletteGenerator } from '~/lib/colors.js'
import { useRouter } from 'vue-router'

const props = defineProps({
  form: { type: Object, required: true },
  mode: {
    type: String,
    default: FormMode.LIVE,
    validator: (value) => Object.values(FormMode).includes(value)
  },
  darkMode: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submitted', 'password-entered', 'restarted'])

const { t, setLocale } = useI18n()
const route = useRoute()
const router = useRouter()
const alert = useAlert()
const workingFormStore = useWorkingFormStore()
const { data: user } = useAuth().user()
const passwordForm = useForm({ password: null })
// Removed unused hidePasswordDisabledMsg (was always false and unused)
const submissionId = ref(route.query.submission_id || null)
const submittedData = ref(null)
const showFirstSubmissionModal = ref(false)

const queryString = route.fullPath.split('?')[1] || ''

// Check for auto_submit parameter during setup
const isAutoSubmit = ref(import.meta.client && window.location.href.includes('auto_submit=true'))


// Create a reactive reference directly from the prop
const darkModeRef = toRef(props, 'darkMode')
// Create a reactive reference for the mode prop
const modeRef = toRef(props, 'mode')

// Provide theme context for components outside OpenForm (password input, TextBlock)
provide('formTheme', computed(() => props.form.theme || 'default'))
provide('formSize', computed(() => props.form.size || 'md'))  
provide('formBorderRadius', computed(() => props.form.border_radius || 'small'))
provide('formPresentationStyle', computed(() => props.form.presentation_style || 'classic'))

let formManager = null
if (props.form) {
  formManager = useFormManager(props.form, props.mode, {
    darkMode: darkModeRef,
    mode: modeRef
  })

  // Await initialization so SSR includes form structure and fields
  await formManager.initialize({
    submissionId: submissionId.value,
    urlParams: new URLSearchParams(queryString),
  })
}

// Watch for changes to the form prop and update formManager
watch(() => props.form, (newForm) => {
  // Only update if the form has changed and formManager is initialized
  if (formManager && newForm) {
    // Update form manager with the new config
    formManager.updateConfig(newForm, {
      submissionId: submissionId.value,
      urlParams: new URLSearchParams(queryString),
    })
  }
})

// Keep the builder's structureService in sync whenever admin preview is on and the adapter changes
watch([
  () => formManager?.strategy?.value?.admin?.showAdminControls,
  () => formManager?.structure?.value
], ([showAdminControls, struct]) => {
  if (workingFormStore && showAdminControls && struct) {
    workingFormStore.setStructureService(struct)
  }
}, { immediate: true })

// Add a watcher to update formManager's darkMode whenever darkModeRef changes
watch(darkModeRef, (newDarkMode) => {
  if (formManager) {
    formManager.setDarkMode(newDarkMode)
  }
})

// If auto_submit is true, trigger submit after component is mounted
onMounted(() => {
  if (isAutoSubmit.value && formManager) {
    // Using nextTick to ensure form is fully rendered and initialized
    nextTick(() => {
      triggerSubmit()
    })
  }
})

const isPublicFormPage = computed(() => {
  return route.name === 'forms-slug'
})

const getFontUrl = computed(() => {
  if(!props.form?.font_family) return null
  const family = props.form.font_family.replace(/ /g, '+')
  return `https://fonts.googleapis.com/css?family=${family}:wght@400,500,700,800,900&display=swap`
})

const isFormOwner = computed(() => {
  const { isAuthenticated } = useIsAuthenticated()
  return isAuthenticated.value && props.form && props.form.creator_id === user.value.id
})

const isProcessing = computed(() => formManager?.state.isProcessing ?? false)
const showFormCleanings = computed(() => formManager?.strategy.value.display.showFormCleanings ?? false)
const showFontLink = computed(() => formManager?.strategy.value.display.showFontLink ?? false)

const formStyle = computed(() => {
  const baseStyle = {
    '--font-family': props.form.font_family,
    'direction': props.form?.layout_rtl ? 'rtl' : 'ltr',
    '--form-color': props.form.color,
    '--color-form': props.form.color
  }

  // Generate color palette variants
  if (props.form.color) {
    const colorPalette = tailwindcssPaletteGenerator(props.form.color).primary
    Object.entries(colorPalette).forEach(([shade, colorValue]) => {
      baseStyle[`--color-form-${shade}`] = colorValue
    })
  }

  return baseStyle
})

const FormComponent = computed(() => {
  return props.form?.presentation_style === 'focused' ? OpenFormFocused : OpenForm
})

// Conditionally add font link to the page head (SSR-friendly)
useHead({
  link: computed(() =>
    (showFontLink.value && props.form?.font_family && getFontUrl.value) ? [
      {
        key: `form-font-${props.form.font_family}`,
        rel: 'stylesheet',
        href: getFontUrl.value,
        crossorigin: 'anonymous',
        referrerpolicy: 'no-referrer'
      }
    ] : []
  )
})

watch(() => props.form?.language, (newLanguage) => {
  if (newLanguage && typeof newLanguage === 'string') {
    setLocale(newLanguage)
  } else {
    setLocale('en')
  }
}, { immediate: true })

onBeforeUnmount(() => {
  setLocale('en')
})

const handleScrollToError = () => {
  if (import.meta.server) return

  nextTick(() => {
      const firstErrorElement = document.querySelector('.form-group [error], .form-group .has-error')
      if (firstErrorElement) {
        const headerOffset = 60 // Offset for fixed headers, adjust as needed
        const elementPosition = firstErrorElement.getBoundingClientRect().top
        const offsetPosition = elementPosition + window.scrollY - headerOffset

        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        })
      } 
  })
}

const triggerSubmit = () => {
  if (!formManager || isProcessing.value) return

  formManager.submit({
    submissionId: submissionId.value
  }).then(result => {
      if (result) {
        submittedData.value = formManager.form.data()
        
        if (result?.submission_id) {
          submissionId.value = result.submission_id
        }

        if (isFormOwner.value && !useIsIframe() && result?.is_first_submission) {
          showFirstSubmissionModal.value = true
        }
        
        emit('submitted', true)

        // Ensure the submitted view starts at the top (parity with page change scroll)
        if (import.meta.client) {
          window.scrollTo({ top: 0, behavior: 'smooth' })
        }
      } else {
        console.warn('Form submission failed via composable, but no error thrown?')
        alert.error(t('forms.submission_error'))
      }
    })
    .catch(error => {
      console.error(error)
      if (error.response && error.response.status === 422 && error.data) {
        alert.formValidationError(error.data)
      } else if (error.message) {
        alert.error(error.message)
      }
      handleScrollToError()
    }).finally(() => {
      isAutoSubmit.value = false
    })
}

const restart = async () => {
  if (!formManager) return
  submittedData.value = null
  submissionId.value = null
  const queryString = route.fullPath.split('?')[1] || ''
  const urlParams = new URLSearchParams(queryString)
  
  // Determine if we should clear the form completely for a fresh start
  const shouldClearUrl = props.form.editable_submissions
  
  if (shouldClearUrl) {
    // Remove submission_id parameter and navigate to clean URL
    urlParams.delete('submission_id')
    const newQueryString = urlParams.toString()
    const newPath = newQueryString ? `${route.path}?${newQueryString}` : route.path
    await router.replace(newPath)
  }
  
  // Single restart call with conditional options
  await formManager.restart({
    urlParams: shouldClearUrl ? new URLSearchParams() : urlParams,
    submissionId: null,
    skipUrlParams: shouldClearUrl
  })
  
  emit('restarted', true)
}

const editSubmission = () => {
  const editUrl = props.form.share_url + '?submission_id=' + submissionId.value
  window.parent.location.href = editUrl
}

const addPasswordError = (msg) => {
  passwordForm.errors.set('password', msg)
}

// Inject password error from parent
const passwordError = inject('passwordError', ref(null))

// Watch for password error from parent and display it
watch(passwordError, (errorMsg) => {
  if (errorMsg) {
    addPasswordError(errorMsg)
  }
}, { immediate: true })

const passwordEntered = () => {
  if (passwordForm.password) {
    emit('password-entered', passwordForm.password)
  } else {
    addPasswordError(t('forms.password_required'))
  }
}

defineExpose({
  restart,
  formManager
})

</script>

<style lang="scss">
.open-complete-form {
  * {
    font-family: var(--font-family) !important;
  }

}
</style>
