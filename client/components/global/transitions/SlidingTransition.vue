<template>
  <div class="sliding-wrapper" :style="{ '--transition-speed': transitionSpeed }">
    <transition :name="transitionName">
      <slot />
    </transition>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  direction: {
    type: String,
    default: 'horizontal',
    validator: (value) => ['vertical', 'horizontal'].includes(value)
  },
  step: {
    type: Number,
    default: 1
  },
  speed: {
    type: [Number, String],
    default: 800
  }
})

const previousStep = ref(props.step)
const transitionName = computed(() => {
  const baseTransition = props.direction === 'vertical' ? 'slide-vertical' : 'slide-horizontal'
  return `${baseTransition}-${props.step > previousStep.value ? 'forward' : 'backward'}`
})

const transitionSpeed = computed(() => {
  return typeof props.speed === 'number' ? `${props.speed}ms` : props.speed
})

watch(() => props.step, (newStep, oldStep) => {
  previousStep.value = oldStep
})

// No JS animation hooks; CSS handles both enter/leave transforms in sync
</script>

<style scoped>
.sliding-wrapper {
  position: relative;
  overflow: hidden;
}

/* Active: absolutely stack and animate transform + opacity */
:deep(.slide-horizontal-forward-enter-active),
:deep(.slide-horizontal-forward-leave-active),
:deep(.slide-horizontal-backward-enter-active),
:deep(.slide-horizontal-backward-leave-active),
:deep(.slide-vertical-forward-enter-active),
:deep(.slide-vertical-forward-leave-active),
:deep(.slide-vertical-backward-enter-active),
:deep(.slide-vertical-backward-leave-active) {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  transition: transform var(--transition-speed, 800ms) ease-in-out, opacity var(--transition-speed, 800ms) ease-in-out;
  will-change: transform, opacity;
}

/* Enter on top */
:deep(.slide-horizontal-forward-enter-active),
:deep(.slide-horizontal-backward-enter-active),
:deep(.slide-vertical-forward-enter-active),
:deep(.slide-vertical-backward-enter-active) {
  z-index: 2;
}
:deep(.slide-horizontal-forward-leave-active),
:deep(.slide-horizontal-backward-leave-active),
:deep(.slide-vertical-forward-leave-active),
:deep(.slide-vertical-backward-leave-active) {
  z-index: 1;
}

/* Horizontal */
:deep(.slide-horizontal-forward-enter-from),
:deep(.slide-horizontal-backward-leave-to) {
  transform: translateX(100%);
  opacity: 0.5;
}
:deep(.slide-horizontal-forward-enter-to),
:deep(.slide-horizontal-backward-leave-from) {
  transform: translateX(0%);
  opacity: 1;
}
:deep(.slide-horizontal-forward-leave-to),
:deep(.slide-horizontal-backward-enter-from) {
  transform: translateX(-100%);
  opacity: 0.5;
}
:deep(.slide-horizontal-forward-leave-from),
:deep(.slide-horizontal-backward-enter-to) {
  transform: translateX(0%);
  opacity: 1;
}

/* Vertical */
/* Forward: old goes up, new comes from bottom */
:deep(.slide-vertical-forward-enter-from) {
  transform: translateY(100%);
  opacity: 0.5;
}
:deep(.slide-vertical-forward-enter-to) {
  transform: translateY(0%);
  opacity: 1;
}
:deep(.slide-vertical-forward-leave-to) {
  transform: translateY(-100%);
  opacity: 0.5;
}
:deep(.slide-vertical-forward-leave-from) {
  transform: translateY(0%);
  opacity: 1;
}

/* Backward: old goes down, new comes from top */
:deep(.slide-vertical-backward-enter-from) {
  transform: translateY(-100%);
  opacity: 0.5;
}
:deep(.slide-vertical-backward-enter-to) {
  transform: translateY(0%);
  opacity: 1;
}
:deep(.slide-vertical-backward-leave-to) {
  transform: translateY(100%);
  opacity: 0.5;
}
:deep(.slide-vertical-backward-leave-from) {
  transform: translateY(0%);
  opacity: 1;
}
</style>