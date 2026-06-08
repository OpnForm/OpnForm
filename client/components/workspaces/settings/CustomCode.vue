<template>
  <div class="space-y-4">
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div class="flex-1">
        <h3 class="text-lg font-medium text-neutral-900">
          Custom Code
        </h3>
        <p class="mt-1 text-sm text-neutral-500">
          The code will be injected in the <b>head</b> section of all forms in this workspace. Workspace code is applied first, then form-specific code (if any).
        </p>
      </div>
      <div class="flex gap-2">
        <UButton
          label="SDK Docs"
          icon="i-heroicons-code-bracket"
          variant="outline"
          color="neutral"
          to="https://docs.opnform.com/embedding/javascript-sdk#custom-code-integration"
          target="_blank"
        />
        <UButton
          label="Help"
          icon="i-heroicons-question-mark-circle"
          variant="outline"
          color="neutral"
          @click="crisp.openHelpdeskArticle('how-do-i-add-custom-code-to-my-form-1amadj3')"
        />
      </div>
    </div>

    <UAlert
      v-if="!canAccessCustomCode"
      icon="i-heroicons-lock-closed"
      class="mb-4"
      color="warning"
      variant="subtle"
      :title="upgradeCalloutTitle"
      :description="upgradeCalloutDescription"
      :actions="[{
        label: upgradeCalloutActionLabel,
        color: 'warning',
        variant: 'solid',
        onClick: () => openUpgradeModal()
      }]"
    />

    <VForm size="sm">
      <form
        @submit.prevent="saveChanges"
      >
        <div class="space-y-4">
          <div>
            <CodeInput
              :allow-fullscreen="true"
              name="custom_code"
              class="mt-4"
              :form="customCodeForm"
              :disabled="!canUseCustomCode"
              :help="customCodeHelp"
              label="Custom Code"
              placeholder="<script>console.log('Hello World!')</script>"
            />
          </div>

          <div class="pt-6">
            <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
              <div>
                <h3 class="text-lg font-medium text-neutral-900">
                  Custom CSS
                </h3>
                <p class="mt-1 text-sm text-neutral-500">
                  The CSS will be injected in the <b>head</b> of all forms in this workspace.
                </p>
              </div>
              <UButton
                label="Help"
                icon="i-heroicons-question-mark-circle"
                variant="outline"
                color="neutral"
                @click="crisp.openHelpdeskArticle('can-i-style-my-form-with-some-custom-css-code-1v3dlr9')"
              />
            </div>
            <CodeInput
              :allow-fullscreen="true"
              language-mode="css"
              name="custom_css"
              class="mt-4"
              :form="customCodeForm"
              :disabled="!canAccessCustomCode"
              help="CSS only. Example: body { background: #f8fafc }"
              label="Custom CSS"
              placeholder="body { background: #f8fafc }"
            />
          </div>
        </div>

        <div class="mt-4">
          <UButton
            type="submit"
            :loading="customCodeForm.busy"
            :disabled="!canAccessCustomCode"
            color="primary"
          >
            Save Changes
          </UButton>
        </div>
      </form>
    </VForm>
  </div>
</template>

<script setup>
const alert = useAlert()
const crisp = useCrisp()
const { current: workspace } = useCurrentWorkspace()
const { invalidateAll } = useWorkspaces()
const { hasFeature } = usePlanFeatures()
const canAccessCustomCode = computed(() => hasFeature('custom_code'))
const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
const { openSubscriptionModal } = useAppModals()

const upgradeCalloutTitle = computed(() => {
  return isSelfHosted.value ? 'Enterprise license required' : 'Business plan required'
})

const upgradeCalloutDescription = computed(() => {
  if (isSelfHosted.value) {
    return 'Workspace-level custom code and CSS require an Enterprise self-hosted license. Purchase or activate a license to inject scripts and styles across all forms in this workspace.'
  }

  return 'Workspace-level custom code and CSS require a Business plan. Upgrade to inject scripts, styles, analytics snippets, and advanced tracking across all forms in this workspace.'
})

const upgradeCalloutActionLabel = computed(() => {
  return isSelfHosted.value ? 'Purchase license' : 'Upgrade to Business'
})

const openUpgradeModal = () => {
  openSubscriptionModal({
    plan: isSelfHosted.value ? 'self_hosted' : 'business',
    modal_title: isSelfHosted.value ? 'Enterprise license required for custom code' : 'Upgrade to use workspace-level custom code',
    modal_description: upgradeCalloutDescription.value
  })
}

const customCodeForm = useForm({
  custom_code: '',
  custom_css: ''
})

const hasCustomDomain = computed(() => {
  return workspace.value?.custom_domains && workspace.value.custom_domains.length > 0
})

const allowSelfHosted = computed(() => !!useFeatureFlag('custom_code.enable_self_hosted', false))

const canUseCustomCode = computed(() => {
  if (!canAccessCustomCode.value) return false
  return hasCustomDomain.value || (isSelfHosted.value && allowSelfHosted.value)
})

const customCodeHelp = computed(() => {
  if (canUseCustomCode.value) {
    return 'Saves changes and visit any form page to test. Workspace code is applied to all forms in this workspace.'
  }
  if (isSelfHosted.value && !allowSelfHosted.value && !hasCustomDomain.value) {
    return 'Custom code is disabled for safety on self-hosted. Enable via CUSTOM_CODE_ENABLE_SELF_HOSTED=true. See technical docs: https://docs.opnform.com/introduction'
  }
  return 'Custom code requires a Pro plan and a custom domain configured for this workspace.'
})

const saveChanges = () => {
  if (!canAccessCustomCode.value) return

  customCodeForm
    .put(`/open/workspaces/${workspace.value.id}/custom-code-settings`, {
      data: {
        custom_code: customCodeForm.custom_code || null,
        custom_css: customCodeForm.custom_css || null,
      },
    })
    .then((_data) => {
      alert.success("Custom code settings saved.")
      // Invalidate workspace cache to refresh data
      invalidateAll()
    })
    .catch((error) => {
      alert.error("Failed to update custom code settings: " + (error?.data?.message || error.message))
    })
}

const initCustomCode = () => {
  if (!workspace.value) return
  const settings = workspace.value.settings || {}
  customCodeForm.custom_code = settings.custom_code || ''
  customCodeForm.custom_css = settings.custom_css || ''
}

onMounted(() => {
  initCustomCode()
})

watch(
  () => workspace.value,
  () => {
    initCustomCode()
  },
  { deep: true }
)
</script>
