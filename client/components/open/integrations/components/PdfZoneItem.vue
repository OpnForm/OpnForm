<template>
  <div
    class="absolute border-2 cursor-move transition-colors"
    :class="{
      'border-blue-500 bg-blue-100/40': selected,
      'border-green-500 bg-green-100/30 hover:border-green-600': !selected
    }"
    :style="zoneStyle"
    @mousedown.stop="onMouseDown"
    @click.stop="$emit('select')"
  >
    <!-- Zone label -->
    <div
      class="absolute -top-6 left-0 px-2 py-0.5 text-xs font-medium rounded whitespace-nowrap"
      :class="{
        'bg-blue-500 text-white': selected,
        'bg-green-500 text-white': !selected
      }"
    >
      {{ fieldLabel }}
    </div>

    <!-- Resize handles (only when selected) -->
    <template v-if="selected">
      <div
        class="absolute w-3 h-3 bg-blue-500 rounded-full cursor-nwse-resize -top-1.5 -left-1.5"
        @mousedown.stop="startResize('nw', $event)"
      />
      <div
        class="absolute w-3 h-3 bg-blue-500 rounded-full cursor-nesw-resize -top-1.5 -right-1.5"
        @mousedown.stop="startResize('ne', $event)"
      />
      <div
        class="absolute w-3 h-3 bg-blue-500 rounded-full cursor-nesw-resize -bottom-1.5 -left-1.5"
        @mousedown.stop="startResize('sw', $event)"
      />
      <div
        class="absolute w-3 h-3 bg-blue-500 rounded-full cursor-nwse-resize -bottom-1.5 -right-1.5"
        @mousedown.stop="startResize('se', $event)"
      />
    </template>

    <!-- Delete button -->
    <UButton
      v-if="selected"
      class="absolute -top-6 -right-1 h-6 w-6"
      color="error"
      variant="solid"
      size="xs"
      icon="i-heroicons-trash"
      @click.stop="$emit('delete')"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  zone: { type: Object, required: true },
  form: { type: Object, required: true },
  scale: { type: Number, default: 1 },
  selected: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'update', 'delete'])

// Get field label
const fieldLabel = computed(() => {
  if (!props.zone.field_id) return 'No field selected'
  
  // Check special fields
  const specialFields = {
    submission_id: 'Submission ID',
    submission_date: 'Submission Date',
    form_name: 'Form Name'
  }
  
  if (specialFields[props.zone.field_id]) {
    return specialFields[props.zone.field_id]
  }
  
  // Find field in form
  const field = props.form?.properties?.find(p => p.id === props.zone.field_id)
  return field?.name || props.zone.field_id
})

// Zone style (position and size as percentages)
const zoneStyle = computed(() => ({
  left: `${props.zone.x}%`,
  top: `${props.zone.y}%`,
  width: `${props.zone.width}%`,
  height: `${props.zone.height}%`
}))

// Drag state
let isDragging = false
let dragStart = { x: 0, y: 0 }
let initialPos = { x: 0, y: 0 }

// Resize state
let isResizing = false
let resizeCorner = ''
let resizeStart = { x: 0, y: 0 }
let initialZone = { x: 0, y: 0, width: 0, height: 0 }

// Get parent container dimensions
const getParentDimensions = () => {
  const parent = document.querySelector('.pdf-zone-editor .relative')
  if (!parent) return { width: 1, height: 1 }
  return {
    width: parent.clientWidth,
    height: parent.clientHeight
  }
}

// Start dragging
const onMouseDown = (event) => {
  if (isResizing) return
  
  isDragging = true
  dragStart = { x: event.clientX, y: event.clientY }
  initialPos = { x: props.zone.x, y: props.zone.y }
  
  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

// Drag movement
const onMouseMove = (event) => {
  if (isDragging) {
    const parent = getParentDimensions()
    const dx = ((event.clientX - dragStart.x) / parent.width) * 100
    const dy = ((event.clientY - dragStart.y) / parent.height) * 100
    
    const newX = Math.max(0, Math.min(100 - props.zone.width, initialPos.x + dx))
    const newY = Math.max(0, Math.min(100 - props.zone.height, initialPos.y + dy))
    
    emit('update', {
      ...props.zone,
      x: newX,
      y: newY
    })
  } else if (isResizing) {
    handleResize(event)
  }
}

// End drag/resize
const onMouseUp = () => {
  isDragging = false
  isResizing = false
  document.removeEventListener('mousemove', onMouseMove)
  document.removeEventListener('mouseup', onMouseUp)
}

// Start resize
const startResize = (corner, event) => {
  isResizing = true
  resizeCorner = corner
  resizeStart = { x: event.clientX, y: event.clientY }
  initialZone = {
    x: props.zone.x,
    y: props.zone.y,
    width: props.zone.width,
    height: props.zone.height
  }
  
  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

// Handle resize
const handleResize = (event) => {
  const parent = getParentDimensions()
  const dx = ((event.clientX - resizeStart.x) / parent.width) * 100
  const dy = ((event.clientY - resizeStart.y) / parent.height) * 100
  
  let newZone = { ...props.zone }
  
  switch (resizeCorner) {
    case 'se':
      newZone.width = Math.max(5, initialZone.width + dx)
      newZone.height = Math.max(5, initialZone.height + dy)
      break
    case 'sw':
      newZone.x = Math.max(0, initialZone.x + dx)
      newZone.width = Math.max(5, initialZone.width - dx)
      newZone.height = Math.max(5, initialZone.height + dy)
      break
    case 'ne':
      newZone.y = Math.max(0, initialZone.y + dy)
      newZone.width = Math.max(5, initialZone.width + dx)
      newZone.height = Math.max(5, initialZone.height - dy)
      break
    case 'nw':
      newZone.x = Math.max(0, initialZone.x + dx)
      newZone.y = Math.max(0, initialZone.y + dy)
      newZone.width = Math.max(5, initialZone.width - dx)
      newZone.height = Math.max(5, initialZone.height - dy)
      break
  }
  
  // Clamp to bounds
  newZone.x = Math.min(newZone.x, 100 - newZone.width)
  newZone.y = Math.min(newZone.y, 100 - newZone.height)
  
  emit('update', newZone)
}
</script>
