<template>
  <div class="flex flex-wrap flex-col flex-grow">
    <create-form-base-modal
      :show="showInitialFormModal"
      @form-generated="formGenerated"
      @close="showInitialFormModal = false"
    />
    <VTransition name="fade">
      <FormEditor
        v-if="stateReady"
        ref="editor"
        class="w-full flex flex-grow"
        :error="error"
        :is-guest="isGuest"
        :loading="workspacesLoading"
        @open-register="appStore.quickRegisterModal = true"
      />
    </VTransition>
  </div>
</template>

<script setup>
import FormEditor from "~/components/open/forms/components/FormEditor.vue"
import CreateFormBaseModal from "../../../components/pages/forms/create/CreateFormBaseModal.vue"
import { initForm } from "~/composables/forms/initForm.js"
import { useQueryClient } from "@tanstack/vue-query"

import { WindowMessageTypes } from "~/composables/useWindowMessage"

const appStore = useAppStore()
const workingFormStore = useWorkingFormStore()
const route = useRoute()
const queryClient = useQueryClient()

let template = null
if (route.query.template) {
  const { data, suspense } = useTemplates().detail(route.query.template)
  await suspense()
  template = data.value
}

// Use workspaces query composable for invalidation functionality
const { invalidateAll } = useWorkspaces()

// Store values
const workspacesLoading = computed(() => {
  // For guest mode, we'll manage loading state manually
  return !stateReady.value
})
const form = storeToRefs(workingFormStore).content

useOpnSeoMeta({
  title: "Create a new Form for free",
})
definePageMeta({
  middleware: ["guest", "self-hosted"],
  layout: 'empty'
})

// Data
const stateReady = ref(false)
const error = ref("")
const isGuest = ref(true)
const showInitialFormModal = ref(false)

// Component ref
const editor = ref(null)

onMounted(() => {
  // Set guest workspace data in query cache instead of store
  const guestWorkspace = {
    id: null,
    name: "Guest Workspace",
    is_enterprise: false,
    is_pro: false,
  }
  
  // Manually set the workspace data in query cache
  queryClient.setQueryData(["workspaces", "list"], [guestWorkspace])

  form.value = initForm({}, true)
  if (template && template.structure) {
    form.value = useForm({ ...form.value.data(), ...template.structure })
  } else {
    // No template loaded, ask how to start
    showInitialFormModal.value = true
  }
  stateReady.value = true

  // Set up window message listener for after-login
  const afterLoginMessage = useWindowMessage(WindowMessageTypes.AFTER_LOGIN)
  afterLoginMessage.listen(() => {
    afterLogin()
  }, { useMessageChannel: false })
})

const afterLogin = () => {
  isGuest.value = false
  invalidateAll() // Refetch all workspace queries
  setTimeout(() => {
    if (editor) {
      editor.value.saveFormCreate()
    }
  }, 500)
}

const formGenerated = (newForm) => {
  form.value = useForm({ ...form.value.data(), ...newForm })
}
</script>
