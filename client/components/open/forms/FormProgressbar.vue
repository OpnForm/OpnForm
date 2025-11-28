<template>
  <div v-if="showProgressBar" :class="wrapperClasses">
    <div class="w-full bg-neutral-200 dark:bg-neutral-600 relative overflow-hidden" :class="barContainerClasses">
      <div
        class="h-full transition-all duration-300"
        :class="[barFillClasses, { 'w-0': formProgress === 0 }]"
        :style="{ width: formProgress + '%', background: config?.color }"
      />
    </div>
  </div>
</template>

<script setup>
import { useIsIframe } from '~/composables/useIsIframe'

const props = defineProps({
  formManager: {
    type: Object,
    required: true
  }
})

const config = computed(() => props.formManager?.config.value)
const form = computed(() => props.formManager?.form)
const structure = props.formManager.structure
const state = computed(() => props.formManager?.state)

const showProgressBar = computed(() => {
  return config.value?.show_progress_bar
})

const isIframe = useIsIframe()
const isFocusedMode = computed(() => config.value?.presentation_style === 'focused')
const isSubmitted = computed(() => props.formManager?.state?.isSubmitted ?? false)

// Wrapper classes - different positioning for iframe vs standalone
const wrapperClasses = computed(() => {
  return isIframe.value 
    ? 'mb-4 p-2' 
    : 'fixed top-0 left-0 right-0 z-50'
})

// Bar container classes - border/rounded for iframe, height varies by mode
const barContainerClasses = computed(() => {
  const heightClass = isIframe.value
    ? (isFocusedMode.value ? 'h-1' : 'h-1')
    : (isFocusedMode.value ? 'h-1' : 'h-[0.2rem]')
  
  const borderClass = isIframe.value ? 'border rounded-full' : ''
  
  return [heightClass, borderClass].filter(Boolean).join(' ')
})

// Bar fill classes - rounded for iframe
const barFillClasses = computed(() => {
  return isIframe.value ? 'rounded-r-full' : ''
})

const formProgress = computed(() => {
  if (isFocusedMode.value) {
    // Focused mode: progress based on current page / total pages
    const currentPage = state.value?.currentPage ?? 0
    const totalPages = structure?.value?.pageCount?.value ?? 1
    
    if (totalPages === 0 || totalPages === 1) return 100
    
    // Show 100% after form submission (thank you page)
    if (isSubmitted.value) return 100
    
    // Progress shows how many pages have been completed (current page / total pages)
    // First page (0) = 0/n = 0%, last page (n-1) = (n-1)/n, after submit = n/n = 100%
    const progress = (currentPage / totalPages) * 100
    return Math.round(progress)
  } else {
    // Classic mode: progress based on completed required fields
    const allFields = config.value?.properties ?? []
    const requiredFields = allFields.filter(field => field?.required)
    
    if (requiredFields.length === 0) {
      return 100
    }
    
    const currentFormData = form.value.data() || {}
    const completedFields = requiredFields.filter(field => field && ![null, undefined, ''].includes(currentFormData[field.id]))
    const progress = (completedFields.length / requiredFields.length) * 100
    return Math.round(progress)
  }
})
</script> 