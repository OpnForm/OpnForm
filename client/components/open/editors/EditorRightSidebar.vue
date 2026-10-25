<template>
  <transition name="slide-right">
    <div
      v-if="show"
      ref="elementRef"
      class="absolute shadow-lg shadow-neutral-800/30 top-0 h-[calc(100vh-55px)] right-0 lg:shadow-none lg:relative bg-white border-l flex-shrink-0 z-30"
      :class="[
        isResizable ? '' : 'w-full md:w-1/2 lg:w-2/5',
        widthClass
      ]"
      :style="isResizable ? dynamicStyles : {}"
    >
      <ResizeHandle
        :show="isResizable"  
        direction="right"
        @start-resize="startResize"
      />
      
      <OverlayScrollbarsComponent
        defer
        class="h-full min-h-0"
      >
        <slot />
      </OverlayScrollbarsComponent>
    </div>
  </transition>
</template>

<script setup>
import { computed } from 'vue'
import { useResizable } from '~/composables/components/useResizable'
import ResizeHandle from '@/components/global/ResizeHandle.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  widthClass: {
    type: String,
    default: 'md:max-w-[20rem]',
  },
  resizable: {
    type: Boolean,
    default: false,
  },
})

// Sidebar resizing using composable
const { 
  elementRef, 
  isResizable: isResizableBase, 
  dynamicStyles, 
  startResize
} = useResizable({
  storageKey: 'formEditorRightSidebarWidth',
  defaultWidth: 315,
  direction: 'right',
  maxWidth: () => Math.min(600, window.innerWidth * 0.6)
})

// Enable resizing only when prop is true and breakpoint allows it
const isResizable = computed(() => props.resizable && isResizableBase.value)
</script>

<style scoped>
.slide-right-enter-active,
.slide-right-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
