<template>
  <IntegrationWrapper
    v-model="props.integrationData"
    :integration="props.integration"
    :form="form"
  >
    <p class="text-neutral-500 text-sm mb-3">
      You can <a
        class="underline cursor-pointer"
        @click="openEmailsModal"
      >
        use our custom SMTP feature
      </a> to send emails from your own domain.
    </p>

    <MentionInput
      :form="integrationData"
      :mentions="form.properties"
      :disable-mention="!form.is_pro"
      :disabled="!form.is_pro"
      name="data.send_to"
      required
      label="Send To"
    >
      <template #help>
        <InputHelp>
        <span v-if="form.is_pro">
          Add one email per line
        </span>
        <span v-else>
          You can only send email notification to your own email address. 
          Please <a
            class="underline cursor-pointer"
            @click="openSubscriptionModal"
          >upgrade to the Pro plan</a> to send to other email addresses.
        </span>
        </InputHelp>
      </template>
    </MentionInput> 
    <div class="flex space-x-4 mt-4">
      <MentionInput
        :form="integrationData"
        :mentions="form.properties"
        name="data.sender_name"
        label="Sender Name"
        class="flex-1"
      />
      <text-input
        v-if="selfHosted"
        :form="integrationData"
        name="data.sender_email"
        label="Sender Email"
        help="If supported by email provider - default otherwise"
        class="flex-1"
      />
    </div>
    <MentionInput
      :form="integrationData"
      :mentions="form.properties"
      required
      name="data.subject"
      label="Subject"
    />
    <rich-text-area-input
      :form="integrationData"
      :enable-mentions="true"
      :enable-image="true"
      :mentions="form.properties"
      name="data.email_content"
      label="Email Content"
      class="mt-4"
    />
    <collapse
      v-model="showEmailAppearance"
      class="mt-4 w-full border rounded-lg bg-gray-50 dark:bg-neutral-900 pr-4"
    >
      <template #title>
        <div class="flex gap-x-3 items-start pr-12 p-4">
          <div
            class="transition-colors"
            :class="{
              'text-blue-600': showEmailAppearance,
              'text-gray-300 dark:text-neutral-500': !showEmailAppearance,
            }"
          >
            <Icon
              name="heroicons:paint-brush-16-solid"
              size="24"
            />
          </div>
          <div class="grow">
            <h4 class="font-semibold flex items-center gap-2">
              Email appearance
              <ProTag upgrade-modal-title="Upgrade to customise email appearance" />
            </h4>
            <p class="text-gray-400 dark:text-neutral-500 text-xs">
              Logo, fonts and colors for your email notifications
            </p>
          </div>
        </div>
      </template>
      <div class="border-t dark:border-neutral-700 p-4 space-y-4">
        <div
          v-if="emailAppearanceLocked"
          class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-100"
        >
          Email appearance customisation is part of the Pro plan.
          <a
            class="underline cursor-pointer"
            @click="openSubscriptionModal"
          >
            Upgrade to Pro
          </a>
          to add your logo, custom fonts and colors.
        </div>
        <image-input
          :form="integrationData"
          :disabled="emailAppearanceLocked"
          name="data.logo_url"
          label="Logo"
          help="Display your logo in the email header (replaces app name)"
        />
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div>
            <label class="text-neutral-700 dark:text-neutral-300 font-semibold text-xs mb-2 block">Font family</label>
            <UButton
              color="neutral"
              block
              size="lg"
              variant="outline"
              :disabled="emailAppearanceLocked"
              @click="showGoogleFontPicker = true"
            >
              <span :style="{ 'font-family': (integrationData.data.font_family ? integrationData.data.font_family + ', sans-serif' : null) }">
                {{ integrationData.data.font_family || 'Default' }}
              </span>
            </UButton>
            <GoogleFontPicker
              :show="showGoogleFontPicker"
              :font="integrationData.data.font_family || null"
              @close="showGoogleFontPicker = false"
              @apply="onApplyFont"
            />
          </div>
          <ColorInput
            :form="integrationData"
            :disabled="emailAppearanceLocked"
            name="data.font_color"
            label="Font color"
            help="Color of the text in the email"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <ColorInput
            :form="integrationData"
            :disabled="emailAppearanceLocked"
            name="data.outer_background_color"
            label="Outer background color"
            help="Background around the email content area"
          />
          <ColorInput
            :form="integrationData"
            :disabled="emailAppearanceLocked"
            name="data.inner_background_color"
            label="Inner background color"
            help="Background of the email content area"
          />
        </div>
      </div>
    </collapse>

    <toggle-switch-input
      :form="integrationData"
      name="data.include_submission_data"
      class="mt-4"
      label="Include submission data"
      help="If enabled the email will contain form submission answers"
    />
    <toggle-switch-input
      v-if="integrationData.data.include_submission_data"
      :form="integrationData"
      name="data.include_hidden_fields_submission_data"
      class="mt-4"
      label="Include hidden fields"
      help="If enabled the email will contain hidden fields"
    />
    <toggle-switch-input
      v-if="form.editable_submissions"
      :form="integrationData"
      name="data.link_edit_submission"
      class="mt-4"
      label="Edit Submission Link"
    />
    <MentionInput
      :form="integrationData"
      :mentions="form.properties"
      class="mt-4"
      name="data.reply_to"
      label="Reply To"
      help="If empty, Reply-to will be your own email."
    />
  </IntegrationWrapper>
</template>

<script setup>
import IntegrationWrapper from "./components/IntegrationWrapper.vue"
import GoogleFontPicker from "~/components/open/editors/GoogleFontPicker.vue"
import Collapse from "~/components/app/Collapse.vue"
import ProTag from "~/components/app/ProTag.vue"

const props = defineProps({
  integration: { type: Object, required: true },
  form: { type: Object, required: true },
  integrationData: { type: Object, required: true },
  formIntegrationId: { type: Number, required: false, default: null },
})

const selfHosted = computed(() => useFeatureFlag('self_hosted'))
const { openWorkspaceSettings } = useAppModals()
const { data: user } = useAuth().user()

const showEmailAppearance = ref(false)
const showGoogleFontPicker = ref(false)
const emailAppearanceLocked = computed(() => !props.form.is_pro)

function onApplyFont(val) {
  if (props.integrationData.data) {
    props.integrationData.data.font_family = val
  }
  showGoogleFontPicker.value = false
}

function openEmailsModal () {
  openWorkspaceSettings('emails')
}

function openSubscriptionModal () {
  useAppModals().openSubscriptionModal({
    modal_title: 'Upgrade to unlock powerful email integration',
    modal_description: 'Upgrade to Pro to customize email notification recipients, send confirmation email to form respondents, and more: form customization, custom domain, collaboration, etc.'
  })
}

onBeforeMount(() => {
  for (const [keyname, defaultValue] of Object.entries({
    send_to: user.value.email || '',
    sender_name: "OpnForm",
    subject: "We saved your answers",
    email_content: "Hello there 👋 <br>This is a confirmation that your submission was successfully saved.",
    include_submission_data: true,
    include_hidden_fields_submission_data: false,
    logo_url: null,
    font_family: null,
    font_color: null,
    outer_background_color: '#f0f0f0',
    inner_background_color: '#ffffff',
  })) {
    if (props.integrationData.data[keyname] === undefined) {
      props.integrationData.data[keyname] = defaultValue
    }
  }
})
</script>
