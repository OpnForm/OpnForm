<!--
  NavigationList Component
  
  A reusable component for rendering navigation items with keyboard shortcuts, 
  tracking, and loading states.
  
  Usage:
  <NavigationList
    :items="navigationItems"
    :loading="isLoading"
    :skeleton-filter="(item) => item.showSkeleton"
    tracking-name="sidebar_nav_click"
    :tracking-properties="(item) => ({ label: item.label })"
    button-class="w-full justify-start"
    @item-click="handleItemClick"
  />
  
  Item Structure:
  {
    label: string,
    icon?: string,
    to?: object,
    active?: boolean,
    kbd?: string[],
    onClick?: function,
    ...buttonProps
  }
-->
<template>
  <ul 
    ref="listRef"
    :class="listClass"
    :style="listClass.includes('overflow-x-auto') ? 'scrollbar-width: none; -ms-overflow-style: none;' : ''"
  >
    <li v-for="item in items" :key="item.label || item.id">
      <!-- Loading skeleton -->
      <USkeleton 
        v-if="loading && shouldShowSkeleton(item)"
        class="h-9 w-full rounded-md"
      />
      
      <!-- Regular navigation item -->
      <component 
        v-else
        :is="trackingEnabled ? TrackClick : 'div'"
        :name="trackingName"
        :properties="getTrackingProperties(item)"
      >
        <UButton
          v-bind="getButtonProps(item)"
          :class="buttonClass"
          @click="handleClick(item)"
        >
          <template #trailing v-if="item.kbd">
            <span class="hidden sm:flex ml-auto">
              <UKbd v-for="kbd in item.kbd" :key="kbd" :value="kbd" />
            </span>
          </template>
        </UButton>
      </component>
    </li>
  </ul>
</template>

<script setup>
import TrackClick from '~/components/global/TrackClick.vue'

// Expose the root element for parent components to use with scroll/resize observers
const listRef = ref(null)
defineExpose({
  $el: listRef
})

const props = defineProps({
  items: {
    type: Array,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  skeletonFilter: {
    type: Function,
    default: null
  },
  trackingEnabled: {
    type: Boolean,
    default: true
  },
  trackingName: {
    type: String,
    default: 'navigation_click'
  },
  trackingProperties: {
    type: [Object, Function],
    default: () => ({})
  },
  buttonClass: {
    type: String,
    default: 'w-full justify-start'
  },
  listClass: {
    type: String,
    default: 'space-y-1'
  }
})

const emit = defineEmits(['item-click'])

// Setup keyboard shortcuts
const shortcuts = computed(() => {
  const shortcutMap = {}
  
  props.items.forEach(item => {
    if (item.kbd && item.kbd.length > 0) {
      // Handle single key shortcuts (like 'n') and combinations
      const shortcut = item.kbd.join('').toLowerCase()
      shortcutMap[shortcut] = {
        handler: () => handleClick(item)
      }
    }
  })
  
  return shortcutMap
})

// Register shortcuts
defineShortcuts(shortcuts)

function shouldShowSkeleton(item) {
  if (typeof props.skeletonFilter === 'function') {
    return props.skeletonFilter(item)
  }
  return true
}

function getTrackingProperties(item) {
  if (typeof props.trackingProperties === 'function') {
    return props.trackingProperties(item)
  }
  return {
    label: item.label,
    ...props.trackingProperties
  }
}

function getButtonProps(item) {
  // Create a copy of the item without the onClick handler
  // eslint-disable-next-line no-unused-vars
  const { onClick, ...buttonProps } = item
  return buttonProps
}

function handleClick(item) {
  // Call the item's onClick handler if it exists
  if (typeof item.onClick === 'function') {
    item.onClick()
  }
  // Handle navigation for items with 'to' property
  else if (item.to) {
    // Use navigateTo for internal navigation
    navigateTo(item.to)
  }
  // Handle external links
  else if (item.href) {
    window.open(item.href, item.target || '_self')
  }
  
  // Emit the click event for parent components
  emit('item-click', item)
}
</script>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
ul::-webkit-scrollbar {
  display: none;
}
</style> 