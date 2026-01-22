<template>
  <div class="pdf-zone-editor space-y-4">
    <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_24rem] gap-6">
      <div class="space-y-4">
        <!-- Page Navigation -->
        <div
          v-if="template.page_count > 1"
          class="flex items-center justify-center gap-4"
        >
          <UButton
            icon="i-heroicons-chevron-left"
            variant="ghost"
            :disabled="currentPage === 1"
            @click="currentPage--"
          />
          <span class="text-sm text-gray-600">
            Page {{ currentPage }} of {{ template.page_count }}
          </span>
          <UButton
            icon="i-heroicons-chevron-right"
            variant="ghost"
            :disabled="currentPage === template.page_count"
            @click="currentPage++"
          />
        </div>

        <!-- PDF Canvas with Zones -->
        <div
          ref="editorContainer"
          class="relative border border-gray-200 rounded-lg overflow-hidden bg-gray-100"
          :style="{ minHeight: '520px' }"
        >
          <!-- PDF Canvas (rendered by pdf.js) -->
          <canvas
            ref="pdfCanvas"
            class="block mx-auto"
            @mousedown="onMouseDown"
            @mousemove="onMouseMove"
            @mouseup="onMouseUp"
          />

          <!-- Existing Zones -->
          <PdfZoneItem
            v-for="zone in currentPageZones"
            :key="zone.id"
            :zone="zone"
            :form="form"
            :scale="scale"
            :selected="selectedZoneId === zone.id"
            @select="selectZone(zone.id)"
            @update="updateZone"
            @delete="deleteZone(zone.id)"
          />

          <!-- Drawing Zone (while creating) -->
          <div
            v-if="isDrawing && drawingRect"
            class="absolute border-2 border-blue-500 bg-blue-100/30 pointer-events-none"
            :style="drawingRectStyle"
          />

          <!-- Loading overlay -->
          <div
            v-if="pdfLoading"
            class="absolute inset-0 flex items-center justify-center bg-white/80"
          >
            <Loader class="h-8 w-8 text-blue-600" />
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <!-- Add Zone Panel -->
        <div class="sticky top-6 space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-gray-800">Add Zone</p>
              <p class="text-xs text-gray-500">Draw areas on the PDF preview.</p>
            </div>
          </div>
          <UButton
            :color="isDrawingMode ? 'neutral' : 'primary'"
            :variant="isDrawingMode ? 'outline' : 'solid'"
            :icon="isDrawingMode ? 'i-heroicons-x-mark' : 'i-heroicons-plus'"
             
            @click="toggleDrawingMode"
          >
            {{ isDrawingMode ? 'Stop Drawing' : 'Start Drawing' }}
          </UButton>
          <p v-if="isDrawingMode" class="text-xs text-gray-500">
            Click and drag on the PDF to create a zone.
          </p>
        </div>

        <!-- Zone Properties Panel -->
        <div
          :class="[
            'rounded-xl border border-dashed bg-gray-50/70 p-4 shadow-sm',
            selectedZone ? 'border-blue-400 bg-white' : 'border-gray-200'
          ]"
        >
          <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold">
              {{ selectedZone ? 'Zone Properties' : 'Select a zone' }}
            </h4>
            <UButton
              v-if="selectedZone"
              icon="i-heroicons-trash"
              color="error"
              variant="ghost"
              size="sm"
              @click="deleteZone(selectedZone.id)"
            />
          </div>

          <p class="text-xs text-gray-500 mb-3">
            {{ selectedZone ? 'Adjust the visual settings for the selected zone.' : 'Select a zone on the PDF preview to edit its settings.' }}
          </p>

          <div v-if="selectedZone" class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Form Field</label>
              <select
                :value="selectedZone.field_id"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                @change="updateSelectedZoneField('field_id', $event.target.value)"
              >
                <option value="">Select a field...</option>
                <optgroup label="Form Fields">
                  <option
                    v-for="field in formFields"
                    :key="field.id"
                    :value="field.id"
                  >
                    {{ field.name }}
                  </option>
                </optgroup>
                <optgroup label="Special Fields">
                  <option value="submission_id">Submission ID</option>
                  <option value="submission_date">Submission Date</option>
                  <option value="form_name">Form Name</option>
                </optgroup>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Font Size</label>
                <select
                  :value="selectedZone.font_size || 12"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                  @change="updateSelectedZoneField('font_size', parseInt($event.target.value))"
                >
                  <option v-for="size in fontSizes" :key="size" :value="size">{{ size }}px</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Font Color</label>
                <input
                  type="color"
                  :value="selectedZone.font_color || '#000000'"
                  class="w-full h-10 border border-gray-300 rounded-md cursor-pointer p-0"
                  @change="updateSelectedZoneField('font_color', $event.target.value)"
                />
              </div>
            </div>

            <div class="grid grid-cols-1">
              <div class="text-xs text-gray-500">
                Position:
                <strong class="text-sm text-gray-700">
                  x={{ selectedZone.x?.toFixed(1) }}%, y={{ selectedZone.y?.toFixed(1) }}%
                </strong>
                <br />
                Size:
                <strong class="text-sm text-gray-700">
                  {{ selectedZone.width?.toFixed(1) }}% × {{ selectedZone.height?.toFixed(1) }}%
                </strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PdfZoneItem from './PdfZoneItem.vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  template: { type: Object, required: true },
  form: { type: Object, required: true }
})

const emit = defineEmits(['update:modelValue'])

// PDF rendering state
const pdfCanvas = ref(null)
const editorContainer = ref(null)
const pdfLoading = ref(true)
// Use shallowRef to prevent Vue from deeply proxying PDF.js objects (breaks private members)
const pdfDoc = shallowRef(null)
const pdfjsLibRef = shallowRef(null)
const scale = ref(1)
const canvasWidth = ref(0)
const canvasHeight = ref(0)

// Page navigation
const currentPage = ref(1)

// Drawing state
const isDrawingMode = ref(false)
const isDrawing = ref(false)
const drawingStart = ref({ x: 0, y: 0 })
const drawingRect = ref(null)

// Zone selection
const selectedZoneId = ref(null)

// Font sizes
const fontSizes = [8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72]

// Form fields
const formFields = computed(() => {
  return props.form?.properties?.filter(p => !p.hidden) || []
})

// Current page zones
const currentPageZones = computed(() => {
  return props.modelValue.filter(z => z.page === currentPage.value)
})

// Selected zone
const selectedZone = computed(() => {
  return props.modelValue.find(z => z.id === selectedZoneId.value)
})

// Drawing rect style
const drawingRectStyle = computed(() => {
  if (!drawingRect.value) return {}
  return {
    left: `${drawingRect.value.x}px`,
    top: `${drawingRect.value.y}px`,
    width: `${drawingRect.value.width}px`,
    height: `${drawingRect.value.height}px`
  }
})

// Initialize PDF.js library
const initPdfJs = async () => {
  if (pdfjsLibRef.value) return pdfjsLibRef.value
  
  const pdfjsLib = await import('pdfjs-dist')
  const pdfjsWorker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url')
  
  // Set worker from the bundled package
  pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker.default
  pdfjsLibRef.value = pdfjsLib
  
  return pdfjsLib
}

// Load PDF using pdf.js
const loadPdf = async () => {
  if (!props.template?.id) return
  
  pdfLoading.value = true
  pdfDoc.value = null
  
  try {
    const pdfjsLib = await initPdfJs()
    
    // Fetch PDF from template download URL (need full URL for pdf.js)
    const config = useRuntimeConfig()
    const authStore = useAuthStore()
    const apiBase = config.public.apiBase
    const url = `${apiBase}open/forms/${props.form.id}/pdf-templates/${props.template.id}/download`
    
    // Load PDF with auth headers
    const loadingTask = pdfjsLib.getDocument({
      url,
      httpHeaders: {
        'Authorization': `Bearer ${authStore.token}`,
      },
    })
    pdfDoc.value = await loadingTask.promise
    
    await renderPage()
  } catch (err) {
    console.error('Failed to load PDF:', err)
  } finally {
    pdfLoading.value = false
  }
}

// Render current page
const renderPage = async () => {
  if (!pdfDoc.value || !pdfCanvas.value) return
  
  try {
    const page = await pdfDoc.value.getPage(currentPage.value)
    const viewport = page.getViewport({ scale: 1.5 })
    
    const canvas = pdfCanvas.value
    const context = canvas.getContext('2d')
    
    canvas.height = viewport.height
    canvas.width = viewport.width
    canvasWidth.value = viewport.width
    canvasHeight.value = viewport.height
    scale.value = 1.5
    
    await page.render({
      canvasContext: context,
      viewport
    }).promise
  } catch (err) {
    console.error('Failed to render page:', err)
  }
}


// Load PDF when template changes
watch(() => props.template, () => {
  loadPdf()
}, { immediate: true })

// Render page when current page changes
watch(currentPage, () => {
  renderPage()
})

// Toggle drawing mode
const toggleDrawingMode = () => {
  isDrawingMode.value = !isDrawingMode.value
  if (!isDrawingMode.value) {
    isDrawing.value = false
    drawingRect.value = null
  }
}

// Mouse events for drawing zones
const onMouseDown = (event) => {
  if (!isDrawingMode.value) return
  
  const rect = pdfCanvas.value.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  
  isDrawing.value = true
  drawingStart.value = { x, y }
  drawingRect.value = { x, y, width: 0, height: 0 }
}

const onMouseMove = (event) => {
  if (!isDrawing.value || !drawingRect.value) return
  
  const rect = pdfCanvas.value.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  
  drawingRect.value = {
    x: Math.min(drawingStart.value.x, x),
    y: Math.min(drawingStart.value.y, y),
    width: Math.abs(x - drawingStart.value.x),
    height: Math.abs(y - drawingStart.value.y)
  }
}

const onMouseUp = () => {
  if (!isDrawing.value || !drawingRect.value) return
  
  // Only create zone if it has reasonable size
  if (drawingRect.value.width > 10 && drawingRect.value.height > 10) {
    createZone()
  }
  
  isDrawing.value = false
  drawingRect.value = null
}

// Create a new zone
const createZone = () => {
  if (!drawingRect.value) return
  
  const zone = {
    id: `zone_${Date.now()}`,
    page: currentPage.value,
    x: (drawingRect.value.x / canvasWidth.value) * 100,
    y: (drawingRect.value.y / canvasHeight.value) * 100,
    width: (drawingRect.value.width / canvasWidth.value) * 100,
    height: (drawingRect.value.height / canvasHeight.value) * 100,
    field_id: '',
    font_size: 12,
    font_color: '#000000'
  }
  
  const newZones = [...props.modelValue, zone]
  emit('update:modelValue', newZones)
  
  // Select the new zone and exit drawing mode
  selectedZoneId.value = zone.id
  isDrawingMode.value = false
}

// Select a zone
const selectZone = (zoneId) => {
  selectedZoneId.value = zoneId
}

// Update a zone
const updateZone = (updatedZone) => {
  const newZones = props.modelValue.map(z => 
    z.id === updatedZone.id ? updatedZone : z
  )
  emit('update:modelValue', newZones)
}

// Delete a zone
const deleteZone = (zoneId) => {
  const newZones = props.modelValue.filter(z => z.id !== zoneId)
  emit('update:modelValue', newZones)
  if (selectedZoneId.value === zoneId) {
    selectedZoneId.value = null
  }
}

// Update selected zone field
const updateSelectedZoneField = (field, value) => {
  if (!selectedZone.value) return
  
  const updatedZone = { ...selectedZone.value, [field]: value }
  updateZone(updatedZone)
}
</script>

<style scoped>
.pdf-zone-editor {
  position: relative;
}
</style>
