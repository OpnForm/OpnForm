<template>
  <UModal
    v-model:open="isOpen"
    :ui="{
      content: 'max-w-5xl h-[80vh] overflow-hidden',
    }"
    title=""
    :content="{
      onPointerDownOutside: (event) => { if (event.target?.closest('.crisp-client')) {return event.preventDefault()}}
    }"
  >
    <template #content>
      <div class="flex h-full flex-col sm:flex-row">
        <!-- Left Sidebar -->
        <aside class="flex flex-col border-b border-neutral-200 bg-neutral-50 sm:w-56 sm:shrink-0 sm:border-r sm:border-b-0">
          <!-- Navigation Menu -->
          <nav class="relative p-2 pt-2 sm:flex-1 sm:overflow-y-auto sm:pt-4">
            <slot name="nav-top" />
            <div class="relative">
              <!-- Left Arrow -->
              <VTransition name="fade">
                <UButton
                  v-if="showLeftArrow"
                  variant="ghost"
                  color="neutral"
                  icon="i-heroicons-chevron-left"
                  class="absolute -left-px top-0 z-10 h-full bg-gradient-to-r from-neutral-50 via-neutral-50 to-transparent"
                  :ui="{ rounded: 'rounded-none', padding: { sm: 'px-4' } }"
                  @click="scrollNav(-1)"
                />
              </VTransition>

              <NavigationList
                ref="navContainer"
                :items="registeredPages.map(page => ({ ...createNavItem(page), id: page.id }))"
                :tracking-enabled="false"
                button-class="justify-start whitespace-nowrap !w-auto sm:!w-full"
                list-class="flex flex-row space-x-1 overflow-x-auto sm:flex-col sm:space-y-1 sm:space-x-0"
                @item-click="(item) => setActiveItem(item.id)"
              />

              <!-- Right Arrow -->
              <VTransition name="fade">
                <UButton
                  v-if="showRightArrow"
                  variant="ghost"
                  color="neutral"
                  icon="i-heroicons-chevron-right"
                  class="absolute -right-px top-0 z-10 h-full bg-gradient-to-l from-neutral-50 via-neutral-50 to-transparent"
                  :ui="{ rounded: 'rounded-none', padding: { sm: 'px-4' } }"
                  @click="scrollNav(1)"
                />
              </VTransition>
            </div>
          </nav>
        </aside>

        <!-- Main Content -->
        <main class="relative flex flex-1 flex-col overflow-hidden">
          <!-- Content Body -->
          <div class="flex-1 overflow-y-auto">
            <div class="p-6">
              <!-- Modal pages will register themselves and render here -->
              <slot />

              <!-- Default content if no pages registered -->
              <div v-if="registeredPages.length === 0" class="py-12 text-center">
                <UIcon
                  name="i-heroicons-cog-6-tooth"
                  class="mx-auto mb-4 h-12 w-12 text-neutral-400"
                />
                <h3 class="mb-2 text-lg font-medium text-neutral-900">
                  Select a setting
                </h3>
                <p class="text-neutral-500">
                  Choose an option from the sidebar to configure your settings.
                </p>
              </div>
            </div>
          </div>
        </main>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { useScroll, useResizeObserver } from '@vueuse/core'
import { nextTick, ref, computed, watch, provide } from 'vue'
import VTransition from '@/components/global/transitions/VTransition.vue'
import NavigationList from '~/components/global/NavigationList.vue'

const emit = defineEmits(['close', 'item-changed', 'update:activeTab'])

// Registered pages for auto-registration
const registeredPages = ref([])

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  activeTab: {
    type: String,
    default: null
  }
})

// Modal state
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('close', value)
})

// Get first item ID from menu sections
function getFirstItemId() {
  if (registeredPages.value.length > 0) {
    return registeredPages.value[0].id
  }
  return null
}

// Reactive reference for the currently active tab
const activeTabRef = ref(props.activeTab)

// --- Responsive Nav Scrolling ---
const navContainer = ref(null)
const { x: scrollX } = useScroll(navContainer)
const contentWidth = ref(0)
const containerWidth = ref(0)

const showLeftArrow = computed(() => scrollX.value > 0)
const showRightArrow = computed(() => {
  if (containerWidth.value === 0 || contentWidth.value === 0) return false
  return scrollX.value < contentWidth.value - containerWidth.value - 1 // -1 for subpixel precision
})

useResizeObserver(navContainer, (entries) => {
  const entry = entries[0]
  containerWidth.value = entry.contentRect.width
  if (navContainer.value) {
    contentWidth.value = navContainer.value.scrollWidth
  }
})

function scrollNav(direction) {
  if (navContainer.value) {
    // Scroll by 80% of the container's width for a better user experience
    const scrollAmount = containerWidth.value * 0.8 * direction
    navContainer.value.scrollBy({ left: scrollAmount, behavior: 'smooth' })
  }
}

// Keep local ref in sync with incoming prop
watch(() => props.activeTab, (newVal) => {
  activeTabRef.value = newVal
})

// Default button configuration
const defaultButtonProps = {
  variant: 'ghost',
  activeVariant: 'soft', 
  color: 'neutral',
  block: true,
}

// Helper function to apply defaults to navigation items
const createNavItem = (item) => {
  const baseItem = {
    ...defaultButtonProps,
    label: item.label,
    icon: item.icon,
    active: activeTabRef.value === item.id
  }
  
  // Add custom classes to darken ghost/soft variants for better visibility on neutral-50 background
  const customClasses = ['group']
  
  // For ghost variant (default), darken hover state
  if (baseItem.variant === 'ghost' && baseItem.color === 'neutral') {
    customClasses.push('hover:bg-neutral-200/80')
    baseItem.ui = {
      leadingIcon: 'text-neutral-400 group-hover:text-neutral-500'
    }
  }
  
  // For soft variant (active state), darken background
  if (baseItem.active && baseItem.activeVariant === 'soft' && baseItem.color === 'neutral') {
    customClasses.push('bg-neutral-200/90 text-neutral-800')
  }
  
  return {
    ...baseItem,
    class: customClasses.length > 0 ? customClasses.join(' ') : undefined
  }
}

// Set active item
const setActiveItem = (itemId) => {
  activeTabRef.value = itemId
  emit('item-changed', itemId)
  emit('update:activeTab', itemId)
}

// Reset to default item only when modal opens
watch(isOpen, (newValue) => {
  if (newValue) {
    // Use nextTick to allow child pages to register themselves first, fixing the race condition.
    nextTick(() => {
      const isValidPropTab = props.activeTab && registeredPages.value.some(p => p.id === props.activeTab)

      if (isValidPropTab) {
        // If the prop provides a valid tab, use it.
        activeTabRef.value = props.activeTab
      } else {
        // Otherwise, fallback to the first item if the current selection is invalid.
        const isCurrentTabValid = registeredPages.value.some(p => p.id === activeTabRef.value)
        if (!isCurrentTabValid) {
          activeTabRef.value = getFirstItemId()
        }
      }
    })
  }
})

// Ensure activeTabRef stays in sync with registered pages
watch(registeredPages, () => {
  // If prop specifies a valid tab and it's not already active, set it
  if (props.activeTab && props.activeTab !== activeTabRef.value && registeredPages.value.some(p => p.id === props.activeTab)) {
    activeTabRef.value = props.activeTab
    return
  }

  // If current active item has been removed, fall back to first available
  if (!registeredPages.value.some(p => p.id === activeTabRef.value)) {
    activeTabRef.value = getFirstItemId()
  }
}, { deep: true })

// Registration functions for modal pages
function registerModalPage(id, label, icon) {
  const existingIndex = registeredPages.value.findIndex(page => page.id === id)
  if (existingIndex !== -1) {
    registeredPages.value[existingIndex] = { id, label, icon }
  } else {
    registeredPages.value.push({ id, label, icon })
    if (registeredPages.value.length === 1) {
      activeTabRef.value = id
    }
  }
}

function unregisterModalPage(id) {
  const index = registeredPages.value.findIndex(page => page.id === id)
  if (index !== -1) {
    registeredPages.value.splice(index, 1)
    // If the page being removed was the active one, select a new one.
    if (activeTabRef.value === id) {
      if (registeredPages.value.length > 0) {
        setActiveItem(registeredPages.value[0].id)
      } else {
        activeTabRef.value = null
      }
    }
  }
}

// Provide functions for child components (after they're defined)
provide('activeModalItem', activeTabRef)
provide('registerModalPage', registerModalPage)
provide('unregisterModalPage', unregisterModalPage)
</script>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
ul::-webkit-scrollbar {
  display: none;
}
</style> 