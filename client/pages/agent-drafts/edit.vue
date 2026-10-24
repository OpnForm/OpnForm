<template>
  <div class="flex min-h-screen flex-col bg-white">
    <div v-if="loading" class="flex grow items-center justify-center">
      <div class="text-center">
        <Loader class="mx-auto h-7 w-7 text-blue-500" />
        <p class="mt-3 text-sm text-neutral-500">Opening your private draft…</p>
      </div>
    </div>

    <div v-else-if="fatalError" class="mx-auto flex max-w-xl grow items-center p-6">
      <UAlert color="error" title="Unable to open this draft" :description="fatalError" />
    </div>

    <FormEditor
      v-else
      class="w-full grow"
      :loading="saving"
      :save-handler="syncDraft"
      :back-button="false"
    >
      <template #before-save>
        <div class="mr-2 hidden items-center gap-2 sm:flex">
          <span class="text-xs text-neutral-500">{{ syncLabel }}</span>
          <UButton
            size="sm"
            color="neutral"
            variant="outline"
            :loading="claiming"
            @click.stop="prepareClaim"
          >
            Save to my account
          </UButton>
        </div>
      </template>
    </FormEditor>

    <UModal v-model:open="workspaceModalOpen" :ui="{ content: 'sm:max-w-md' }">
      <template #header>
        <div>
          <h2 class="font-semibold">Choose a workspace</h2>
          <p class="mt-1 text-sm font-normal text-neutral-500">The form will be saved as an unpublished draft.</p>
        </div>
      </template>
      <template #body>
        <SelectInput
          v-model="selectedWorkspaceId"
          name="workspace"
          label="Workspace"
          :options="workspaceOptions"
          :required="true"
        />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="outline" @click="workspaceModalOpen = false">Cancel</UButton>
          <UButton :loading="claiming" @click="claimDraft">Save draft</UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup>
import FormEditor from '~/components/open/forms/components/FormEditor.vue'
import { workspaceApi } from '~/api'
import { WindowMessageTypes } from '~/composables/useWindowMessage'
import { createAgentDraftAutosave } from '~/lib/forms/agent-draft-autosave'
import { getAgentDraftSessionRequest } from '~/lib/forms/agent-draft-session'
import { isAgentDraftVersionConflict } from '~/lib/forms/agent-draft-errors.js'

definePageMeta({ layout: 'empty' })
useOpnSeoMeta({ title: 'Edit private form draft' })

const appStore = useAppStore()
const workingFormStore = useWorkingFormStore()
const { isAuthenticated } = useIsAuthenticated()
provide('disableCustomCodeExecution', true)

const loading = ref(true)
const saving = ref(false)
const claiming = ref(false)
const fatalError = ref(null)
const version = ref(null)
const lastSavedFingerprint = ref(null)
const syncState = ref('saved')
const workspaces = ref([])
const selectedWorkspaceId = ref(null)
const workspaceModalOpen = ref(false)
const claimAfterLogin = ref(false)
const hydratingEditor = ref(true)
let syncPromise = null

const syncLabel = computed(() => ({
  saving: 'Saving draft…',
  error: 'Draft not saved',
  saved: `Draft saved · v${version.value || '?'}`,
}[syncState.value]))
const workspaceOptions = computed(() => workspaces.value.map(workspace => ({
  name: workspace.name,
  value: workspace.id,
})))

const cleanDefinition = (data) => {
  const definition = JSON.parse(JSON.stringify(data))
  delete definition.id
  delete definition.slug
  delete definition.workspace_id
  delete definition.workspace
  delete definition.bypass_success_page
  delete definition.share_url
  delete definition.created_at
  delete definition.updated_at
  return definition
}

const applyDraft = (draft) => {
  version.value = draft.version
  const definition = cleanDefinition(draft.definition)
  lastSavedFingerprint.value = JSON.stringify(definition)
  workingFormStore.set(useForm(definition))
  syncState.value = 'saved'
}

const refreshDraft = () => $fetch('/api/agent-drafts/current').then((response) => {
  applyDraft(response.draft)
  return response.draft
})

const performSync = (rawData, keepalive = false) => {
  const definition = cleanDefinition(rawData || workingFormStore.content?.data())
  const fingerprint = JSON.stringify(definition)
  if (!definition || fingerprint === lastSavedFingerprint.value) {
    return Promise.resolve()
  }
  if (syncPromise) {
    return syncPromise.then(() => performSync(definition, keepalive))
  }

  saving.value = true
  syncState.value = 'saving'
  syncPromise = $fetch('/api/agent-drafts/current', {
    method: 'PUT',
    body: { expected_version: version.value, definition },
    keepalive,
  }).then((response) => {
    version.value = response.draft.version
    lastSavedFingerprint.value = JSON.stringify(cleanDefinition(response.draft.definition))
    syncState.value = 'saved'
  }).catch((exception) => {
    syncState.value = 'error'
    if (isAgentDraftVersionConflict(exception)) {
      return refreshDraft().then(() => {
        useAlert().warning('The draft changed in the conversation. The editor was refreshed with the latest version.')
      })
    }
    useAlert().error(exception?.data?.message || 'Unable to save this draft right now.')
    throw exception
  }).finally(() => {
    saving.value = false
    syncPromise = null
  })

  return syncPromise
}

const syncDraft = (data) => performSync(data).then(() => {
  useAlert().success('Draft saved.')
})

const draftAutosave = createAgentDraftAutosave(({ keepalive }) => performSync(undefined, keepalive))

const scheduleSync = () => {
  if (loading.value || claiming.value || hydratingEditor.value) return
  draftAutosave.schedule()
}

const flushPendingSync = () => draftAutosave.flush().catch(() => {})

watch(() => workingFormStore.content?.data?.(), scheduleSync, { deep: true })

const prepareClaim = () => {
  if (!isAuthenticated.value) {
    claimAfterLogin.value = true
    appStore.quickRegisterModal = true
    return
  }

  claiming.value = true
  performSync().then(() => workspaceApi.list()).then((availableWorkspaces) => {
    workspaces.value = availableWorkspaces || []
    if (workspaces.value.length === 1) {
      selectedWorkspaceId.value = workspaces.value[0].id
      return claimDraft()
    }
    if (workspaces.value.length === 0) {
      throw new Error('Your account does not have a workspace yet.')
    }
    selectedWorkspaceId.value = null
    claiming.value = false
    workspaceModalOpen.value = true
  }).catch((exception) => {
    useAlert().error(exception?.data?.message || exception.message || 'Unable to prepare this draft for your account.')
  }).finally(() => {
    if (!workspaceModalOpen.value) claiming.value = false
  })
}

const claimDraft = () => {
  if (!selectedWorkspaceId.value) {
    useAlert().error('Please select a workspace.')
    return Promise.resolve()
  }

  claiming.value = true
  return performSync().then(() => $fetch('/api/agent-drafts/claim', {
    method: 'POST',
    body: {
      expected_version: version.value,
      workspace_id: selectedWorkspaceId.value,
    },
  })).then((response) => {
    workspaceModalOpen.value = false
    if (response.cleanings && Object.keys(response.cleanings).length > 0) {
      useAlert().warning('The form was saved. Some premium features will stay disabled when the form is shared.', 10000, { form: response.form })
    } else {
      useAlert().success('Form saved to your account as an unpublished draft.')
    }
    window.location.assign(response.editor_url)
  }).catch((exception) => {
    useAlert().error(exception?.data?.message || 'Unable to save this form to your account.')
  }).finally(() => {
    claiming.value = false
  })
}

onMounted(() => {
  window.addEventListener('pagehide', flushPendingSync)

  const sessionRequest = getAgentDraftSessionRequest(window.location.hash)
  if (sessionRequest.consumesHandoff) {
    window.history.replaceState({}, '', '/agent-drafts/edit')
  }
  $fetch(sessionRequest.url, sessionRequest.options).then((response) => {
    applyDraft(response.draft)
  }).catch((exception) => {
    fatalError.value = exception?.data?.message || 'This editor link is invalid or expired. Ask the agent for a fresh link.'
  }).finally(() => {
    loading.value = false
    if (!fatalError.value) {
      nextTick().then(() => {
        lastSavedFingerprint.value = JSON.stringify(cleanDefinition(workingFormStore.content?.data()))
        hydratingEditor.value = false
      })
    }
  })

  useWindowMessage(WindowMessageTypes.AFTER_LOGIN).listen(() => {
    if (claimAfterLogin.value) {
      claimAfterLogin.value = false
      prepareClaim()
    }
  }, { useMessageChannel: false })
})

onBeforeUnmount(() => {
  window.removeEventListener('pagehide', flushPendingSync)
  flushPendingSync()
})
</script>
