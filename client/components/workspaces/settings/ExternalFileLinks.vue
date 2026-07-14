<template>
  <div class="space-y-4">
    <div>
      <h3 class="text-lg font-medium text-neutral-900">External file links</h3>
      <p class="mt-1 text-sm text-neutral-500">
        Control how long uploaded-file links shared outside your workspace remain available.
      </p>
    </div>

    <UAlert
      icon="i-heroicons-shield-check"
      color="warning"
      variant="subtle"
      title="Anyone with a link can access the file until it expires"
      description="This policy applies to links in email notifications, CSV exports, webhooks, chat integrations and Google Sheets. It only affects newly generated links."
    />

    <VForm size="sm">
      <form @submit.prevent="saveChanges">
        <div class="max-w-xl">
          <OptionSelectorInput
            :form="externalFileLinkSettingsForm"
            name="expires_in_hours"
            label="Link validity"
            :options="expirationOptions"
            :columns="5"
            seamless
          />
        </div>

        <div class="mt-4">
          <UButton
            type="submit"
            :loading="externalFileLinkSettingsForm.busy"
          >
            Save changes
          </UButton>
        </div>
      </form>
    </VForm>
  </div>
</template>

<script setup>
const alert = useAlert()
const { current: workspace } = useCurrentWorkspace()
const { invalidateAll } = useWorkspaces()

const expirationOptions = [
  { name: 24, label: '24 hours' },
  { name: 72, label: '3 days' },
  { name: 168, label: '7 days' },
  { name: 336, label: '14 days' },
  { name: 720, label: '30 days' },
]

const externalFileLinkSettingsForm = useForm({
  expires_in_hours: 24,
})

function initSettings() {
  externalFileLinkSettingsForm.expires_in_hours = workspace.value?.settings?.external_file_links?.expires_in_hours || 24
}

function saveChanges() {
  externalFileLinkSettingsForm
    .put(`/open/workspaces/${workspace.value.id}/external-file-link-settings`)
    .then(() => {
      invalidateAll()
      alert.success('External file link settings saved.')
    })
    .catch((error) => {
      alert.error('Failed to update external file link settings: ' + (error.response?.data?.message || 'Unknown error'))
    })
}

onMounted(() => {
  initSettings()
})

watch(workspace, () => {
  initSettings()
})
</script>
