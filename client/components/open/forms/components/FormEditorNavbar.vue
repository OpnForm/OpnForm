<template>
  <div class="w-full border-b px-3 py-3 min-h-16 flex gap-x-3 items-center bg-white">
    <a
      v-if="backButton"
      href="#"
      class="ml-2 flex text-blue font-semibold text-sm -m-1 hover:bg-blue-500/10 rounded-md p-1 group"
      @click.prevent="$emit('go-back')"
    >
      <Icon
        name="heroicons:arrow-left-20-solid"
        class="text-blue mr-1 w-6 h-6 group-hover:-translate-x-0.5 transition-all"
      />
    </a>


    <UTabs
      id="form-editor-navbar-tabs"
      class="px-4"
      v-model="activeTab"
      :content="false"
      :items="[
        { label: 'Build', value: 'build' },
        { label: 'Design', value: 'design'}
      ]"
    />
    <UButton
      color="neutral"
      variant="subtle"
      icon="i-heroicons-cog-6-tooth"
      label="Settings"
      @click="settingsModal = true"
    />
    <FormSettingsModal
      v-if="settingsModal"
      v-model="settingsModal"
      v-model:active-tab="settingsModalActiveTab"
      :computed-variable-edit-request="computedVariableEditRequest"
      :saving="updateFormLoading"
      :save-label="isEdit ? 'Save and close' : 'Done'"
      @save="saveSettings"
      @close="settingsModal = false"
    />

    <GitHubStar
      v-if="isSelfHosted"
      class="ml-2"
    />

    <div class="flex-grow min-w-0 flex justify-center items-center gap-2">
      <EditableTag
        id="form-editor-title"
        v-model="form.title"
        element="h1"
        :max-length="255"
        class="font-semibold py-1 text-xl leading-7 w-72 max-w-full text-neutral-700 truncate form-editor-title"
      />
      <UBadge
        v-if="form.visibility == 'draft'"
        color="warning"
        variant="soft"
        icon="i-heroicons-pencil-square"
        label="Draft"
      />
      <UBadge
        v-else-if="form.visibility == 'closed'"
        color="neutral"
        variant="soft"
        icon="i-heroicons-lock-closed-20-solid"
        label="Closed"
      />
    </div>

    <UndoRedo />

    <FormHistory />

    <div
      class="flex items-center gap-x-2"
    >
      <TrackClick name="form_editor_help_button_clicked">
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
      <div class="flex shrink-0 flex-col items-end">
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
            name="save_form_click"
          >
            <UButton
              color="primary"
              class="px-8 md:px-4 py-2"
              :loading="updateFormLoading"
              :class="saveButtonClass"
              data-testid="save-form-button"
              icon="i-ic-outline-save"
              @click="emit('save-form')"
              :label="isEdit || form.visibility !== 'public' ? 'Save Changes' : 'Publish Form'"
            />
          </TrackClick>
        </UTooltip>
        <span
          v-if="hasUnsavedChanges !== null"
          role="status"
          class="mt-1 text-xs whitespace-nowrap"
          :class="hasUnsavedChanges ? 'text-amber-700' : 'text-neutral-500'"
          data-testid="form-save-status"
        >
          {{ updateFormLoading ? 'Saving changes…' : hasUnsavedChanges ? 'Unsaved changes' : 'All changes saved' }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import FormHistory from '~/components/open/editors/FormHistory.vue'
import UndoRedo from '~/components/open/editors/UndoRedo.vue'
import EditableTag from '~/components/app/EditableTag.vue'
import TrackClick from '~/components/global/TrackClick.vue'
import { useFeatureFlag } from '~/composables/useFeatureFlag'

const FormSettingsModal = defineAsyncComponent(() => import('~/components/open/forms/components/form-components/FormSettingsModal.vue'))

const props = defineProps({
  isEdit: {
    type: Boolean,
    default: false
  },
  hasUnsavedChanges: {
    type: Boolean,
    default: null
  },
  backButton: {
    type: Boolean,
    default: true
  },
  updateFormLoading: {
    type: Boolean,
    required: true
  },
  saveButtonClass: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['go-back', 'save-form'])

defineShortcuts({
  meta_s: {
    handler: () => emit('save-form')
  }
})

const workingFormStore = useWorkingFormStore()
const crisp = useCrisp()

const form = computed(() => workingFormStore.content)
const { activeTab } = storeToRefs(workingFormStore)

const settingsModal = ref(false)
function saveSettings() {
  if (props.isEdit) {
    emit('save-form')
  } else {
    settingsModal.value = false
  }
}

const settingsModalActiveTab = ref('general')
const computedVariableEditRequest = ref(null)
let computedVariableEditRequestId = 0

function openComputedVariable({ variableId, variableIndex }) {
  computedVariableEditRequestId += 1
  computedVariableEditRequest.value = {
    variableId,
    variableIndex,
    requestId: computedVariableEditRequestId,
  }
  settingsModalActiveTab.value = 'variables'
  settingsModal.value = true
}

function openComputedVariableCreator() {
  computedVariableEditRequestId += 1
  computedVariableEditRequest.value = {
    create: true,
    requestId: computedVariableEditRequestId,
  }
  settingsModalActiveTab.value = 'variables'
  settingsModal.value = true
}

defineExpose({ openComputedVariable, openComputedVariableCreator })

const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
</script>
