<template>
  <div class="space-y-4">
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">Workspace Information</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Update your workspace information.
        </p>
      </div>
    </div>

    <VForm @submit.prevent="updateProfile" size="sm">
   
        <div class="max-w-sm">
          <TextInput
            :disabled="workspace.is_readonly"
            :form="workspaceForm"
            name="name"
            label="Workspace Name"
            placeholder="My Workspace"
            :required="true"
          />
          <TextInput
            :disabled="workspace.is_readonly"
            :form="workspaceForm"
            name="emoji"
            label="Emoji (optional)"
            placeholder="Emoji"
            help="Choose an emoji to represent your workspace"
          />
        </div>

        <div class="mt-4">
          <UButton
            :disabled="workspace.is_readonly"
            type="submit"
            :loading="workspaceForm.busy"
            color="primary"
          >
            Save Changes
          </UButton>
        </div>
    </VForm>

    <div class="pt-8 border-t border-neutral-200">
      <div 
        v-if="workspace.is_admin" 
        class="space-y-2"
      >
        <h4 class="text-red-800 font-medium">Delete Workspace</h4>
        <p class="text-neutral-500 text-sm">
          This will permanently delete your entire workspace. All forms created in this workspace will be removed. This cannot be undone.
        </p>
        <UButton
          color="error"
          :loading="removeMutation.isPending.value"
          @click="confirmDeleteWorkspace"
        >
          Delete workspace
        </UButton>
      </div>

      <div 
        v-else
        class="space-y-2"
      >
        <h4 class="text-neutral-900 font-medium">Leave Workspace</h4>
        <p class="text-neutral-500 text-sm">
          This will remove you from the workspace. You will lose access to all forms in this workspace.
        </p>
        <UButton
          color="error"
          :loading="leaveMutation.isPending.value"
          @click="leaveWorkSpace"
        >
          Leave workspace
        </UButton>
      </div>
    </div>
  </div>
</template>

<script setup>
const { update, remove, leave } = useWorkspaces()

const alert = useAlert()
const { closeWorkspaceSettings } = useAppModals()
const router = useRouter()

const { current: workspace } = useCurrentWorkspace()

const updateMutation = update(computed(() => workspace.value?.id))
const removeMutation = remove()
const leaveMutation = leave()

// Workspace form
const workspaceForm = useForm({
  name: '',
  emoji: ''
})

// Update profile
const updateProfile = () => {
  workspaceForm.mutate(updateMutation).then(() => {
    useAlert().success('Workspace information successfully updated!')
  }).catch((error) => {
      console.error('Error updating workspace:', error)
  })
}

// Delete workspace confirmation
const confirmDeleteWorkspace = () => {
  alert.confirm(
    'Do you really want to delete your workspace?',
    deleteWorkspace
  )
}

// Delete workspace
const deleteWorkspace = () => {
  removeMutation.mutateAsync(workspace.value.id).then((data) => {
      alert.success(data.message)
      closeWorkspaceSettings()
      nextTick(() => {
        router.push({ name: "home", query: {} })
      })
  }).catch((error) => {
      alert.error(error.data?.message || 'Error deleting workspace')
    })
}

// Leave workspace
const leaveWorkSpace = () => {
  alert.confirm(
    "Do you really want to leave this workspace? You will lose access to all forms in this workspace.",
    () => {
      leaveMutation.mutateAsync(workspace.value.id).then(() => {
        alert.success("You have left the workspace.")
        closeWorkspaceSettings()
        nextTick(() => {
          router.push({ name: "home", query: {} })
        })
      }).catch((error) => {
        console.error('Error leaving workspace:', error)
        alert.error("There was an error leaving the workspace.")
      })
    },
  )
}


// Watch for user changes
watch(workspace, (newWorkspace) => {
  if (newWorkspace) {
    workspaceForm.fill({
      name: newWorkspace.name || '',
      emoji: newWorkspace.icon || ''
    })
  }
}, { immediate: true })
</script> 