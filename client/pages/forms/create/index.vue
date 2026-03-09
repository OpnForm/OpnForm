<template>
  <div class="flex flex-wrap flex-col flex-grow">
    <div
      key="2"
      class="w-full flex flex-grow flex-col"
    >
      <create-form-base-modal
        :show="showInitialFormModal"
        @form-generated="formGenerated"
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
const { fetchDraft } = useChatGptDrafts()
const isUuid = (value) => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value)

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

onMounted(async () => {
  if (import.meta.client) {
    window.onbeforeunload = () => {
      if (isDirty()) {
        return false
      }
    }
  }

  form.value = initForm({ workspace_id: workspace.value?.id, no_branding: workspace.value?.is_pro }, true)

  if (template && template.structure) {
    form.value = useForm({ ...form.value.data(), ...template.structure })
    showInitialFormModal.value = false
    formInitialHash.value = hash(JSON.stringify(form.value.data()))
    return
  }

  const gptChatId = typeof route.query.gpt_chat_id === 'string' ? route.query.gpt_chat_id : null
  const canImportChatGptDraft = !!gptChatId && isUuid(gptChatId) && !useFeatureFlag('self_hosted', true) && !!useFeatureFlag('chatgpt_app.enabled', false)

  if (canImportChatGptDraft) {
    try {
      const response = await fetchDraft(gptChatId)
      const importedDraft = response?.draft?.form_state

      if (importedDraft && typeof importedDraft === 'object') {
        form.value = useForm({ ...form.value.data(), ...importedDraft })
        showInitialFormModal.value = false
        formInitialHash.value = hash(JSON.stringify(form.value.data()))
        return
      }
    } catch (error) {
      console.warn('Failed to import ChatGPT draft in authenticated editor, falling back to default create flow.', error)
    }
  }

  // No template/draft loaded, ask how to start
  showInitialFormModal.value = true
  formInitialHash.value = hash(JSON.stringify(form.value.data()))
})

// Methods
const formGenerated = (newForm) => {
  form.value = useForm({ ...form.value.data(), ...newForm })
}

const isDirty = () => {
  return (
    !loading.value &&
    formInitialHash.value &&
    formInitialHash.value !== hash(JSON.stringify(form.value.data()))
  )
}
</script>
