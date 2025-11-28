<template>
  <VTransition name="fade">
    <div
      v-if="form"
      id="form-editor"
      class="relative flex w-full flex-col grow max-h-screen"
      key="form"
    >
      <!-- Loading overlay -->
      <div
        v-if="form.busy || loading"
        class="absolute inset-0 bg-white bg-opacity-70 z-50 flex items-center justify-center"
      >
        <loader class="h-6 w-6 text-blue-500" />
      </div>
      <div
        class="border-b bg-white md:hidden fixed inset-0 w-full z-50 flex flex-col items-center justify-center"
      >
        <Icon
          name="heroicons:exclamation-circle"
          class="w-10 h-10 text-blue-800"
        />
        <div class="p-5 text-blue-800 text-center">
          OpnForm is not optimized for mobile devices. Please open this page on a device with a larger screen.
        </div>
        <div>
          <UButton
            color="neutral"
            variant="outline"
            size="lg"
            class="w-full"
            :to="{ name: 'home' }"
          >
            Back to dashboard
          </UButton>
        </div>
      </div>

      <FormEditorNavbar
        :back-button="backButton"
        :update-form-loading="form.busy"
        :save-button-class="saveButtonClass"
        @go-back="goBack"
        @save-form="saveForm"
      >
        <template #before-save>
          <slot name="before-save" />
        </template>
      </FormEditorNavbar>

      <FormEditorErrorHandler>
        <div class="w-full flex grow overflow-y-scroll relative bg-white">
          <div 
            ref="elementRef"
            class="relative shrink-0 overflow-y-scroll border-r"
            :class="isResizable ? '' : 'w-full md:w-1/2 md:max-w-xs lg:w-2/5'"
            :style="isResizable ? dynamicStyles : {}"
          >
            <ResizeHandle
              :show="isResizable"
              direction="left"
              @start-resize="startResize"
              class="z-20"
            />
            
            <VForm
              size="sm"
              @submit.prevent=""
            >
              <div
                v-show="activeTab === 'build'"
              >
                <FormFieldsEditor />
              </div>
              <div
                v-show="activeTab === 'design'"
              >
                <FormCustomization />
              </div>
            </VForm>
          </div>

          <FormEditorPreview />

          <FormEditorSidebar />
        </div>
      </FormEditorErrorHandler>

      <!-- Form Error Modal -->
      <FormErrorModal
        :show="showFormErrorModal"
        :validation-error-response="validationErrorResponse"
        @close="showFormErrorModal = false"
      />

      <!-- Logic Confirmation Modal -->
      <LogicConfirmationModal
        :is-visible="showLogicConfirmationModal"
        :errors="logicErrors"
        @cancel="handleLogicConfirmationCancel"
        @confirm="handleLogicConfirmationConfirm"
      />
    </div>
    <FormEditorSkeleton
      v-else
      key="skeleton"
      :back-button="backButton"
      @go-back="goBack"
    />
  </VTransition>
</template>

<script setup>
import FormEditorNavbar from './FormEditorNavbar.vue'
import FormEditorSkeleton from './FormEditorSkeleton.vue'
import FormEditorSidebar from "./form-components/FormEditorSidebar.vue"
import FormErrorModal from "./form-components/FormErrorModal.vue"
import FormFieldsEditor from './FormFieldsEditor.vue'
import FormCustomization from "./form-components/FormCustomization.vue"
import FormEditorPreview from "./form-components/FormEditorPreview.vue"
import { useFormLogic } from "~/composables/forms/useFormLogic.js"
import { captureException } from "@sentry/core"
import FormEditorErrorHandler from '~/components/open/forms/components/FormEditorErrorHandler.vue'
import { setFormDefaults } from '~/composables/forms/initForm.js'
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import LogicConfirmationModal from '~/components/forms/heavy/LogicConfirmationModal.vue'
import { formsApi } from "~/api"
import { useResizable } from '~/composables/components/useResizable'
import ResizeHandle from '~/components/global/ResizeHandle.vue'

// Define props
const props = defineProps({
  isEdit: {
    required: false,
    type: Boolean,
    default: false,
  },
  isGuest: {
    required: false,
    type: Boolean,
    default: false,
  },
  backButton: {
    required: false,
    type: Boolean,
    default: true,
  },
  saveButtonClass: {
    required: false,
    type: String,
    default: "",
  },
  loading: {
    required: false,
    type: Boolean,
    default: false,
  }
})

// Define emits
const emit = defineEmits(['mounted', 'on-save', 'openRegister', 'go-back', 'save-form'])

// Reactive data
const showFormErrorModal = ref(false)
const showLogicConfirmationModal = ref(false)
const validationErrorResponse = ref(null)
const createdFormSlug = ref(null)
const logicErrors = ref([])

// Sidebar resizing using composable
const { 
  elementRef, 
  isResizable, 
  dynamicStyles, 
  startResize
} = useResizable({
  storageKey: 'formEditorSidebarWidth',
  defaultWidth: 315,
  direction: 'left',
  maxWidth: () => Math.min(600, window.innerWidth * 0.6)
})

// Check if the editor is visible on smaller screens then send an email
const breakpoints = useBreakpoints(breakpointsTailwind)
const isVisible = ref(breakpoints.smaller("md"))
watch(isVisible, (newValue) => {
  if (newValue && form?.value && form?.value?.id) {
    formsApi.mobileEditorEmail(form.value.id)
  }
})

// Composables
const { content: form } = storeToRefs(useWorkingFormStore())
const { current: workspace } = useCurrentWorkspace()

// Initialize TanStack Query mutations for forms
const { create: createFormMutationFactory, update: updateFormMutationFactory } = useForms()
const createMutation = createFormMutationFactory()

// Create update mutation with reactive form ID
const formId = computed(() => form.value?.id)
const updateMutation = updateFormMutationFactory(formId)

const workingFormStore = useWorkingFormStore()
const crisp = useCrisp()
const amplitude = useAmplitude()

// Keyboard shortcut to open add field sidebar
defineShortcuts({
  meta_b: {
    handler: () => {
      workingFormStore.openAddFieldSidebar()
    }
  },
  ctrl_b: {
    handler: () => {
      workingFormStore.openAddFieldSidebar()
    }
  }
})

// Computed properties
const activeTab = computed(() => workingFormStore.activeTab)

// Methods
const goBack = () => {
  if (props.isEdit) {
    useRouter().push({ name: 'forms-slug-show-submissions', params: {slug: form.value.slug} })
  } else {
    useRouter().push({ name: 'home' })
  }
}

const displayFormModificationAlert = (responseData) => {
  const alert = useAlert()
  if (
    responseData.form &&
    responseData.form.cleanings &&
    Object.keys(responseData.form.cleanings).length > 0
  ) {
    alert.warning(responseData.message, 10000, { form: responseData.form })
  } else if (responseData.message) {
    alert.success(responseData.message, 10000, { form: responseData.form })
  }
}

const showValidationErrors = () => {
  showFormErrorModal.value = true
}

const saveForm = () => {
  // Apply defaults to the form
  const defaultedData = setFormDefaults(form.value.data())
  form.value.fill(defaultedData)

  // Check for logic errors
  const { getLogicErrors } = useFormLogic()
  logicErrors.value = getLogicErrors(form.value.properties)
  
  if (logicErrors.value.length > 0) {
    showLogicConfirmationModal.value = true
    return
  }
  
  proceedWithSave()
}

const proceedWithSave = () => {
  if (logicErrors.value.length > 0) {
    // Clean invalid logic before saving using the comprehensive validator
    const { validatePropertiesLogic } = useFormLogic()
    form.value.properties = validatePropertiesLogic(form.value.properties)
  }

  if (props.isGuest) {
    saveFormGuest()
  } else if (props.isEdit) {
    saveFormEdit()
  } else {
    saveFormCreate()
  }
}

const handleLogicConfirmationCancel = () => {
  showLogicConfirmationModal.value = false
}

const handleLogicConfirmationConfirm = () => {
  showLogicConfirmationModal.value = false
  proceedWithSave()
}

const saveFormEdit = () => {
  if (form.value.busy || !form.value.id) return

  validationErrorResponse.value = null

  form.value.mutate(updateMutation).then((response) => {
    const updatedForm = response.form
    emit("on-save")

    // Navigate to share page
    useRouter().push({
      name: "forms-slug-show-share",
      params: { slug: updatedForm.slug },
    })

    try{
    // Analytics / alerts
    amplitude.logEvent("form_saved", {
      form_id: updatedForm.id,
      form_slug: updatedForm.slug,
    })
    displayFormModificationAlert(response)
    } catch (error) {
      console.error("Analytics error", error)
    }
  }).catch((error) => {
    console.error("Error saving form", error)
    
    // Check for 401 errors - these are handled by the HTTP interceptor
    const errorStatus = error?.response?.status || error?.status
    if (errorStatus === 401) {
      // Token expiry is handled by the HTTP interceptor (opens QuickRegister modal)
      // Don't show an additional error message
      return
    }
    
    if (errorStatus === 422) {
      validationErrorResponse.value = error.data
      showValidationErrors()
    } else {
      console.error(error)
      useAlert().error(
        "An error occurred while saving the form, please try again.",
      )
      captureException(error)
    }
  })
}

const saveFormCreate = () => {
  if (form.value.busy) return
  // Attach workspace ID before sending
  form.value.workspace_id = workspace.value.id
  validationErrorResponse.value = null

  form.value.mutate(createMutation).then((response) => {
    const newForm = response.form
    emit("on-save")
    createdFormSlug.value = newForm.slug

    try{
      // Analytics / alerts
      amplitude.logEvent("form_created", {
        form_id: newForm.id,
        form_slug: newForm.slug,
      })
      crisp.pushEvent("form_created", {
        form_id: newForm.id,
        form_slug: newForm.slug,
      })
    } catch (error) {
      console.error("Analytics error", error)
    }
    displayFormModificationAlert(response)

    useRouter().push({
      name: "forms-slug-show-share",
      params: {
        slug: createdFormSlug.value,
        new_form: newForm.users_first_form ?? false,
      },
    })
  }).catch((error) => {
    console.error("Error saving form", error)
    
    // Check for 401 errors - these are handled by the HTTP interceptor
    const errorStatus = error?.response?.status || error?.status
    if (errorStatus === 401) {
      // Token expiry is handled by the HTTP interceptor (opens QuickRegister modal)
      // Don't show an additional error message
      return
    }
    
    if (errorStatus === 422) {
      validationErrorResponse.value = error.data
      showValidationErrors()
    } else {
      useAlert().error(
        "An error occurred while saving the form, please try again.",
      )
      captureException(error)
    }
  })
}

const saveFormGuest = () => {
  emit("openRegister")
}

defineExpose({
  saveFormCreate,
  showValidationErrors
})

// Lifecycle hooks
onMounted(() => {
  emit("mounted")
  workingFormStore.activeTab = 'build'
  amplitude.logEvent('form_editor_viewed')
  
  if (!props.isEdit) {
    nextTick(() => {
      workingFormStore.openAddFieldSidebar()
    })
  }
})
</script>

<style lang="scss">
.v-step {
  color: white;

  .v-step__header,
  .v-step__content {
    color: white;

    div {
      color: white;
    }
  }
}
</style>
