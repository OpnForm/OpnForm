<template>
  <div class="w-full border-b p-2 flex gap-x-2 items-center bg-white">
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
      v-model="settingsModal"
      @close="settingsModal = false"
      hydrate-on-interaction
    />

    <GitHubStar
      v-if="isSelfHosted"
      class="mt-2 ml-2"
    />

    <div class="flex-grow flex justify-center gap-2">
      <EditableTag
        id="form-editor-title"
        v-model="form.title"
        element="h3"
        class="font-medium py-1 text-md w-48 text-neutral-500 truncate form-editor-title"
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

    <div
      class="flex items-stretch gap-x-2"
    >
      <TrackClick name="form_editor_help_button_clicked">
        <UTooltip
          text="Help"
          class="items-center relative"
          :content="{ side: 'bottom' }"
          arrow
        >
          <UButton
            color="ghost"
            icon="i-heroicons-question-mark-circle"
            class="p-2 text-neutral-500 hover:text-neutral-800"
            @click.prevent="crisp.openHelpdesk()"
          />
        </UTooltip>
      </TrackClick>
      <slot name="before-save" />
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
            icon="i-ic-outline-save"
            @click="emit('save-form')"
            :label="form.visibility === 'public' ? 'Publish Form' : 'Save Changes'"
          />
        </TrackClick>
      </UTooltip>
    </div>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import UndoRedo from '~/components/open/editors/UndoRedo.vue'
import FormSettingsModal from '~/components/open/forms/components/form-components/FormSettingsModal.vue'
import EditableTag from '~/components/app/EditableTag.vue'
import TrackClick from '~/components/global/TrackClick.vue'
import { useFeatureFlag } from '~/composables/useFeatureFlag'

defineProps({
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

const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
</script>