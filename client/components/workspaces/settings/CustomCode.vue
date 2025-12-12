<template>
  <div class="space-y-4">
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div class="flex-1">
        <h3 class="text-lg font-medium text-neutral-900">
          Custom Code <ProTag
            class="mb-2 block"
            upgrade-modal-title="Upgrade to Unlock Custom Code Capabilities"
            upgrade-modal-description="On the Free plan, you can explore custom code features within the workspace settings. Upgrade your plan to implement custom scripts, styles, and advanced tracking in all your workspace forms. Elevate your forms' functionality and design with unlimited customization options."
          />
        </h3>
        <p class="mt-1 text-sm text-neutral-500">
          The code will be injected in the <b>head</b> section of all forms in this workspace. Workspace code is applied first, then form-specific code (if any).
        </p>
      </div>
      <UButton
        label="Help"
        icon="i-heroicons-question-mark-circle"
        variant="outline"
        color="neutral"
        @click="crisp.openHelpdeskArticle('how-do-i-add-custom-code-to-my-form-1amadj3')"
      />
    </div>

    <UAlert
      v-if="!workspace.is_pro"
      icon="i-heroicons-user-group-20-solid"
      class="mb-4"
      color="warning"
      variant="subtle"
      title="Pro plan required"
      description="Please upgrade your account to use workspace-level custom code."
      :actions="[{
        label: 'Try Pro plan',
        color: 'warning',
        variant: 'solid',
        onClick: () => openSubscriptionModal()
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
                  Custom CSS <ProTag
                    class="mb-2 block"
                    upgrade-modal-title="Upgrade to Unlock Custom CSS"
                    upgrade-modal-description="On the Free plan, you can explore custom CSS within the workspace settings. Upgrade to apply custom styles to all your workspace forms."
                  />
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
              :disabled="!workspace.is_pro"
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
            :disabled="!workspace.is_pro"
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
import ProTag from "~/components/app/ProTag.vue"

const alert = useAlert()
const crisp = useCrisp()
const { current: workspace } = useCurrentWorkspace()
const { openSubscriptionModal: openModal } = useAppModals()
const { invalidateAll } = useWorkspaces()

const openSubscriptionModal = () => {
  openModal({ modal_title: 'Upgrade to use workspace level custom code' })
}

const customCodeForm = useForm({
  custom_code: '',
  custom_css: ''
})

const hasCustomDomain = computed(() => {
  return workspace.value?.custom_domains && workspace.value.custom_domains.length > 0
})

const selfHosted = computed(() => !!useFeatureFlag('self_hosted', false))
const allowSelfHosted = computed(() => !!useFeatureFlag('custom_code.enable_self_hosted', false))

const canUseCustomCode = computed(() => {
  if (!workspace.value?.is_pro) return false
  return hasCustomDomain.value || (selfHosted.value && allowSelfHosted.value)
})

const customCodeHelp = computed(() => {
  if (canUseCustomCode.value) {
    return 'Saves changes and visit any form page to test. Workspace code is applied to all forms in this workspace.'
  }
  if (selfHosted.value && !allowSelfHosted.value && !hasCustomDomain.value) {
    return 'Custom code is disabled for safety on self-hosted. Enable via CUSTOM_CODE_ENABLE_SELF_HOSTED=true. See technical docs: https://docs.opnform.com/introduction'
  }
  return 'Custom code requires a Pro plan and a custom domain configured for this workspace.'
})

const saveChanges = () => {
  if (!workspace.value?.is_pro) return

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
      alert.error("Failed to update custom code settings: " + (error.response?.data?.message || error.message))
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
