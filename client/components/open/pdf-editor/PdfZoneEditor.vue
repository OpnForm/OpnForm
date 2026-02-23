<template>
  <div class="pdf-zone-editor">
    <!-- PDF Canvas with Zones -->
    <div
      ref="editorContainer"
      class="relative min-w-full w-max"
      :style="{ minHeight: '520px' }"
      @click="handleBackgroundClick"
    >
      <!-- Wrapper sized to PDF page so zones are constrained and aligned with canvas -->
      <div
        class="relative mx-auto overflow-hidden"
        :style="pageWrapperStyle"
      >
        <!-- PDF Canvas -->
        <canvas
          ref="pdfCanvas"
          class="block cursor-crosshair"
          @click="handleBackgroundClick"
        />

        <!-- Existing Zones (positioned inside page bounds, clipped by overflow-hidden) -->
        <div
          v-for="zone in currentPageZones"
          :key="zone.id"
          class="absolute border-2 cursor-move transition-colors"
          :class="[
            selectedZoneId === zone.id
              ? 'border-blue-500 bg-blue-500/20'
              : 'border-blue-400/60 bg-blue-400/10 hover:border-blue-500 hover:bg-blue-500/15'
          ]"
          :style="getZoneStyle(zone)"
          @mousedown.stop="startDragging($event, zone)"
          @click.stop="selectZone(zone.id)"
        >
          <div
            class="absolute -top-5 left-0 text-xs bg-blue-500 text-white px-1.5 py-0.5 rounded whitespace-nowrap"
            :class="{ 'opacity-60': selectedZoneId !== zone.id }"
          >
            {{ getZoneLabel(zone) }}
          </div>
          <!-- In-canvas text preview (static text zones only) -->
          <div
            v-if="zone.static_text !== undefined && zone.static_text"
            class="w-full h-full overflow-hidden leading-tight pointer-events-none select-none"
            v-html="zone.static_text"
          />
          <div
            class="absolute bottom-0 right-0 w-3 h-3 bg-blue-500 cursor-se-resize"
            @mousedown.stop="startResizing($event, zone)"
          />
        </div>
      </div>

      <!-- Loading overlay -->
      <div
        v-if="pdfLoading"
        class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80"
      >
        <Loader class="h-8 w-8 text-blue-600" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { formsApi } from '~/api/forms'

const pdfStore = useWorkingPdfStore()
const { 
  content: pdfTemplate,
  form,
  currentPage,
  selectedZoneId,
  currentPageZones,
  zoomScale,
} = storeToRefs(pdfStore)

const { getZoneLabel } = pdfStore

// PDF rendering state
const pdfCanvas = ref(null)
const editorContainer = ref(null)
const pdfLoading = ref(true)
const pdfDoc = shallowRef(null)
const pdfjsLibRef = shallowRef(null)
const canvasWidth = ref(0)
const canvasHeight = ref(0)
const canvasRect = ref(null)

// Drag/resize state
const isDragging = ref(false)
const isResizing = ref(false)
const activeZone = ref(null)
const dragStart = ref({ x: 0, y: 0 })
const zoneStart = ref({ x: 0, y: 0, width: 0, height: 0 })

// Wrapper style: same size as PDF page so zones are constrained within the page
const pageWrapperStyle = computed(() => {
  const w = canvasWidth.value
  const h = canvasHeight.value
  if (!w || !h) {
    return { minHeight: '520px' }
  }
  return {
    width: `${w}px`,
    height: `${h}px`,
  }
})

// Get zone style (convert percentage to pixels)
const getZoneStyle = (zone) => {
  return {
    left: `${(zone.x / 100) * canvasWidth.value}px`,
    top: `${(zone.y / 100) * canvasHeight.value}px`,
    width: `${(zone.width / 100) * canvasWidth.value}px`,
    height: `${(zone.height / 100) * canvasHeight.value}px`,
  }
}

// Initialize PDF.js library
const initPdfJs = async () => {
  if (pdfjsLibRef.value) return pdfjsLibRef.value
  
  const pdfjsLib = await import('pdfjs-dist')
  const pdfjsWorker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url')
  
  pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker.default
  pdfjsLibRef.value = pdfjsLib
  
  return pdfjsLib
}

// Load PDF
const loadPdf = async () => {
  if (!pdfTemplate.value?.id) return
  
  pdfLoading.value = true
  pdfDoc.value = null
  
  try {
    const pdfjsLib = await initPdfJs()

    const loadingTask = pdfjsLib.getDocument(
      formsApi.pdfTemplates.getDownloadRequest(form.value.id, pdfTemplate.value.id)
    )
    pdfDoc.value = await loadingTask.promise
    
    await renderPage()
  } catch (err) {
    console.error('Failed to load PDF:', err)
  } finally {
    pdfLoading.value = false
  }
}

// Render current page (supports new/blank pages)
const renderPage = async () => {
  if (!pdfDoc.value || !pdfCanvas.value) return
  
  const logicalPage = currentPage.value
  const isNew = pdfStore.isNewPage(logicalPage)
  
  try {
    // Use page 1 for dimensions when current page is blank
    const physicalPage = pdfStore.getPhysicalPageNumber(logicalPage)
    const sourcePageNum = isNew ? 1 : (physicalPage ?? 1)
    const page = await pdfDoc.value.getPage(sourcePageNum)
    const viewport = page.getViewport({ scale: zoomScale.value })
    
    const canvas = pdfCanvas.value
    const context = canvas.getContext('2d')
    
    canvas.height = viewport.height
    canvas.width = viewport.width
    canvasWidth.value = viewport.width
    canvasHeight.value = viewport.height
    
    if (isNew) {
      context.fillStyle = '#ffffff'
      context.fillRect(0, 0, viewport.width, viewport.height)
    } else {
      await page.render({
        canvasContext: context,
        viewport
      }).promise
    }
    
    // Update canvas rect for drag calculations
    canvasRect.value = canvas.getBoundingClientRect()
  } catch (err) {
    console.error('Failed to render page:', err)
  }
}

// Watch for template changes
watch(pdfTemplate, loadPdf, { immediate: true })

// Watch for page changes
watch(currentPage, renderPage)

// Watch for zoom changes
watch(zoomScale, renderPage)

// Select zone
const selectZone = (zoneId) => {
  pdfStore.setSelectedZone(zoneId)
}

// Handle click on background (deselect zone)
const handleBackgroundClick = () => {
  pdfStore.setSelectedZone(null)
}

// Start dragging
const startDragging = (event, zone) => {
  if (isResizing.value) return
  
  isDragging.value = true
  activeZone.value = zone
  dragStart.value = { x: event.clientX, y: event.clientY }
  zoneStart.value = { x: zone.x, y: zone.y, width: zone.width, height: zone.height }
  
  selectZone(zone.id)
  
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDragging)
}

// Dragging
const onDrag = (event) => {
  if (!isDragging.value || !activeZone.value || !pdfTemplate.value?.zone_mappings) return
  
  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  
  // Convert pixel delta to percentage
  const dxPercent = (dx / canvasWidth.value) * 100
  const dyPercent = (dy / canvasHeight.value) * 100
  
  // Calculate new position with bounds
  let newX = Math.max(0, Math.min(100 - zoneStart.value.width, zoneStart.value.x + dxPercent))
  let newY = Math.max(0, Math.min(100 - zoneStart.value.height, zoneStart.value.y + dyPercent))
  
  // Update zone directly in store
  const zone = pdfTemplate.value.zone_mappings.find(z => z.id === activeZone.value.id)
  if (zone) {
    zone.x = newX
    zone.y = newY
  }
}

// Stop dragging
const stopDragging = () => {
  isDragging.value = false
  activeZone.value = null
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDragging)
}

// Start resizing
const startResizing = (event, zone) => {
  event.preventDefault()
  
  isResizing.value = true
  activeZone.value = zone
  dragStart.value = { x: event.clientX, y: event.clientY }
  zoneStart.value = { x: zone.x, y: zone.y, width: zone.width, height: zone.height }
  
  selectZone(zone.id)
  
  document.addEventListener('mousemove', onResize)
  document.addEventListener('mouseup', stopResizing)
}

// Resizing
const onResize = (event) => {
  if (!isResizing.value || !activeZone.value || !pdfTemplate.value?.zone_mappings) return
  
  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  
  // Convert pixel delta to percentage
  const dxPercent = (dx / canvasWidth.value) * 100
  const dyPercent = (dy / canvasHeight.value) * 100
  
  // Calculate new size with minimum
  let newWidth = Math.max(5, Math.min(100 - zoneStart.value.x, zoneStart.value.width + dxPercent))
  let newHeight = Math.max(2, Math.min(100 - zoneStart.value.y, zoneStart.value.height + dyPercent))
  
  // Update zone directly in store
  const zone = pdfTemplate.value.zone_mappings.find(z => z.id === activeZone.value.id)
  if (zone) {
    zone.width = newWidth
    zone.height = newHeight
  }
}

// Stop resizing
const stopResizing = () => {
  isResizing.value = false
  activeZone.value = null
  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResizing)
}

// Cleanup
onUnmounted(() => {
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDragging)
  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResizing)
})
</script>

<style scoped>
.pdf-zone-editor {
  position: relative;
}
</style>
