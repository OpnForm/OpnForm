<template>
  <div class="px-4 pb-4">
    <EditorSectionHeader
      icon="heroicons:paint-brush-16-solid"
      title="Basic Appearance"
      :show-line="false"
    />

    <PresentationStyleSwitch />

    <select-input
      name="theme"
      class="mt-4"
      :options="[
        { name: 'Default', value: 'default' },
        { name: 'Notion', value: 'notion' },
        { name: 'Simple (no shadows)', value: 'simple' },
        { name: 'Minimal', value: 'minimal' },
        { name: 'Transparent', value: 'transparent' }
      ]"
      :form="form"
      label="Form Theme"
    />

    <color-input
      name="color"
      :form="form"
      label="Accent Color"
      class="my-4"
    >
      <template #label>
        <InputLabel label="">Accent Color - <a
          href="#" class="text-blue-500"
          @click.prevent="form.color = DEFAULT_COLOR"
        >Reset</a></InputLabel>
      </template>
    </color-input>

    <OptionSelectorInput
      v-model="form.dark_mode"
      :form="form"
      name="dark_mode"
      label="Color Mode"
      :options="[
        { name: 'auto', label: 'System', icon: 'i-heroicons-computer-desktop' },
        { name: 'light', label: 'Light', icon: 'i-heroicons-sun' },
        { name: 'dark', label: 'Dark', icon: 'i-heroicons-moon' },
      ]"
      :multiple="false"
      :columns="3"
      class="mb-4"
    />

    <EditorSectionHeader
      icon="octicon:typography-16"
      title="Text & Language"
    />
    <div class="grid grid-cols-2 gap-4">
      <div class="flex-grow my-1" v-if="useFeatureFlag('services.google.fonts')">
        <label class="text-neutral-700 font-semibold text-xs mb-0.5 block">Font Family</label>
        <UButton
          color="neutral"
          variant="outline"
          block
          @click="showGoogleFontPicker = true"
        >
          <span :style="{ 'font-family': (form.font_family ? form.font_family + ' !important' : null) }">
            {{ form.font_family || 'Default' }}
          </span>
        </UButton>
        <GoogleFontPicker
          :show="showGoogleFontPicker"
          :font="form.font_family || null"
          @close="showGoogleFontPicker = false"
          @apply="onApplyFont"
        />
      </div>

      <div class="flex-grow">
        <select-input
          name="language"
          searchable
          :options="availableLocales"
          :form="form"
          label="Language"
        />
      </div>
    </div>

    <ToggleSwitchInput
      name="layout_rtl"
      :form="form"
      label="Right-to-Left Layout"
    />
    
    <toggle-switch-input
      name="uppercase_labels"
      :form="form"
      label="Uppercase Input Labels"
    />

    <EditorSectionHeader
      icon="heroicons:rectangle-stack-16-solid"
      title="Layout & Sizing"
    />
    <div class="grid grid-cols-2 gap-4">
      <OptionSelectorInput
        seamless
        label="Input Size"
        v-model="form.size"
        :form="form"
        name="size"
        :options="[
          { name: 'sm', label:'S'},
          { name: 'md', label:'M' },
          { name: 'lg', label:'L' },
        ]"
        :multiple="false"
        :columns="3"
        class="mb-4"
      />
      <OptionSelectorInput
        v-if="form.theme !== 'transparent'"
        label="Input Roundness"
        v-model="form.border_radius"
        seamless
        :form="form"
        name="border_radius"
        :options="[
          { name: 'none', icon: 'i-tabler-border-corner-square' },
          { name: 'small', icon: 'i-tabler-border-corner-rounded' },
          { name: 'full', icon: 'i-tabler-border-corner-pill' },
        ]"
        :multiple="false"
        :columns="3"
        class="mb-4"
      />
    </div>

    <OptionSelectorInput
      v-model="form.width"
      label="Form Width"
      :form="form"
      name="width"
      seamless
      v-if="!isFocused"
      :options="[
        { name: 'centered', label: 'Centered' },
        { name: 'full', label: 'Full Width' },
      ]"
      :multiple="false"
      :columns="2"
      class="mb-4 w-2/3"
    />

    <EditorSectionHeader
      icon="heroicons:tag-16-solid"
      title="Branding"
    />
    <div class="grid grid-cols-2 gap-4">
      <image-input
        name="logo_picture"
        :form="form"
        label="Logo"
        :required="false"
      />

      <ImageWithSettings :form="form" name="cover_picture" :label="isFocused ? 'Background' : 'Cover (~1500px)'" kind="cover" />
    </div>

    <toggle-switch-input
      name="no_branding"
      :form="form"
      class="mt-4"
      @update:model-value="onChangeNoBranding"
    >
      <template #label>
        <InputLabel
          :label="'Hide OpnForm Branding'"
          :native-for="'no_branding'"
          class="text-sm font-medium!"
        />
        <pro-tag
          upgrade-modal-title="Upgrade today to remove OpnForm branding"
          class="-mt-1"
        />
      </template>
    </toggle-switch-input>

    <EditorSectionHeader
      icon="heroicons:cog-6-tooth-16-solid"
      title="Advanced Options"
    />

    <toggle-switch-input
      v-if="isFocused"
      name="settings.navigation_arrows"
      :form="form"
      class="mt-2"
      label="Show navigation arrows"
    />
    <toggle-switch-input
      name="show_progress_bar"
      :form="form"
      label="Show progress bar"
      :help="
        form.show_progress_bar
          ? 'The bar is at the top of the page (above navigation in this editor) or below the title when embedded'
          : ''
      "
    />
    <toggle-switch-input
      name="transparent_background"
      :form="form"
      label="Transparent Background"
      help="When form is embedded"
    />
    <toggle-switch-input
      name="confetti_on_submission"
      :form="form"
      label="Confetti on successful submisison"
      @update:model-value="onChangeConfettiOnSubmission"
    />
    <ToggleSwitchInput
      name="auto_focus"
      :form="form"
      label="Auto focus first input on page"
    />
  </div>
</template>

<script setup>
import EditorSectionHeader from "./EditorSectionHeader.vue"
import { useWorkingFormStore } from "../../../../../stores/working_form"
import GoogleFontPicker from "../../../editors/GoogleFontPicker.vue"
import ProTag from "~/components/app/ProTag.vue"
import { DEFAULT_COLOR, ensureSettingsObject } from "@/composables/forms/initForm"
import PresentationStyleSwitch from "./PresentationStyleSwitch.vue"
import ImageWithSettings from "../media/ImageWithSettings.vue"


const workingFormStore = useWorkingFormStore()
const { openSubscriptionModal } = useAppModals()
const form = storeToRefs(workingFormStore).content
const isMounted = ref(false)
const confetti = useConfetti()
const showGoogleFontPicker = ref(false)
const { $i18n } = useNuxtApp()

const { data: user } = useAuth().user()
const { current: workspace } = useCurrentWorkspace()

const isPro = computed(() => {
  if (!useFeatureFlag('billing.enabled')) return true
  if (!user.value || !workspace.value) return false
  return workspace.value.is_pro
})

const isFocused = computed(() => form.value?.presentation_style === 'focused')

const availableLocales = computed(() => {
  return $i18n.locales?.value.map(locale => ({ name: locale.name, value: locale.code })) ?? []
})

onMounted(() => {
  isMounted.value = true
  
  // Ensure settings is a plain, writable object (avoid writing into readonly proxies)
  ensureSettingsObject(form.value)
  
  // Set default value for navigation_arrows in focused mode if not defined
  if (isFocused.value && form.value.settings.navigation_arrows === undefined) {
    form.value.settings.navigation_arrows = true
  }
})

const onChangeConfettiOnSubmission = (val) => {
  if (isMounted.value && val) {
    confetti.play()
  }
}

const onChangeNoBranding = (val) => {
  if (!isPro.value && val) {
    openSubscriptionModal({ modal_title: "Upgrade today to remove OpnForm branding" })
    setTimeout(() => {
      form.value.no_branding = false
    }, 300)
  } 
}

const onApplyFont = (val) => {
  form.value.font_family = val
  showGoogleFontPicker.value = false
}
</script>
