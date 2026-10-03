<template>
  <VForm size="sm">
    <div class="space-y-4">
      <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h3 class="text-lg font-medium text-neutral-900">Security & Access</h3>
          <p class="mt-1 text-sm text-neutral-500">
            Manage who can access your form and when.
          </p>
        </div>
      </div>

      <ToggleSwitchInput
        name="share_submission_data_with_parent"
        :form="form"
        label="Share answers with the embedding page"
        help="Allow the website embedding this form to receive submitted answers. Leave off to send only a submission notification."
      />

      <TextInput
        name="password"
        :form="form"
        class="mt-4 max-w-xs"
        label="Form Password"
        placeholder="********"
        help="Leave empty to disable password protection"
      />
      <DateInput
        :with-time="true"
        name="closes_at"
        class="mt-4 max-w-xs"
        :form="form"
        label="Closing date"
        help="Leave empty to keep the form open indefinitely"
        :required="false"
      />
      <div
        v-if="form.closes_at || form.visibility == 'closed'"
        class="bg-neutral-50 border rounded-lg px-4 py-2"
      >
        <rich-text-area-input
          name="closed_text"
          :form="form"
          :allow-fullscreen="true"
          label="Closed form text"
          help="This message will be shown when the form will be closed"
          :required="false"
          wrapper-class="mb-0"
        />
      </div>
      <text-input
        name="max_submissions_count"
        native-type="number"
        :min="1"
        :form="form"
        label="Limit number of submissions"
        placeholder="Max submissions"
        class="mt-4 max-w-xs"
        help="Leave empty for unlimited submissions"
        :required="false"
      />
      <div
        v-if="form.max_submissions_count && form.max_submissions_count > 0"
        class="bg-neutral-50 border rounded-lg px-4 py-2"
      >
        <rich-text-area-input
          wrapper-class="mb-0"
          :allow-fullscreen="true"
          name="max_submissions_reached_text"
          :form="form"
          label="Max Submissions reached text"
          help="This message will be shown when the form will have the maximum number of submissions"
          :required="false"
        />
      </div>

      <h4 class="font-semibold mt-4 border-t pt-4">
        Security
      </h4>
      <p class="text-neutral-500 text-sm">
        Protect your form, and your sensitive files.
      </p>
      <div
        v-if="hasCaptcha"
        class="flex items-start gap-6 flex-wrap"
      >
        <ToggleSwitchInput
          name="use_captcha"
          :form="form"
          class="mt-4"
          label="Bot Protection"
          help="Protects your form from spam and abuse with a captcha"
        />
        <FlatSelectInput
          v-if="form.use_captcha"
          name="captcha_provider"
          :form="form"
          :options="captchaOptions"
          class="mt-4 w-80"
          label="Select a captcha provider"
        />
      </div>
    </div>
  </VForm>
</template>

<script setup>
const workingFormStore = useWorkingFormStore()
const { content: form } = storeToRefs(workingFormStore)
const config = useRuntimeConfig()

const hasCaptcha = computed(() => {
  return config.public.hCaptchaSiteKey || config.public.reCaptchaSiteKey
})

const captchaOptions = computed(() => {
  const options = []
  
  if (config.public.reCaptchaSiteKey) {
    options.push({ name: 'reCAPTCHA', value: 'recaptcha' })
  }
  
  if (config.public.hCaptchaSiteKey) {
    options.push({ name: 'hCaptcha', value: 'hcaptcha' })
  }
  
  return options
})
</script>
