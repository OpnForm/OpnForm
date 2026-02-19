<template>
  <div class="w-full border-b p-2 flex gap-x-2 items-center bg-white">
    <a
      href="#"
      class="ml-2 flex text-blue font-semibold text-sm -m-1 hover:bg-blue-500/10 rounded-md p-1 group"
      @click.prevent="$emit('go-back')"
    >
      <Icon
        name="heroicons:arrow-left-20-solid"
        class="text-blue mr-1 w-6 h-6 group-hover:-translate-x-0.5 transition-all"
      />
    </a>

    <UButton
      color="neutral"
      variant="subtle"
      icon="i-heroicons-cog-6-tooth"
      label="Settings"
      @click="settingsModal = true"
    />

    <div class="flex-grow flex justify-center gap-2">
      <EditableTag
        v-if="pdfTemplate"
        id="pdf-editor-title"
        v-model="pdfTemplate.name"
        element="h3"
        class="font-medium py-1 text-md w-48 text-neutral-500 truncate pdf-editor-title"
      />
    </div>

    <div
      class="flex items-center gap-x-2"
    >
      <TrackClick name="pdf_editor_help_button_clicked">
        <UTooltip
          text="Help"
          class="items-center relative"
          :content="{ side: 'bottom' }"
          arrow
        >
          <UButton
            variant="ghost"
            color="neutral"
            icon="i-heroicons-question-mark-circle"
            @click.prevent="crisp.openHelpdesk()"
          />
        </UTooltip>
      </TrackClick>

      <slot name="before-save" />

      <UButton
        color="neutral"
        variant="soft"
        icon="i-heroicons-eye"
        @click="previewPdf"
      >
        Preview
      </UButton>

      <UTooltip arrow :content="{side: 'bottom'}">
        <template #content>
          <UKbd
            value="meta"
            size="xs"
          />
          <UKbd
            value="s"
            size="xs"
          />
        </template>
        <TrackClick
          name="save_pdf_template_click"
        >
          <UButton
            color="primary"
            class="px-8 md:px-4 py-2"
            :loading="saving"
            icon="i-ic-outline-save"
            @click="emit('save-pdf-template')"
            label="Save Changes"
          />
        </TrackClick>
      </UTooltip>
    </div>
  </div>

  <!-- Settings Modal -->
  <UModal
    v-model:open="settingsModal"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="grow w-full">
          <h3 class="text-base font-semibold leading-6 text-neutral-900 dark:text-white">
            Settings
          </h3>
          <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Settings for this PDF template
          </p>
        </div>
      </div>
    </template>
    <template #body>
      <div class="space-y-4">
        <!-- Filename Pattern -->
        <TextInput
          v-model="pdfTemplate.filename_pattern"
          name="filename_pattern"
          label="Filename Pattern"
          :placeholder="pdfStore.defaultFilenamePattern"
          size="sm"
          help="Variables: {form_name}, {submission_id}, {date}"
        />

        <!-- Remove Branding -->
        <ToggleSwitchInput
          v-model="pdfTemplate.remove_branding"
          name="remove_branding"
          help="Hide 'PDF generated with OpnForm' footer"
        >
          <template #label>
            <span class="text-sm">
              Remove Branding
            </span>
            <ProTag
              upgrade-modal-title="Upgrade to remove PDF branding"
            />
          </template>
        </ToggleSwitchInput>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { formsApi } from '~/api/forms'
import EditableTag from '~/components/app/EditableTag.vue'
import TrackClick from '~/components/global/TrackClick.vue'
import ProTag from '~/components/app/ProTag.vue'

const emit = defineEmits(['go-back', 'save-pdf-template'])

const alert = useAlert()
const pdfStore = useWorkingPdfStore()
const { content: pdfTemplate, form, saving } = storeToRefs(pdfStore)

defineShortcuts({
  meta_s: {
    handler: () => emit('save-pdf-template')
  }
})

const crisp = useCrisp()

const settingsModal = ref(false)

// Preview PDF
const previewPdf = async () => {
  if (pdfStore.hasUnsavedChanges) {
    alert.warning('You have unsaved changes. Please save changes before previewing.')
    return
  }

  try {
    const response = await formsApi.pdfTemplates.getPreviewSignedUrl(form.value.id, pdfTemplate.value.id)
    window.open(response.url, '_blank')
  } catch (error) {
    alert.error(error?.data?.message || error?.message || 'Failed to open PDF preview.')
  }
}
</script>
