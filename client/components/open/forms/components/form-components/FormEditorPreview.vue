<template>
  <!-- Backdrop -->
  <div
    v-if="isExpanded"
    class="fixed inset-0 z-40 bg-white/30 dark:bg-neutral-900/30 backdrop-blur-xs"
    @click="toggleExpand"
  />

  <!--   Form Preview (desktop only)   -->
  <div
    class="form-editor-preview"
    ref="parent"
    :class="{
      'fixed inset-8 z-50 !flex': isExpanded,
      'bg-neutral-50 dark:bg-notion-dark-light hidden md:flex flex-grow p-4 flex-col items-center shadow-inner': !isExpanded
    }"
  >
    <div 
      class="border rounded-lg bg-white dark:bg-notion-dark w-full grow shadow-xs transition-all overflow-y-scroll flex flex-col"
      :class="{ 'h-full': isExpanded }"
    >
      <div class="w-full bg-white dark:bg-neutral-950 border-b border-neutral-300 dark:border-blue-900 dark:border-neutral-700 rounded-t-lg p-1.5 pl-4 pr-1.5 flex items-center gap-x-1.5">
        <div class="bg-red-500 rounded-full w-2.5 h-2.5" />
        <div class="bg-yellow-500 rounded-full w-2.5 h-2.5" />
        <div class="bg-green-500 rounded-full w-2.5 h-2.5" />
        <p class="text-sm text-neutral-500/70 text-sm ml-4 select-none">
          Form Preview
        </p>
        <div class="flex-grow" />
        <UButton
          v-if="previewFormSubmitted || (form && form.presentation_style === 'focused' && workingFormStore.formPageIndex !== 0)"
          icon="i-heroicons-arrow-path-rounded-square"
          color="neutral"
          variant="outline"
          size="xs"
          @click="restartForm"
        >
          Re-start
        </UButton>
        <TrackClick
            name="form_editor_toggle_expand"
            :properties="{toggle: !isExpanded}"
          >
        <UTooltip arrow :text="isExpanded ? 'Collapse' : 'Expand'">
         
            <UButton
              :icon="isExpanded ? 'i-heroicons-arrows-pointing-in' : 'i-heroicons-arrows-pointing-out'"
              color="neutral"
              variant="outline"
              size="xs"
              @click="toggleExpand"
            />
        </UTooltip>
      </TrackClick>
      </div>
      <div class="flex-grow overflow-y-auto relative flex flex-col transform-gpu">
        <!-- The transform creates a containing block so descendants with position: fixed
             are anchored to this preview container instead of the page viewport. -->
        <open-complete-form
          ref="formPreview"
          class="w-full grow min-h-0"
          :form="form"
          :dark-mode="darkMode"
          :mode="formMode"
          @restarted="previewFormSubmitted=false"
          @submitted="previewFormSubmitted=true"
        />
        <!-- Quick actions for focused presentation (only when not expanded) -->
         
        <VTransition name="fade">
          <div
            v-if="!isExpanded && form && form.presentation_style === 'focused'"
            class="absolute top-2 right-2 z-20 flex items-center gap-2"
          >
            <UTooltip
              text="Add block"
              :kbds="['meta', 'B']"
              arrow
            >
              <UButton
                icon="i-heroicons-plus"
                color="neutral"
                variant="outline"
                size="sm"
                @click.stop="handleAddBlock"
              />
            </UTooltip>
            <UButtonGroup size="sm">
              <UTooltip text="Edit question">
                <UButton
                  icon="i-heroicons-cog-6-tooth"
                  color="neutral"
                  variant="outline"
                  label="Edit"
                  @click.stop="handleEditCurrent"
                />
              </UTooltip>
              <UDropdownMenu :items="moreMenuItems" :content="{ align: 'end' }">
                <UButton
                  color="neutral"
                  variant="outline"
                  icon="i-heroicons-ellipsis-vertical"
                  @click.stop
                />
              </UDropdownMenu>
            </UButtonGroup>
          </div>
        </VTransition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import OpenCompleteForm from '../../OpenCompleteForm.vue'
import {handleDarkMode, useDarkMode} from "~/lib/forms/public-page.js"
import { useWorkingFormStore } from '~/stores/working_form'
import { storeToRefs } from 'pinia'
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import { useCrisp } from '~/composables/useCrisp.js'
import TrackClick from '~/components/global/TrackClick.vue'

const { hideChat, showChat } = useCrisp()

const workingFormStore = useWorkingFormStore()

const parent = ref(null)
const formPreview = ref(null)
const previewFormSubmitted = ref(false)
const isExpanded = ref(false)

watch(isExpanded, (expanded) => {
  if (expanded)
    hideChat()
  else
    showChat()
})

const { content: form } = storeToRefs(workingFormStore)
const darkMode = useDarkMode(parent)

// Use PREVIEW mode when not expanded, TEST mode when expanded
const formMode = computed(() => isExpanded.value ? FormMode.TEST : FormMode.PREVIEW)

defineShortcuts({
  escape: {
    usingInput: true,
    handler: () => {
      if (isExpanded.value) {
        isExpanded.value = false
      }
    }
  }
})

watch(() => form.value.dark_mode, () => {
  handleDarkModeChange()
})

// Watch for form mode changes to reset the form when switching modes
watch(formMode, () => {
  if (previewFormSubmitted.value) {
    restartForm()
  }
})

onMounted(() => {
  handleDarkModeChange()
})

function handleDarkModeChange() {
  handleDarkMode(form.value.dark_mode, parent.value)
}

function restartForm() {
  previewFormSubmitted.value = false
  
  try {
    // Try using the component reference first
    if (formPreview.value && typeof formPreview.value.restart === 'function') {
      formPreview.value.restart()
      return
    }
  } catch (error) {
    console.error('Error restarting form:', error)
  }
}

function toggleExpand() {
  isExpanded.value = !isExpanded.value
}

// Helpers to get current focused slide index
const currentSlideIndex = computed(() => {
  try {
    // Prefer structure service from store if available
    const struct = workingFormStore.structureService
    if (struct && struct.currentPage !== undefined) {
      const cp = struct.currentPage
      return (typeof cp === 'number') ? cp : (cp?.value ?? 0)
    }
    // Fallback to formPreview's formManager
    return formPreview.value?.formManager?.state?.currentPage ?? 0
  } catch {
    return 0
  }
})

// Shared guards/helpers
const isFocusedEditing = computed(() => {
  return !!(
    form.value &&
    form.value.presentation_style === 'focused' &&
    workingFormStore.showEditFieldSidebar
  )
})

function isValidIndex(index) {
  const total = (workingFormStore.content?.properties?.length) || 0
  return typeof index === 'number' && index >= 0 && index < total
}

// Helper to get the absolute property index from a visible page index in focused mode
function getAbsoluteIndexFromVisiblePage(visiblePageIndex) {
  const struct = workingFormStore.structureService
  if (!struct || typeof struct.getPageFields !== 'function') return visiblePageIndex
  
  const pageFields = struct.getPageFields(visiblePageIndex)
  if (!pageFields || pageFields.length === 0) return visiblePageIndex
  
  const field = pageFields[0]
  const properties = workingFormStore.content?.properties || []
  const absoluteIndex = properties.findIndex(p => p?.nf_id === field?.nf_id)
  
  return absoluteIndex >= 0 ? absoluteIndex : visiblePageIndex
}

// Sync selected field with current page in focused mode while editing
watch(() => currentSlideIndex.value, (newIndex) => {
  try {
    if (!isFocusedEditing.value) return
    
    // Convert visible page index to absolute property index
    const absoluteIndex = getAbsoluteIndexFromVisiblePage(newIndex)
    
    if (!isValidIndex(absoluteIndex) || workingFormStore.selectedFieldIndex === absoluteIndex) return
    
    // Skip if the field at this index is hidden
    const field = workingFormStore.content?.properties?.[absoluteIndex]
    const struct = workingFormStore.structureService
    if (field && struct && typeof struct.isFieldHidden === 'function' && struct.isFieldHidden(field)) {
      return
    }
    // Update the selected field to follow the currently focused slide
    workingFormStore.setEditingField(absoluteIndex)
  } catch (e) {
    console.error(e)
  }
})

// Also keep the preview page aligned when selection changes externally
watch(() => workingFormStore.selectedFieldIndex, (newIndex) => {
  try {
    if (isFocusedEditing.value && isValidIndex(newIndex)) {
      // Skip if the selected field is hidden
      const field = workingFormStore.content?.properties?.[newIndex]
      const struct = workingFormStore.structureService
      if (field && struct && typeof struct.isFieldHidden === 'function' && struct.isFieldHidden(field)) {
        return
      }
      if (struct && typeof struct.setPageForField === 'function') {
        struct.setPageForField(newIndex)
      }
    }
  } catch (e) {
    console.error(e)
  }
})

function handleAddBlock() {
  try {
    workingFormStore.activeTab = 'build'
    const absoluteIndex = getAbsoluteIndexFromVisiblePage(currentSlideIndex.value)
    workingFormStore.openAddFieldSidebar(absoluteIndex)
  } catch (e) {
    console.error(e)
  }
}

function handleEditCurrent() {
  try {
    workingFormStore.activeTab = 'build'
    const absoluteIndex = getAbsoluteIndexFromVisiblePage(currentSlideIndex.value)
    workingFormStore.openSettingsForField(absoluteIndex, true)
  } catch (e) {
    console.error(e)
  }
}

// Dropdown items and actions for current slide
const moreMenuItems = computed(() => ([
  [
    {
      label: 'Duplicate',
      icon: 'i-heroicons-document-duplicate-20-solid',
      onClick: handleDuplicateCurrent
    }
  ],
  [
    {
      label: 'Delete',
      icon: 'i-heroicons-trash-20-solid',
      color: 'error',
      onClick: handleDeleteCurrent
    }
  ]
]))

function handleDuplicateCurrent() {
  const index = getAbsoluteIndexFromVisiblePage(currentSlideIndex.value)
  workingFormStore.duplicateField(index)
}

function handleDeleteCurrent() {
  const index = getAbsoluteIndexFromVisiblePage(currentSlideIndex.value)
  workingFormStore.removeField(index)
}
</script>

<style scoped>
.fixed {
  transition: all 0.3s ease-in-out;
}
</style>

<style>
@reference '~/css/app.css';

.form-editor-preview .powered-by-button {
  @apply bottom-10 right-10 z-50;
}
</style>