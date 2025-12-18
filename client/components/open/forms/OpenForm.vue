<template>
  <form
    v-if="form"
    class="@container pb-20"
    @submit.prevent=""
  >
    <!-- Classic cover/logo rendering -->
    <div v-if="showBrandingMedia" class="mb-2">
      <div v-if="form.cover_picture">
        <div id="cover-picture" class="h-56 w-full overflow-hidden pointer-events-none">
          <BlockMediaLayout :image="coverMedia" img-class="w-full h-full object-cover" alt="Form cover image" />
        </div>
      </div>
      <div
        v-if="form.logo_picture"
        class="w-full p-5 relative mx-auto"
        :class="[
          !form.cover_picture ? 'pt-20' : '',
          form.width === 'centered' ? (isPreviewMode ? 'max-w-lg' : '@3xl:w-3/5 @5xl:w-1/2 @3xl:max-w-2xl') : '',
          (form.width === 'full' && !isIframe) ? 'max-w-7xl' : ''
        ]"
        :style="{ direction: form?.layout_rtl ? 'rtl' : 'ltr' }"
      >
        <img
          :src="form.logo_picture"
          :alt="form.seo_meta?.site_name ? `${form.seo_meta.site_name} logo` : 'Form logo'"
          :class="{ 'top-5': !form.cover_picture, '-top-10': form.cover_picture }"
          class="w-20 h-20 object-contain absolute transition-all"
        >
      </div>
    </div>

    <div
      class="w-full mx-auto px-4"
      :class="[
        (!isIframe && !showBrandingMedia) ? 'mt-6' : '',
        form && form.width === 'centered' ? (isPreviewMode ? 'max-w-lg' : '@3xl:w-3/5 @5xl:w-1/2 @3xl:max-w-2xl') : '',
        (form && form.width === 'full' && !isIframe) ? 'max-w-7xl' : ''
      ]"
    >
      <FormProgressbar :form-manager="formManager" />
      <transition
        name="fade"
        mode="out-in"
      >
        <div v-if="$slots.password" key="password" class="w-full">
          <slot name="password" />
        </div>

        <div v-else-if="$slots.alerts" key="alerts" class="w-full">
          <slot name="alerts" />
        </div>

        <div v-else-if="isSubmitted" key="submitted" class="px-2 w-full">
          <slot name="after-submit" :submittedData="submittedData" />
        </div>

        <div v-else-if="shouldDisplayForm" :key="formPageIndex" class="form-group flex flex-wrap w-full">
          <VueDraggable
            :model-value="currentFields"
            group="form-elements"
            item-key="id"
            class="grid grid-cols-12 relative transition-all w-full"
            :class="[
              draggingNewBlock ? 'rounded-md bg-blue-50 dark:bg-neutral-800' : '',
            ]"
            ghost-class="ghost-item"
            filter=".not-draggable"
            :animation="200"
            :disabled="!allowDragging"
            @add="handleDragAdd"
            @update="handleDragUpdate"
          >
            <template #default>
              <div
                v-for="element in currentFields"
                :key="element.id"
                :class="getFieldWidthClasses(element.width)"
              >
                <VTransition name="fadeHeight">
                  <open-form-field
                    :field="element"
                    :form-manager="formManager"
                  />
                </VTransition>
              </div>
            </template>
          </VueDraggable>
        </div>
      </transition>

      <!-- Captcha -->
      <CaptchaWrapper v-if="form.use_captcha && !isSubmitted" :form-manager="formManager" />

      <!--  Submit, Next and previous buttons  -->
      <div v-if="shouldDisplayForm" class="flex flex-wrap justify-center w-full">
        <open-form-button
          v-if="formPageIndex>0 && previousFieldsPageBreak"
          native-type="button"
          :form="form"
          class="mt-2 px-8 mx-1"
          @click.stop="handlePreviousClick"
        >
          {{ previousFieldsPageBreak.previous_btn_text || $t('forms.buttons.previous') }}
        </open-form-button>

        <template v-if="isLastPage">
          <slot name="submit-btn" :loading="isProcessing">
            <open-form-button
              :form="form"
              class="mt-2 px-8 mx-1"
              :loading="isProcessing"
              @click.prevent="emit('submit')"
            >
              {{ form.submit_button_text || $t('forms.buttons.submit') }}
            </open-form-button>
          </slot>
        </template>
        <open-form-button
          v-else-if="currentFieldsPageBreak"
          native-type="button"
          :form="form"
          class="mt-2 px-8 mx-1"
          :loading="isProcessing"
          @click.stop="handleNextClick"
        >
          {{ currentFieldsPageBreak.next_btn_text || $t('forms.buttons.next') }}
        </open-form-button>
        <div v-if="structure && !currentFieldsPageBreak && !isLastPage">
          {{ $t('forms.wrong_form_structure') }}
        </div>
        <div v-if="hasPaymentBlock" class="mt-6 flex justify-center w-full">
          <p class="text-xs text-neutral-400 dark:text-neutral-500 flex text-center max-w-md">
            {{ $t('forms.payment.payment_disclaimer') }}
          </p>
        </div>
      </div>

      <!-- Branding slot (renderer placement) -->
      <slot name="branding" />

      <!-- Cleanings slot -->
      <div class="fixed bottom-4 left-4 max-w-full z-10" v-if="$slots.cleanings">
        <div class="max-w-lg">
          <slot name="cleanings" />
        </div>
      </div>

    </div>

    <!-- Branding button -->
    <div v-if="!form.no_branding && showBranding" class="fixed z-10 bottom-4 right-4">
      <PoweredBy :color="form.color" />
    </div>
  </form>
</template>

<script setup>
import { VueDraggable } from 'vue-draggable-plus'
import OpenFormButton from './OpenFormButton.vue'
import BlockMediaLayout from './components/BlockMediaLayout.vue'
import CaptchaWrapper from '~/components/forms/heavy/components/CaptchaWrapper.vue'
import OpenFormField from './OpenFormField.vue'
import FormProgressbar from './FormProgressbar.vue'
import PoweredBy from '~/components/pages/forms/show/PoweredBy.vue'
import { useWorkingFormStore } from '~/stores/working_form'
import { FormMode } from '~/lib/forms/FormModeStrategy.js'
import { useIsIframe } from '~/composables/useIsIframe'

const props = defineProps({
  formManager: { type: Object, required: true }
})

const emit = defineEmits(['submit'])

const workingFormStore = useWorkingFormStore()
const isIframe = useIsIframe()

// Derive everything from formManager
const state = computed(() => props.formManager.state)
const form = computed(() => props.formManager.config.value)
const formPageIndex = computed(() => props.formManager.state.currentPage)
const strategy = computed(() => props.formManager.strategy.value)
const structure = props.formManager.structure

// Slots/utilities
const slots = useSlots()

const hasPaymentBlock = computed(() => structure.value?.currentPageHasPaymentBlock?.value ?? false)

const currentFields = computed(() => structure.value?.getPageFields?.(state.value.currentPage) ?? [])

const isLastPage = computed(() => {
  const s = structure.value
  if (!s) return true
  if (s.isLastPage && 'value' in s.isLastPage) return s.isLastPage.value
  if (s.pageCount && 'value' in s.pageCount) {
    const count = s.pageCount.value || 0
    return state.value.currentPage >= Math.max(0, count - 1)
  }
  return true
})

const currentFieldsPageBreak = computed(() => structure.value?.currentPageBreak?.value ?? null)
const previousFieldsPageBreak = computed(() => structure.value?.previousPageBreak?.value ?? null)

const allowDragging = computed(() => strategy.value.admin.allowDragging)
const draggingNewBlock = computed(() => workingFormStore.draggingNewBlock)

const handlePreviousClick = () => {
  props.formManager.previousPage()
  if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
}

const handleNextClick = () => {
  props.formManager.nextPage().then(() => {
    if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
  })
}

const getAbsoluteIndex = (relativeIndex) => {
  return structure.value.getTargetDropIndex(relativeIndex, state.value.currentPage)
}

const handleDragAdd = (evt) => {
  if (!structure.value) return
  const targetIndex = getAbsoluteIndex(evt.newIndex)
  const payload = evt?.clonedData
  workingFormStore.addBlock(payload, targetIndex, false)
}

const handleDragUpdate = (evt) => {
  if (!structure.value) return
  const oldTargetIndex = getAbsoluteIndex(evt.oldIndex)
  const newTargetIndex = getAbsoluteIndex(evt.newIndex)
  if (oldTargetIndex !== newTargetIndex) {
    workingFormStore.moveField(oldTargetIndex, newTargetIndex)
  }
}

const isProcessing = computed(() => props.formManager.state.isProcessing)

// Renderer-level gates
const isSubmitted = computed(() => !!props.formManager?.state.isSubmitted)
const shouldDisplayForm = computed(() => {
  const showAdminControls = !!props.formManager?.strategy?.value?.admin?.showAdminControls
  return (!isSubmitted.value && !form.value?.is_closed && !form.value?.max_number_of_submissions_reached && !slots.password) || showAdminControls
})
const submittedData = computed(() => props.formManager?.form?.data?.() ?? null)

// Preview mode width override (FormEditorPreview uses PREVIEW mode)
const isPreviewMode = computed(() => props.formManager?.mode?.value === FormMode.PREVIEW)

const coverMedia = computed(() => ({
  url: form.value?.cover_picture,
  focal_point: form.value?.cover_settings?.focal_point,
  brightness: form.value?.cover_settings?.brightness
}))

// Hide logo/cover in READ_ONLY and EDIT modes
const showBrandingMedia = computed(() => {
  const mode = props.formManager?.mode?.value
  if (mode === FormMode.READ_ONLY || mode === FormMode.EDIT) return false
  return !!(form.value && (form.value.logo_picture || form.value.cover_picture))
})

const getFieldWidthClasses = (width) => {
  if (!width || width === 'full') return 'col-span-full'
  else if (width === '1/2') {
    return 'sm:col-span-6 col-span-full'
  } else if (width === '1/3') {
    return 'sm:col-span-4 col-span-full'
  } else if (width === '2/3') {
    return 'sm:col-span-8 col-span-full'
  } else if (width === '1/4') {
    return 'sm:col-span-3 col-span-full'
  } else if (width === '3/4') {
    return 'sm:col-span-9 col-span-full'
  }
  return 'col-span-full'
}

// Branding display control comes from strategy; default to true
const showBranding = computed(() => props.formManager?.strategy?.value?.display?.showBranding ?? true)
</script>

<style lang='scss' scoped>
.ghost-item {
  @apply bg-blue-100 dark:bg-blue-900 rounded-md;
}
</style>
