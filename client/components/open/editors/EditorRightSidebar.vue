<template>
  <transition @leave="(el, done) => sidebarMotion?.leave(done)">
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
      
      <div class="h-full overflow-y-auto">
        <slot />
      </div>
    </div>
  </transition>
</template>

<script setup>
import { slideRight, useMotion } from '@vueuse/motion'
import { watch, computed } from 'vue'
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

// Motion animation
const sidebarMotion = ref(null)

// Enable resizing only when prop is true and breakpoint allows it
const isResizable = computed(() => props.resizable && isResizableBase.value)

// Watch for show prop changes (existing functionality)  
watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      nextTick(() => {
        sidebarMotion.value = useMotion(elementRef.value, slideRight)
      })
    }
  },
)
</script>
