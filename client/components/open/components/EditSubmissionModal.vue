<template>
  <UModal
    v-model:open="isModalOpen"
    :ui="{ content: 'sm:max-w-2xl', body: 'p-0!' }"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold">
          Edit Submission
        </h2>
        <UButton
          v-if="props.form?.editable_submissions ?? false"
          variant="outline"
          :color="copySuccess ? 'success' : 'primary'"
          :icon="copySuccess ? 'i-heroicons-check' : 'i-heroicons-clipboard-document'"
          @click.prevent="copyToClipboard"
        >
          <span class="hidden md:inline">{{ copySuccess ? 'Copied!' : 'Copy Public Link' }}</span>
        </UButton>
      </div>
    </template>
    <template #body>
      <OpenForm
        v-if="form"
        :form-manager="formManager"
        @submit="updateForm"
      >
        <template #submit-btn="{ isProcessing }">
          <UButton
            class="mt-2"
            :loading="updateSubmissionMutation.isPending.value || isProcessing"
            @click.prevent="updateForm"
            label="Update Submission"
          />
        </template>
      </OpenForm>
    </template>
  </UModal>
</template>

<script setup>
import OpenForm from "../forms/OpenForm.vue"
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import { useFormManager } from '~/lib/forms/composables/useFormManager'
import { useFormSubmissions } from "~/composables/query/forms/useFormSubmissions"

const props = defineProps({
  show: { type: Boolean, required: true },
  form: { type: Object, required: true },
  submission: { type: Object },
})

// Modal state
const isModalOpen = computed({
  get() {
    return props.show
  },
  set(value) {
    if (!value) {
      emit("close")
    }
  }
})

// Set up form manager with proper mode
let formManager = null
const setupFormManager = () => {
  if (!props.form) return null
  
  formManager = useFormManager(props.form, FormMode.EDIT)
  
  return formManager
}

// Initialize form manager
formManager = setupFormManager()

watch(() => props.show, (newShow) => {
  if (newShow) {
    nextTick(() => {
      formManager.initialize({
        skipPendingSubmission: true,
        skipUrlParams: true,
        defaultData: props.submission
      })
    })
  }
})

// Use form submissions composable for update
const { updateSubmission } = useFormSubmissions()
const updateSubmissionMutation = updateSubmission()

const emit = defineEmits(["close"])
const alert = useAlert()

const updateForm = () => {
  updateSubmissionMutation.mutateAsync({
    formId: props.form.id,
    submissionId: props.submission.id,
    data: formManager.form.data()
  }).then((res) => {
    alert.success(res.message)
    emit("close")
  }).catch((error) => {
    console.error(error)
    if (error?.data) {
      alert.formValidationError(error.data)
    }
  })
}

const copySuccess = ref(false)
const { copy } = useClipboard()
const copyToClipboard = () => {
  if (import.meta.server) return

  const url = props.form.share_url + "?submission_id=" + props.submission.submission_id
  copy(url)
  
  // Show success state
  copySuccess.value = true
  
  // Reset after 2 seconds
  setTimeout(() => {
    copySuccess.value = false
  }, 2000)
}
</script>
