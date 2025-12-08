<template>
  <VForm size="sm">
    <div class="space-y-4">
      <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h3 class="text-lg font-medium text-neutral-900">Submission Settings</h3>
          <p class="mt-1 text-sm text-neutral-500">
            Configure how form submissions are handled.
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-end gap-4">
        <TextInput
          name="submit_button_text"
          :form="form"
          class="max-w-xs"
          label="Submit button text"
        />
        <TextInput
          v-if="isFocused"
          v-model="focusedNextText"
          name="focused_next_button_text"
          class="max-w-xs"
          label="Next button text"
          :placeholder="$t('forms.buttons.next')"
        />
      </div>
      <ToggleSwitchInput
        name="auto_save"
        :form="form"
        label="Auto save form response"
        help="Saves form progress, allowing respondents to resume later."
        class="mt-4"
        :disabled="hasPaymentBlock"
      />
      <UAlert
        v-if="hasPaymentBlock"
        color="primary"
        variant="subtle"
        title="Must be enabled with a payment block."
        class="max-w-md"
      />
      
      <FlatSelectInput
        :form="submissionOptions"
        name="databaseAction"
        class="mt-4 max-w-xs"
        label="Database Submission Action"
        :options="[
          { name: 'Create new record', value: 'create' },
          { name: 'Update existing record', value: 'update' }
        ]"
        :required="true"
      />
      <div
        v-if="submissionOptions.databaseAction == 'update'"
        class="bg-neutral-50 border rounded-lg px-4 py-2"
      >
        <div
          class="w-auto max-w-lg"
        >
          <p class="mb-2 mt-2 text-neutral-500 text-sm">
            When matching values are found in the selected column(s), the (first) existing record will be updated instead of creating a new record. If there's no match, a new record will be created.
            <a
              href="#"
              class="text-blue-500 hover:underline"
              @click.prevent="crisp.openHelpdeskArticle('how-to-update-a-record-on-form-submission-1t1jwmn')"
            >
              Learn more.
            </a>
          </p>
          <select-input
            v-if="filterableFields.length"
            :form="form"
            name="database_fields_update"
            label="Properties to check on update"
            :options="filterableFields"
            multiple
            clearable
          />

          <toggle-switch-input
            v-model="clearEmptyFieldsOnUpdate"
            class="mt-4"
            label="Clear empty fields on update"
            help="When enabled, fields left empty in submission will clear existing values in Submission. When disabled, only filled fields are updated."
          />
        </div>
      </div>

      <!-- Advanced Submission Settings -->
      <div class="mb-8">
        <h4 class="font-semibold mt-4 border-t pt-4">
          Advanced Submission Options <ProTag  class="ml-1"/>
        </h4>
        <p class="text-gray-500 text-sm mb-4">
          Configure advanced options for form submissions and data collection.
        </p>
        
        <ToggleSwitchInput
          name="enable_partial_submissions"
          :form="form"
          help="Capture incomplete form submissions to analyze user drop-off points and collect partial data even when users don't complete the entire form."
        >
          <template #label>
            <span class="text-sm">
              Collect partial submissions
            </span>
            <ProTag
              class="ml-1"
              upgrade-modal-title="Upgrade to collect partial submissions"
              upgrade-modal-description="Capture valuable data from incomplete form submissions. Analyze where users drop off and collect partial information even when they don't complete the entire form."
            />
          </template>
        </ToggleSwitchInput>
      </div>

      <ToggleSwitchInput
        class="mt-4"
        name="enable_ip_tracking"
        :form="form"
        help="Collect and store submitter IP addresses for analytics, fraud prevention, and geographic insights. Helps identify submission patterns and improve security."
      >
        <template #label>
          <span class="text-sm">
            Collect IP addresses
          </span>
          <ProTag
            class="ml-1"
            upgrade-modal-title="Upgrade to collect IP addresses"
            upgrade-modal-description="Automatically capture submitter IP addresses to gain valuable insights into your form traffic. Analyze geographic patterns, detect suspicious activity, and enhance your form security with detailed submission analytics."
          />
        </template>
      </ToggleSwitchInput>
      <UAlert
        v-if="form.enable_ip_tracking"
        color="neutral"
        icon="i-heroicons-shield-exclamation"
        variant="subtle"
        title="GDPR and Privacy Compliance"
        description="Ensure your privacy policy discloses IP address collection and obtain proper user consent where required."
        class="mt-4 max-w-md"
      />

      <!-- Post-Submission Behavior -->
      <div class="mb-8">
        <h4 class="font-semibold mt-4 border-t pt-4">
          After Submission <pro-tag
            upgrade-modal-title="Upgrade to customize post-submission behavior"
            upgrade-modal-description="Customize post-submission behavior: redirect users, show custom messages, or trigger actions. Upgrade to unlock advanced options for a seamless user experience. We have plenty of other pro features to enhance your form functionality and user engagement."
          />
        </h4>
        <p class="text-neutral-500 text-sm mb-4">
          Customize the user experience after form submission.
        </p>

        <OptionSelectorInput
          label="Action after form submission"
          v-model="submissionOptions.submissionMode"
          :options="[
            { name: 'default', label: 'Show Success page' },
            { name: 'redirect', label: 'Redirect' }
          ]"
          option-key="name"
          :columns="2"
          class="mb-4 max-w-xs"
        />

        <div
          v-if="submissionOptions.submissionMode"
          class="bg-gray-50 border rounded-lg px-4 py-2"
        >
          <div class="w-auto max-w-lg">
            <template v-if="submissionOptions.submissionMode === 'redirect'">
              <MentionInput
                name="redirect_url"
                :form="form"
                :mentions="form.properties"
                class="w-full max-w-xs"
                label="Redirect URL"
                placeholder="https://www.google.com"
                :required="true"
              />
            </template>
            <template v-else>
              <rich-text-area-input
                enable-mentions
                :mentions="form.properties"
                :allow-fullscreen="true"
                name="submitted_text"
                class="w-full"
                :form="form"
                label="Success page text"
                :required="false"
                :max-char-limit="10000"
                :show-char-limit="true"
              />
              <div class="flex items-center flex-wrap gap-x-4 mt-4">
                <toggle-switch-input
                  name="re_fillable"
                  class="w-full max-w-xs"
                  :form="form"
                  label="Re-fillable form"
                  help="Allows user to fill the form multiple times"
                />
                <text-input
                  v-if="form.re_fillable"
                  name="re_fill_button_text"
                  :form="form"
                  label="Text of re-start button"
                />
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Editable Submissions Settings -->
      <div class="mb-8">
        <h4 class="font-semibold mt-4 border-t pt-4">
          Editable Submissions <ProTag
              class="ml-1"
              upgrade-modal-title="Upgrade to use Editable Submissions"
              upgrade-modal-description="On the Free plan, you can try out all paid features only within the form editor. Upgrade your plan to allow users to update their submissions via a unique URL, and much more. Gain full access to all advanced features."
            />
        </h4>
        <p class="text-gray-500 text-sm mb-4">
          Allow respondents to edit and update their submissions via a unique link.
        </p>
        <toggle-switch-input
          name="editable_submissions"
          class="w-full max-w-sm"
          help="Allows users to edit submissions via unique URL"
          :form="form"
          label="Enable editable submissions"
        />
        <text-input
          v-if="form.editable_submissions"
          name="editable_submissions_button_text"
          class="w-full max-w-64 mt-4"
          :form="form"
          label="Edit submission button text"
          :required="true"
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

const submissionOptions = ref({})

const filterableFields = computed(() => {
  if (submissionOptions.value.databaseAction !== "update") return []
  return form.value.properties
    .filter((field) => {
      return (
        !field.hidden &&
        !["files", "signature", "multi_select", "matrix", 'payment'].includes(field.type)
      )
    })
    .map((field) => {
      return {
        name: field.name,
        value: field.id,
      }
    })
})

const clearEmptyFieldsOnUpdate = computed({
  get: () => form.value.clear_empty_fields_on_update ?? false,
  set: (value) => { form.value.clear_empty_fields_on_update = value }
})

watch({
  redirect_url: form.value.redirect_url,
  database_fields_update: form.value.database_fields_update
}, () => {
  if (form.value) {
    submissionOptions.value = {
      submissionMode: form.value.redirect_url ? 'redirect' : 'default',
      databaseAction: form.value.database_fields_update ? 'update' : 'create'
    }
  }
}, { immediate: true })

watch(submissionOptions, (val) => {
  if (val.submissionMode === 'default') form.value.redirect_url = null
  if (val.databaseAction === 'create') form.value.database_fields_update = null
}, { deep: true })

const hasPaymentBlock = computed(() => {
  return form.value.properties.some(property => property.type === 'payment')
})

const isFocused = computed(() => form.value?.presentation_style === 'focused')

onMounted(() => {
  // Ensure translations is a plain, writable object (avoid writing into readonly proxies)
  const t = form.value?.translations
  if (!t || typeof t !== 'object' || Array.isArray(t)) {
    form.value.translations = {}
  } else {
    form.value.translations = { ...t }
  }
})

const focusedNextText = computed({
  get() {
    return form.value?.translations?.focused_next_button_text || ''
  },
  set(val) {
    const current = form.value?.translations && typeof form.value.translations === 'object' ? form.value.translations : {}
    // Replace the entire translations object to avoid setting into a readonly proxy
    form.value.translations = { ...current, focused_next_button_text: val }
  }
})
</script>
