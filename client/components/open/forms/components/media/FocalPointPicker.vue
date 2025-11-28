<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div
      ref="container"
      class="relative inline-block overflow-hidden rounded-md border select-none"
      @mousedown.prevent="startDrag"
      @touchstart.prevent="startDrag"
      @mousemove.prevent="onMoveIfDragging"
      @touchmove.prevent="onTouchMove"
      @mouseup.prevent="endDrag"
      @mouseleave="endDrag"
      @touchend.prevent="endDrag"
      @click.prevent="onClickSet"
    >
      <img :src="src" class="block max-w-full h-auto select-none" draggable="false" @dragstart.prevent />
      <button
        class="absolute -translate-x-1/2 -translate-y-1/2 w-6 h-6 rounded-full border-2 border-white bg-neutral-700/80 shadow"
        :style="{ left: x + '%', top: y + '%' }"
        aria-label="Focal point"
      />
    </div>

    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script setup>
import InputWrapper from '~/components/forms/core/components/InputWrapper.vue'
import { inputProps, useFormInput } from '~/components/forms/useFormInput.js'

const props = defineProps({
  ...inputProps,
  modelValue: { type: Object, default: () => ({ x: 50, y: 50 }) },
  src: { type: String, required: true }
})
const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

const { compVal, inputWrapperProps } = useFormInput(props, { emit })

const container = ref(null)

const safePoint = (val, _axis = 'x') => {
  const num = typeof val === 'number' ? val : 50
  return Math.max(0, Math.min(100, num))
}

const x = computed(() => safePoint((compVal.value && compVal.value.x) ?? props.modelValue?.x))
const y = computed(() => safePoint((compVal.value && compVal.value.y) ?? props.modelValue?.y, 'y'))

let dragging = false

function setFromEvent(e) {
  const root = container.value
  if (!root) return
  const img = root.querySelector('img')
  if (!img) return
  const rect = img.getBoundingClientRect()
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  const nx = ((clientX - rect.left) / rect.width) * 100
  const ny = ((clientY - rect.top) / rect.height) * 100
  compVal.value = { x: safePoint(nx), y: safePoint(ny, 'y') }
}

function onMoveIfDragging(e) {
  if (!dragging) return
  setFromEvent(e)
}

function onTouchMove(e) {
  if (!dragging) return
  setFromEvent(e)
}

function startDrag(_e) {
  dragging = true
}

function endDrag() {
  dragging = false
}

function onClickSet(e) {
  setFromEvent(e)
}
</script>


