<template>
  <form ref="formElement" v-if="form" @submit.prevent="" class="@container w-full relative overflow-hidden flex flex-col min-h-full">
    <!-- Fixed fullscreen background from form cover -->
    <div v-if="form.cover_picture" class="absolute inset-0 pointer-events-none">
      <BlockMediaLayout :image="coverMedia" alt="Form cover image" />
    </div>

    <!-- Fixed logo in top-left -->
    <div v-if="form.logo_picture" class="absolute top-10 left-10 z-20">
      <img :src="form.logo_picture" :alt="form.seo_meta?.site_name ? `${form.seo_meta.site_name} logo` : 'Form logo'" class="size-8 md:size-16 object-contain">
    </div>

    <!-- Progressbar -->
    <FormProgressbar :form-manager="formManager" />

    <!-- Slide: question vertically centered, next/submit button slides with it -->
    <SlidingTransition 
      direction="vertical"
      :step="currentIndex"
      :auto-height="false"
      class="grow min-h-0 flex"
      :speed="500"
    >
      <!-- Password view (exclusive) -->
      <template v-if="$slots.password && form?.is_password_protected" key="password">
        <div key="pwd" class="w-full flex items-center px-6 grow min-h-0 z-10">
          <div class="w-full max-w-xl mx-auto p-4">
            <slot name="password" />
          </div>
        </div>
      </template>
      <!-- Alerts view (exclusive) -->
      <template v-else-if="$slots.alerts" key="alerts">
        <div key="alerts" class="w-full flex items-center px-6 grow min-h-0 z-10">
          <div class="w-full max-w-2xl mx-auto p-4">
            <slot name="alerts" />
          </div>
        </div>
      </template>
      <!-- After-submit view (exclusive) -->
      <template v-else-if="props.formManager?.state.isSubmitted && $slots['after-submit']" key="submitted">
        <div key="submitted" class="w-full flex items-center px-6 grow min-h-0 z-10">
          <div class="w-full max-w-2xl mx-auto p-4">
            <slot name="after-submit" :submittedData="props.formManager?.form?.data?.()" />
          </div>
        </div>
      </template>
      <component v-else :is="currentLayoutComponent" v-bind="currentLayoutProps" :key="currentIndex">
        <div class="relative">
          <BlockRenderer :block="currentBlock" :form-manager="formManager" @input-filled="onInputFilled" />
        </div>
        <div v-if="!props.formManager?.state.isSubmitted" class="mt-2 flex gap-2" :class="[getFieldAlignClasses(currentBlock), {'flex-col justify-normal! items-center': isLast &&form.use_captcha}]">
          <slot name="submit-btn" v-if="isLast" :loading="isProcessing">
            <CaptchaWrapper v-if="form.use_captcha" :form-manager="formManager" />
            <open-form-button 
              native-type="button" 
              :form="form" 
              class="mt-0.5 px-6" 
              :loading="isProcessing" 
              @click.prevent.stop="handleSubmitClick"
            >
              {{ form.submit_button_text || $t('forms.buttons.submit') }}
            </open-form-button>
          </slot>
          <open-form-button v-else native-type="button" :form="form" class="mt-0.5 px-6" :loading="isProcessing" @click.stop="handleNextClick">
            {{ form?.translations?.focused_next_button_text || $t('forms.buttons.next') }}
          </open-form-button>
        </div>
        <div v-if="hasPaymentBlock" class="mt-2">
          <p class="text-xs text-neutral-400 dark:text-neutral-500 max-w-md">
            {{ $t('forms.payment.payment_disclaimer') }}
          </p>
        </div>
      </component>
    </SlidingTransition>

    <!-- Cleanings slot -->
    <div class="fixed bottom-4 left-4 max-w-full z-10" v-if="$slots.cleanings">
      <div class="max-w-lg">
        <slot name="cleanings" />
      </div>
    </div>

    <!-- Bottom right controls: arrows and branding -->
    <div class="hidden sm:flex gap-2 fixed bottom-8 right-8 z-10" aria-label="Form controls">
      <!-- Focused nav arrows with fade transition -->
      <Transition name="fade" mode="out-in">
        <div v-if="shouldShowArrows && showArrowsOnCurrentPage" class="flex gap-2">
          <UButton color="form" square variant="solid" icon="i-heroicons-chevron-up-20-solid" :disabled="!canGoPrev" @click="goPrev" />
          <UButton color="form" square variant="solid" icon="i-heroicons-chevron-down-20-solid" :disabled="isLast" @click="goNext" />
        </div>
      </Transition>
      <!-- Branding button -->
      <PoweredBy v-if="!form.no_branding && showBranding" :color="form.color" />
    </div>
  </form>
</template>

<script setup>
import BlockRenderer from './BlockRenderer.vue'
import BlockMediaLayout from './components/BlockMediaLayout.vue'
import SideMediaSplit from './components/layouts/SideMediaSplit.vue'
import SideMediaSmall from './components/layouts/SideMediaSmall.vue'
import CenteredStep from './components/layouts/CenteredStep.vue'
import FormProgressbar from './FormProgressbar.vue'
import OpenFormButton from './OpenFormButton.vue'
import SlidingTransition from '../../global/transitions/SlidingTransition.vue'
import CaptchaWrapper from '~/components/forms/heavy/components/CaptchaWrapper.vue'
import { FormMode } from '~/lib/forms/FormModeStrategy.js'
import { useFormImagePreloader } from '~/composables/forms/useFormImagePreloader.js'
import PoweredBy from '~/components/pages/forms/show/PoweredBy.vue'

const props = defineProps({
  formManager: { type: Object, required: true }
})

const emit = defineEmits(['submit'])

const form = computed(() => props.formManager.config.value)
const structure = props.formManager.structure
const state = computed(() => props.formManager.state)
const isTemplateMode = computed(() => props.formManager?.mode?.value === FormMode.TEMPLATE)

const currentIndex = computed(() => state.value.currentPage)
const currentFields = computed(() => structure?.value?.getPageFields
  ? structure.value.getPageFields(currentIndex.value)
  : [])
const currentBlock = computed(() => currentFields.value?.[0] || null)
const currentMedia = computed(() => currentBlock.value?.image || null)

const isLast = computed(() => structure?.value?.isLastPage?.value ?? false)
const isProcessing = computed(() => props.formManager.state.isProcessing)
const hasPaymentBlock = computed(() => structure.value?.currentPageHasPaymentBlock?.value ?? false)

// Reserved for future gating if focused renderer wants to branch
// const isSubmitted = computed(() => !!props.formManager?.state.isSubmitted)
// const isPasswordProtected = computed(() => !!form.value?.is_password_protected)

const layoutName = computed(() => currentMedia.value?.layout || null)

// Lookup table for layout -> component + props
const layoutConfig = {
  'left-split': {
    component: SideMediaSplit,
    props: () => ({ image: currentMedia.value, side: 'left' })
  },
  'right-split': {
    component: SideMediaSplit,
    props: () => ({ image: currentMedia.value, side: 'right' })
  },
  'left-small': {
    component: SideMediaSmall,
    props: () => ({ image: currentMedia.value, side: 'left', borderRadius: borderRadius.value })
  },
  'right-small': {
    component: SideMediaSmall,
    props: () => ({ image: currentMedia.value, side: 'right', borderRadius: borderRadius.value })
  },
  'background': {
    component: CenteredStep,
    props: () => ({ background: currentMedia.value })
  }
}

// Single dynamic component + props for active layout
const currentLayoutComponent = computed(() => layoutConfig[layoutName.value]?.component || CenteredStep)
const currentLayoutProps = computed(() => layoutConfig[layoutName.value]?.props() || { background: null })

const handleNextClick = () => {
  props.formManager.nextPage().then((moved) => {
    if (moved && import.meta.client && !isTemplateMode.value) window.scrollTo({ top: 0, behavior: 'smooth' })
  })
}

const onInputFilled = () => {
  // On last page, submit the form instead of advancing
  if (isLast.value) {
    // Don't submit if already processing
    if (isProcessing.value) {
      return
    }
    emit('submit')
    return
  }
  
  // On non-last pages, advance to next page
  handleNextClick()
}

const coverMedia = computed(() => ({
  url: form.value?.cover_picture,
  focal_point: form.value?.cover_settings?.focal_point,
  brightness: form.value?.cover_settings?.brightness
}))

const borderRadius = computed(() => form.value?.border_radius || 'small')

// Preload images used by the form (cover/logo/blocks)
useFormImagePreloader(form, state)

// Auto-focus the input field after page transition
const formElement = ref(null)
const focusCurrentInput = () => {
  if (import.meta.server || isTemplateMode.value || !form.value?.auto_focus) return
  
  nextTick(() => {
    // Wait for transition to complete (500ms as defined in SlidingTransition)
    setTimeout(() => {
      // Find the first visible focusable input element in the current form
      if (!formElement.value) return
      
      const focusableSelectors = [
        'input:not([type="hidden"]):not([disabled])',
        'textarea:not([disabled])',
        'select:not([disabled])',
        'button[aria-haspopup="listbox"]:not([disabled])', // VSelect dropdown trigger
        '[contenteditable="true"]',
        'button[role="radio"]:not([disabled])',
        'button[role="checkbox"]:not([disabled])',
        '[role="listbox"][tabindex="0"]' // FocusedSelectorInput container
      ]
      
      const focusableElements = formElement.value.querySelectorAll(focusableSelectors.join(', '))
      
      // Find the first truly visible focusable element
      let firstVisible = null
      for (const element of focusableElements) {
        // Skip if element has hidden attribute
        if (element.hasAttribute('hidden')) continue
        
        // Skip if element has aria-hidden="true"
        if (element.getAttribute('aria-hidden') === 'true') continue
        
        // Check visibility: element is visible if it has layout (offsetParent) or client rects
        if (element.offsetParent !== null || element.getClientRects().length > 0) {
          firstVisible = element
          break
        }
      }
      
      if (firstVisible && typeof firstVisible.focus === 'function') {
        firstVisible.focus({ preventScroll: true })
      }
    }, 550) // Slightly longer than transition speed to ensure it's complete
  })
}

// Watch for page changes and auto-focus (only if auto_focus is enabled)
watch(currentIndex, () => {
  if (form.value?.auto_focus) {
    focusCurrentInput()
  }
})

// Focus on initial mount (only if auto_focus is enabled)
onMounted(() => {
  if (form.value?.auto_focus) {
    focusCurrentInput()
  }
})

// Slots/utilities
const slots = useSlots()

// Branding gating from strategy; defaults to true when not present
const showBranding = computed(() => props.formManager?.strategy?.value?.display?.showBranding ?? true)

// Focused arrows logic and gating
const showArrowsSetting = computed(() => (form.value?.settings?.navigation_arrows !== false))
const canGoPrev = computed(() => state.value.currentPage > 0)
const hasExclusiveView = computed(() => (
  !!(form.value?.is_password_protected && slots.password) ||
  !!slots.alerts ||
  (!!props.formManager?.state.isSubmitted && !!slots['after-submit'])
))
const shouldShowArrows = computed(() => showArrowsSetting.value && !hasExclusiveView.value)
const showArrowsOnCurrentPage = computed(() => {
  // Don't show arrows on first page (page 0) - only show when there are multiple pages and we're not on the first
  return state.value.currentPage > 0 || !isLast.value
})
const goPrev = () => { 
  if (canGoPrev.value && props.formManager?.previousPage) {
    try {
      const result = props.formManager.previousPage()
      if (result && typeof result.then === 'function') {
        result.then(() => {
          // Navigation successful
        }).catch(error => {
          console.warn('Error in previousPage:', error)
        }).finally(() => {
          if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
        })
      } else {
        // Synchronous result, scroll immediately
        if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
      }
    } catch (error) {
      console.warn('Error calling previousPage:', error)
      if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }
}
const handleSubmitClick = (event) => {
  // Prevent form submission if button is disabled/processing
  if (isProcessing.value) {
    event?.preventDefault()
    return
  }
  
  emit('submit')
}

const goNext = () => { if (!isLast.value) handleNextClick() }

// If block is text block, or any other block that has an align property, use the align property to determine the justify class
function getFieldAlignClasses(field) {
  if (!field?.align || field.align === 'left') return 'justify-start'
  else if (field.align === 'right') return 'justify-end'
  else if (field.align === 'center') return 'justify-center'
  else return 'justify-start'
}
</script>
