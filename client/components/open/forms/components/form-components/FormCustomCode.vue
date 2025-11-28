<template>
  <VForm size="sm">
    <div class="space-y-4">
      <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h3 class="text-lg font-medium text-neutral-900">
            Custom Code <ProTag
              class="mb-2 block"
              upgrade-modal-title="Upgrade to Unlock Custom Code Capabilities"
              upgrade-modal-description="On the Free plan, you can explore custom code features within the form editor. Upgrade your plan to implement custom scripts, styles, and advanced tracking in live forms. Elevate your form's functionality and design with unlimited customization options."
            />
          </h3>
          <p class="mt-1 text-sm text-neutral-500">
            The code will be injected in the <b>head</b> section of your form page.
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

      <CodeInput
        :allow-fullscreen="true"
        name="custom_code"
        class="mt-4"
        :form="form"
        :disabled="!canUseCustomCode"
        :help="customCodeHelp"
        label="Custom Code"
        placeholder="<script>console.log('Hello World!')</script>"
      />

      <div class="pt-6">
        <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h3 class="text-lg font-medium text-neutral-900">
              Custom CSS <ProTag
                class="mb-2 block"
                upgrade-modal-title="Upgrade to Unlock Custom CSS"
                upgrade-modal-description="On the Free plan, you can explore custom CSS within the editor. Upgrade to apply custom styles to your live forms."
              />
            </h3>
            <p class="mt-1 text-sm text-neutral-500">
              The CSS will be injected in the <b>head</b> of your form page.
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
          :form="form"
          help="CSS only. Example: body { background: #f8fafc }"
          label="Custom CSS"
          placeholder="body { background: #f8fafc }"
        />
      </div>
    </div>
  </VForm>
</template>

<script setup>
import ProTag from "~/components/app/ProTag.vue"

const workingFormStore = useWorkingFormStore()
const { content: form } = storeToRefs(workingFormStore)
const crisp = useCrisp()

const canUseCustomCode = computed(() => workingFormStore.isCustomCodeAllowed)

const customCodeHelp = computed(() => {
  const hasCustomDomain = !!form.value?.custom_domain
  const selfHosted = !!useFeatureFlag('self_hosted', false)
  const allowSelfHosted = !!useFeatureFlag('custom_code.enable_self_hosted', false)
  if (canUseCustomCode.value) {
    return 'Saves changes and visit the actual form page to test.'
  }
  // In self-hosted mode with flag disabled (and no custom domain), show safety notice with docs link
  if (selfHosted && !allowSelfHosted && !hasCustomDomain) {
    return 'Custom code is disabled for safety on self-hosted. Enable via CUSTOM_CODE_ENABLE_SELF_HOSTED=true. See technical docs: https://docs.opnform.com/introduction'
  }
  return 'Custom code requires to be using a custom domain.'
})

</script>
