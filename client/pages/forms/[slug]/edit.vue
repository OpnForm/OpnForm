<template>
  <div class="w-full flex flex-col flex-grow">
    <VTransition name="fade">
    <FormEditor
      v-if="!error"
      ref="editor"
      :is-edit="true"
      :has-unsaved-changes="hasUnsavedChanges"
      @on-save="formInitialHash = null"
      :loading="formsLoading"
    />
    <UAlert
      v-else-if="error && !formsLoading"
      icon="i-heroicons-exclamation-triangle"
      color="error"
      variant="soft"
      class="max-w-xl mx-auto mt-4"
      title="Error"
      :description="errorMessage"
    >
      <template #actions>
        <UButton
          color="neutral"
          variant="outline"
          :to="{ name: 'home' }"
          icon="i-heroicons-arrow-left"
        >
          Back to dashboard
        </UButton>
      </template>
    </UAlert>
    </VTransition>
  </div>
</template>

<script setup>

import FormEditor from "~/components/open/forms/components/FormEditor.vue"
import { applyEditorFieldDefaults, setFormDefaults } from "~/composables/forms/initForm.js"
import { hash } from "~/lib/utils.js"

// Composables
const route = useRoute()
const workingFormStore = useWorkingFormStore()
const { detail: formDetail } = useForms()

const slug = route.params.slug

// Get form by slug using TanStack Query with private authenticated endpoint
const { data: form, isLoading: formsLoading, error } = formDetail(slug, {
  usePrivate: true,
  enabled: import.meta.client,
})

const errorMessage = computed(() => {
  if (error.value?.response?.status === 401) {
    return "You are not authorized to access this form."
  } else if (error.value?.response?.status === 404) {
    return "Form not found."
  }
  if (error.value?.response?.data?.message) {
    return error.value.response.data.message
  }
  return "Error loading form"
})

const updatedForm = storeToRefs(workingFormStore).content
const formInitialHash = ref(null)
const hasUnsavedChanges = computed(() => Boolean(isDirty()))

function isDirty() {
  try {
    return (
      formInitialHash.value &&
      updatedForm.value &&
      formInitialHash.value !==
        hash(JSON.stringify(updatedForm?.value?.data() ?? null))
    )
  } catch {
    return false
  }
}

function initUpdatedForm() {
  if (!form.value) {
    return
  }

  const formData = setFormDefaults(form.value)
  formData.properties = applyEditorFieldDefaults(formData.properties)
  updatedForm.value = useForm(formData)
  if (!updatedForm.value) {
    return
  }

  nextTick(() => {
    formInitialHash.value = hash(JSON.stringify(updatedForm?.value?.data() ?? null))
  })
}

// Update working form store when form changes
watch(
  () => form.value,
  (newForm) => {
    workingFormStore.reset()
    if (newForm) {
      workingFormStore.set(newForm)
      initUpdatedForm()
    }
  },
  { immediate: true }
)

onBeforeRouteLeave((to, from, next) => {
  if (hasUnsavedChanges.value && !window.confirm('You have unsaved changes. Leave without saving?')) {
    return next(false)
  }
  next()
})

function warnBeforeUnload(event) {
  if (hasUnsavedChanges.value) {
    event.preventDefault()
    event.returnValue = ''
  }
}

onMounted(() => {
  window.addEventListener('beforeunload', warnBeforeUnload)
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', warnBeforeUnload)
})

useOpnSeoMeta({
  title: "Edit " + (form.value ? form.value.title : "Your Form"),
})
definePageMeta({
  middleware: ["auth", "readonly-block"],
  layout: 'empty'
})
</script>
