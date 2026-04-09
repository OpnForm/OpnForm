<template>
  <div class="flex flex-wrap flex-col flex-grow">
    <div
      key="2"
      class="w-full flex flex-grow flex-col"
    >
      <create-form-base-modal
        :show="showInitialFormModal"
        @form-generated="formGenerated"
        @form-imported="formImported"
        @close="showInitialFormModal = false"
      />

      <VTransition name="fade">
        <FormEditor
          v-if="form"
          ref="editor"
          class="w-full flex flex-grow"
          :error="error"
          @on-save="formInitialHash = null"
        />
      </VTransition>
    </div>
  </div>
</template>

<script setup>
import { watch } from "vue"
import { initForm } from "~/composables/forms/initForm.js"
import FormEditor from "~/components/open/forms/components/FormEditor.vue"
import CreateFormBaseModal from "../../../components/pages/forms/create/CreateFormBaseModal.vue"
import { hash } from "~/lib/utils.js"
import { onBeforeRouteLeave } from "vue-router"

definePageMeta({
  middleware: "auth",
  layout: 'empty'
})

useOpnSeoMeta({
  title: "Create a new Form",
})

onBeforeRouteLeave((to, from, next) => {
  if (isDirty()) {
      if (window.confirm('Changes you made may not be saved. Are you sure want to leave?')) {
        window.onbeforeunload = null
        next()
      } else {
        next(false)
      }
    }
  next()
})

const route = useRoute()
const workingFormStore = useWorkingFormStore()

let template = null
if (route.query.template) {
  const { data, suspense } = useTemplates().detail(route.query.template)
  await suspense()
  template = data.value
}

const { current: workspace } = useCurrentWorkspace()
const { content: form } = storeToRefs(workingFormStore)

// Pre-load forms list for the current workspace (replaces formStore.loadAll)
const workspaceId = computed(() => workspace.value?.id)
useFormsList(workspaceId, {
  enabled: computed(() => !!workspaceId.value)
})

// State
const loading = ref(false)
const error = ref("")
const showInitialFormModal = ref(false)
const formInitialHash = ref(null)

watch(
  () => workspace,
  () => {
    if (workspace) {
      form.workspace_id = workspace.value.id
    }
  },
)

onMounted(() => {
  if (import.meta.client) {
    window.onbeforeunload = () => {
      if (isDirty()) {
        return false
      }
    }
  }

  const { hasFeature } = usePlanFeatures()
  form.value = initForm({ workspace_id: workspace.value?.id, no_branding: hasFeature('branding.removal') }, true)
  formInitialHash.value = hash(JSON.stringify(form.value.data()))

  // Check for imported form data passed via localStorage (e.g. comparison pages)
  const pendingImport = localStorage.getItem('importedFormData')
  if (pendingImport) {
    localStorage.removeItem('importedFormData')
    try {
      const importedData = JSON.parse(pendingImport)
      form.value = useForm({ ...form.value.data(), ...importedData })
    } catch { /* ignore malformed data */ }
  } else if (template && template.structure) {
    form.value = useForm({ ...form.value.data(), ...template.structure })
  } else {
    showInitialFormModal.value = true
  }
})

// Methods
const formGenerated = (newForm) => {
  form.value = useForm({ ...form.value.data(), ...newForm })
}

const formImported = (importedForm) => {
  form.value = useForm({ ...form.value.data(), ...importedForm })
}

const isDirty = () => {
  return (
    !loading.value &&
    formInitialHash.value &&
    formInitialHash.value !== hash(JSON.stringify(form.value.data()))
  )
}
</script>
