<template>
  <div class="pdf-zone-editor bg-neutral-100 dark:bg-neutral-900">
    <!-- Loading overlay -->
    <div
      v-if="pdfLoading"
      class="fixed inset-0 z-20 flex items-center justify-center bg-white/80 dark:bg-neutral-900/80"
    >
      <Loader class="h-8 w-8 text-blue-600" />
    </div>

    <!-- Multi-page stack: pages vertically with spacing -->
    <div
      ref="pagesContainer"
      class="flex flex-col items-center gap-3 py-6 min-w-full"
      :style="{ minHeight: '520px' }"
      @click="handleBackgroundClick"
      @wheel="handleWheelZoom"
    >
      <div
        v-for="pageNum in pageList"
        :key="pageNum"
        :ref="el => setPageRef(el, pageNum)"
        class="relative flex flex-col items-center"
      >
        <!-- Page wrapper: shadow/paper style -->
        <div
          class="pdf-page-surface relative overflow-hidden bg-white dark:bg-neutral-800 shadow-lg border border-neutral-300 dark:border-neutral-600"
          :style="pageWrapperStyle"
          @click="handleBackgroundClick"
        >
          <!-- PDF Canvas or blank page -->
          <canvas
            v-if="!isNewPage(pageNum)"
            :ref="el => setCanvasRef(el, pageNum)"
            class="block cursor-crosshair"
          />
          <div
            v-else
            class="w-full h-full bg-white"
          />

          <!-- Zones for this page -->
          <div
            v-for="zone in zonesForPage(pageNum)"
            :key="zone.id"
            :ref="el => setZoneRef(el, zone.id)"
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
            <!-- In-canvas image preview (static image zones only) -->
            <div
              v-else-if="zone.static_image !== undefined && zone.static_image"
              class="w-full h-full overflow-hidden pointer-events-none select-none flex items-center justify-center bg-neutral-100 dark:bg-neutral-700"
            >
              <img
                :src="zone.static_image"
                alt=""
                class="w-full h-full"
              >
            </div>
            <div
              class="absolute bottom-0 right-0 w-3 h-3 bg-blue-500 cursor-se-resize"
              @mousedown.stop="startResizing($event, zone)"
            />
          </div>
        </div>
        <!-- Page number label below -->
        <span class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
          Page {{ pageNum }}
        </span>
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
  lastAddedZoneId,
  zoomScale,
  pageList,
} = storeToRefs(pdfStore)

const { getZoneLabel, zonesForPage, isNewPage } = pdfStore

// PDF rendering state
const pagesContainer = ref(null)
const pdfLoading = ref(true)
const pdfDoc = shallowRef(null)
const pdfjsLibRef = shallowRef(null)
const canvasWidth = ref(0)
const canvasHeight = ref(0)
const canvasRefs = ref({})
const pageRefs = ref({})
const zoneRefs = ref({})
const canvasRects = ref({})
const wheelZoomRaf = ref(null)
const pendingWheelDelta = ref(0)
const activeRenderTasks = new Map()
const renderPassId = ref(0)
const zoomRenderTimeout = ref(null)
const pendingRenderAfterInteraction = ref(false)

// Drag/resize state
const isDragging = ref(false)
const isResizing = ref(false)
const activeZone = ref(null)
const dragStart = ref({ x: 0, y: 0 })
const zoneStart = ref({ x: 0, y: 0, width: 0, height: 0 })

// Programmatic scroll flag (avoid IntersectionObserver updating currentPage during scroll-to)
const isScrollingToPage = ref(false)
let intersectionObserver = null

// Page wrapper style (same for all pages)
const pageWrapperStyle = computed(() => {
  const w = canvasWidth.value
  const h = canvasHeight.value
  if (!w || !h) {
    return { minHeight: '520px', minWidth: '400px' }
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

const setCanvasRef = (el, pageNum) => {
  if (el) canvasRefs.value[pageNum] = el
}

const setPageRef = (el, pageNum) => {
  if (el) pageRefs.value[pageNum] = el
}

const setZoneRef = (el, zoneId) => {
  if (!zoneId) return
  if (el) {
    zoneRefs.value[zoneId] = el
    return
  }
  delete zoneRefs.value[zoneId]
}

const getPageSurfaceRect = (pageNum) => {
  const canvasRect = canvasRects.value[pageNum]
  if (canvasRect) return canvasRect
  const pageEl = pageRefs.value[pageNum]
  const surfaceEl = pageEl?.querySelector?.('.pdf-page-surface')
  return surfaceEl?.getBoundingClientRect?.() ?? null
}

// Initialize PDF.js library
const initPdfJs = async () => {
  if (!import.meta.client) return null
  if (pdfjsLibRef.value) return pdfjsLibRef.value
  const pdfjsLib = await import('pdfjs-dist')
  const pdfjsWorker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url')
  pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker.default
  pdfjsLibRef.value = pdfjsLib
  return pdfjsLib
}

const cancelRenderTaskForPage = (pageNum) => {
  const task = activeRenderTasks.get(pageNum)
  if (task) {
    task.cancel()
    activeRenderTasks.delete(pageNum)
  }
}

const cancelAllRenderTasks = () => {
  for (const [, task] of activeRenderTasks.entries()) {
    task.cancel()
  }
  activeRenderTasks.clear()
}

const getPriorityZoomPages = () => {
  const current = Number(currentPage.value)
  const candidatePages = [current - 1, current, current + 1]
  return candidatePages.filter((pageNum) => pageList.value.includes(pageNum) && !isNewPage(pageNum))
}

const scheduleRenderAllPages = (delayMs = 0, options = {}) => {
  if (!pdfDoc.value) return
  const { zoomOnlyVisible = false } = options
  if (zoomRenderTimeout.value != null) {
    clearTimeout(zoomRenderTimeout.value)
    zoomRenderTimeout.value = null
  }
  if (delayMs <= 0) {
    renderAllPages({ zoomOnlyVisible }).then(() => nextTick(updateCanvasRects))
    return
  }
  zoomRenderTimeout.value = setTimeout(() => {
    zoomRenderTimeout.value = null
    renderAllPages({ zoomOnlyVisible }).then(() => nextTick(updateCanvasRects))
  }, delayMs)
}

// Load PDF and render all pages
const loadPdf = async () => {
  if (!import.meta.client) return
  if (!pdfTemplate.value?.id) return

  cancelAllRenderTasks()
  if (zoomRenderTimeout.value != null) {
    clearTimeout(zoomRenderTimeout.value)
    zoomRenderTimeout.value = null
  }
  renderPassId.value++
  pdfLoading.value = true
  pdfDoc.value = null
  canvasRefs.value = {}
  pageRefs.value = {}

  try {
    const pdfjsLib = await initPdfJs()
    if (!pdfjsLib) return
    const loadingTask = pdfjsLib.getDocument(
      formsApi.pdfTemplates.getDownloadRequest(form.value.id, pdfTemplate.value.id)
    )
    pdfDoc.value = await loadingTask.promise
    await renderAllPages()
  } catch (err) {
    console.error('Failed to load PDF:', err)
  } finally {
    pdfLoading.value = false
  }
}

// Render all pages
const renderAllPages = async (options = {}) => {
  if (!pdfDoc.value) return
  const { zoomOnlyVisible = false } = options
  const thisPassId = ++renderPassId.value
  const targetPages = zoomOnlyVisible ? getPriorityZoomPages() : pageList.value.filter((p) => !isNewPage(p))
  if (!targetPages.length) return

  // Get dimensions from first physical page (for new pages and initial layout)
  const firstPhysical = targetPages[0]
  if (firstPhysical) {
    const sourcePageNumber = pdfStore.getSourcePageNumber(firstPhysical)
    if (sourcePageNumber == null) return
    const page = await pdfDoc.value.getPage(sourcePageNumber)
    if (thisPassId !== renderPassId.value) return
    const viewport = page.getViewport({ scale: zoomScale.value })
    canvasWidth.value = viewport.width
    canvasHeight.value = viewport.height
  }

  const renderPromises = []
  for (const pageNum of targetPages) {
    if (thisPassId !== renderPassId.value) return
    renderPromises.push(renderPage(pageNum, thisPassId))
  }
  await Promise.all(renderPromises)
}

// Render single page
const renderPage = async (pageNum, thisPassId) => {
  if (!pdfDoc.value) return
  if (thisPassId !== renderPassId.value) return
  let renderTask = null

  const canvas = canvasRefs.value[pageNum]
  if (!canvas) {
    await nextTick()
    if (canvasRefs.value[pageNum]) await renderPage(pageNum, thisPassId)
    return
  }

  const sourcePageNumber = pdfStore.getSourcePageNumber(pageNum)
  if (sourcePageNumber == null) return

  try {
    const page = await pdfDoc.value.getPage(sourcePageNumber)
    if (thisPassId !== renderPassId.value) return
    const viewport = page.getViewport({ scale: zoomScale.value })

    const renderCanvas = document.createElement('canvas')
    renderCanvas.height = viewport.height
    renderCanvas.width = viewport.width
    const renderContext = renderCanvas.getContext('2d')
    if (!renderContext) return

    cancelRenderTaskForPage(pageNum)
    renderTask = page.render({
      canvasContext: renderContext,
      viewport,
    })
    activeRenderTasks.set(pageNum, renderTask)
    await renderTask.promise

    if (thisPassId !== renderPassId.value) return
    const context = canvas.getContext('2d')
    if (!context) return
    canvas.height = viewport.height
    canvas.width = viewport.width
    context.clearRect(0, 0, canvas.width, canvas.height)
    context.drawImage(renderCanvas, 0, 0)
    nextTick(() => {
      canvasRects.value[pageNum] = canvas.getBoundingClientRect()
    })
  } catch (err) {
    if (err?.name === 'RenderingCancelledException') return
    console.error(`Failed to render page ${pageNum}:`, err)
  } finally {
    const currentTask = activeRenderTasks.get(pageNum)
    if (currentTask === renderTask) {
      activeRenderTasks.delete(pageNum)
    }
  }
}

// Update canvas rects (e.g. after zoom or layout)
const updateCanvasRects = () => {
  const rects = {}
  for (const [pageNum, canvas] of Object.entries(canvasRefs.value)) {
    if (canvas) rects[pageNum] = canvas.getBoundingClientRect()
  }
  canvasRects.value = rects
}

// Zoom with touchpad pinch (Chrome/Edge emits wheel+ctrlKey for pinch gesture)
const applyWheelZoom = () => {
  if (!pendingWheelDelta.value) return
  const ZOOM_WHEEL_SENSITIVITY = 0.0015
  const delta = pendingWheelDelta.value
  pendingWheelDelta.value = 0
  wheelZoomRaf.value = null
  pdfStore.setZoomScale(zoomScale.value - (delta * ZOOM_WHEEL_SENSITIVITY))
}

const handleWheelZoom = (event) => {
  // Keep regular two-finger scroll for navigation; only intercept pinch gestures.
  if (!event.ctrlKey) return
  if (isDragging.value || isResizing.value) return
  event.preventDefault()
  pendingWheelDelta.value += event.deltaY
  if (wheelZoomRaf.value != null) return
  wheelZoomRaf.value = window.requestAnimationFrame(applyWheelZoom)
}

// Scroll to page when selected from left nav
const scrollToPage = (pageNum) => {
  const el = pageRefs.value[pageNum]
  if (el) {
    isScrollingToPage.value = true
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    setTimeout(() => {
      isScrollingToPage.value = false
    }, 800)
  }
}

// Watch for template changes
watch(pdfTemplate, loadPdf, { immediate: true })

// Watch page list (add/remove pages)
watch(
  () => [pageList.value, pdfDoc.value],
  async () => {
    if (!pdfDoc.value) return
    await nextTick()
    scheduleRenderAllPages(0)
  },
  { deep: true }
)

// Watch for zoom changes
watch(zoomScale, async () => {
  if (!pdfDoc.value) return
  if (isDragging.value || isResizing.value) {
    pendingRenderAfterInteraction.value = true
    return
  }
  // Render only nearby pages during active zoom for smoother interactions.
  scheduleRenderAllPages(90, { zoomOnlyVisible: true })
})

watch([isDragging, isResizing], ([dragging, resizing]) => {
  if (dragging || resizing) return
  if (!pendingRenderAfterInteraction.value) return
  pendingRenderAfterInteraction.value = false
  scheduleRenderAllPages(0, { zoomOnlyVisible: true })
})

// When currentPage changes (e.g. from left nav click), scroll to that page
watch(currentPage, (newPage) => {
  if (isScrollingToPage.value) return
  scrollToPage(newPage)
  scheduleRenderAllPages(0, { zoomOnlyVisible: true })
}, { flush: 'post' })

// Setup IntersectionObserver when pages are rendered
const setupObserver = () => {
  if (intersectionObserver) {
    intersectionObserver.disconnect()
  }
  const scrollRoot = pagesContainer.value?.closest?.('.pdf-editor-scroll-container')
  if (!scrollRoot || Object.keys(pageRefs.value).length === 0) return

  intersectionObserver = new IntersectionObserver(
    (entries) => {
      if (isScrollingToPage.value) return
      let bestPage = currentPage.value
      let bestRatio = 0
      for (const entry of entries) {
        if (!entry.isIntersecting) continue
        const pageNum = Number(entry.target.dataset.page)
        if (!pageNum) continue
        if (entry.intersectionRatio > bestRatio) {
          bestRatio = entry.intersectionRatio
          bestPage = pageNum
        }
      }
      if (bestRatio > 0.1 && bestPage !== currentPage.value) {
        isScrollingToPage.value = true
        pdfStore.setCurrentPage(bestPage)
        nextTick(() => { isScrollingToPage.value = false })
      }
    },
    {
      root: scrollRoot,
      rootMargin: '-10% 0px -70% 0px',
      threshold: [0, 0.1, 0.25, 0.5, 0.75, 1],
    }
  )

  for (const [pageNum, el] of Object.entries(pageRefs.value)) {
    if (el) {
      el.dataset.page = String(pageNum)
      intersectionObserver.observe(el)
    }
  }
}

watch(
  () => [pdfLoading.value, pageList.value.length],
  () => {
    if (pdfLoading.value) return
    nextTick(() => {
      setTimeout(setupObserver, 100)
    })
  }
)

// Select zone
const selectZone = (zoneId) => {
  pdfStore.setSelectedZone(zoneId)
}

// Handle click on background (deselect zone)
const handleBackgroundClick = () => {
  pdfStore.setSelectedZone(null)
}

// Get canvas dimensions for a zone's page (for drag/resize)
const getCanvasDimensions = () => {
  if (!activeZone.value) return { w: canvasWidth.value, h: canvasHeight.value }
  const rect = getPageSurfaceRect(activeZone.value.page)
  if (rect) {
    return { w: rect.width, h: rect.height }
  }
  return { w: canvasWidth.value, h: canvasHeight.value }
}

const moveZoneToAdjacentPage = (direction, event) => {
  if (!activeZone.value || !pdfTemplate.value?.zone_mappings) return false
  const currentPageNum = Number(activeZone.value.page)
  const currentIndex = pageList.value.indexOf(currentPageNum)
  if (currentIndex === -1) return false

  const targetIndex = currentIndex + direction
  if (targetIndex < 0 || targetIndex >= pageList.value.length) return false

  const zone = pdfTemplate.value.zone_mappings.find((z) => z.id === activeZone.value.id)
  if (!zone) return false

  const targetPageNum = pageList.value[targetIndex]
  zone.page = targetPageNum
  zone.page_id = pdfStore.getPageId(targetPageNum)
  zone.y = direction > 0
    ? 0
    : Math.max(0, 100 - zone.height)

  dragStart.value = { x: event.clientX, y: event.clientY }
  zoneStart.value = { x: zone.x, y: zone.y, width: zone.width, height: zone.height }
  return true
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
  const PAGE_TRANSFER_THRESHOLD_PX = 24
  const pointerDeltaY = event.clientY - dragStart.value.y
  const pageRect = getPageSurfaceRect(activeZone.value.page)
  if (pageRect) {
    const nearTopEdge = event.clientY <= pageRect.top + PAGE_TRANSFER_THRESHOLD_PX
    const nearBottomEdge = event.clientY >= pageRect.bottom - PAGE_TRANSFER_THRESHOLD_PX
    if (pointerDeltaY < 0 && nearTopEdge && moveZoneToAdjacentPage(-1, event)) {
      onDrag(event)
      return
    }
    if (pointerDeltaY > 0 && nearBottomEdge && moveZoneToAdjacentPage(1, event)) {
      onDrag(event)
      return
    }
  }

  const { w, h } = getCanvasDimensions()

  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  const dxPercent = (dx / w) * 100
  const dyPercent = (dy / h) * 100

  const maxY = 100 - zoneStart.value.height
  let newX = Math.max(0, Math.min(100 - zoneStart.value.width, zoneStart.value.x + dxPercent))
  let newY = Math.max(0, Math.min(maxY, zoneStart.value.y + dyPercent))

  // If dragged down while already clamped at bottom edge, transfer to next page early.
  if (pointerDeltaY > 0 && newY >= maxY && moveZoneToAdjacentPage(1, event)) {
    onDrag(event)
    return
  }
  // Symmetric handling for upward transfer when clamped at top edge.
  if (pointerDeltaY < 0 && newY <= 0 && moveZoneToAdjacentPage(-1, event)) {
    onDrag(event)
    return
  }

  const zone = pdfTemplate.value.zone_mappings.find((z) => z.id === activeZone.value.id)
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
  const { w, h } = getCanvasDimensions()

  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  const dxPercent = (dx / w) * 100
  const dyPercent = (dy / h) * 100

  let newWidth = Math.max(5, Math.min(100 - zoneStart.value.x, zoneStart.value.width + dxPercent))
  let newHeight = Math.max(2, Math.min(100 - zoneStart.value.y, zoneStart.value.height + dyPercent))

  const zone = pdfTemplate.value.zone_mappings.find((z) => z.id === activeZone.value.id)
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

// Update rects on resize
onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateCanvasRects)
  }
})

watch(lastAddedZoneId, async (zoneId) => {
  if (!zoneId) return
  const zone = pdfTemplate.value?.zone_mappings?.find((z) => z.id === zoneId)
  if (!zone) {
    pdfStore.clearLastAddedZone()
    return
  }
  pdfStore.setCurrentPage(zone.page)
  await nextTick()
  const pageEl = pageRefs.value[zone.page]
  if (pageEl) {
    pageEl.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
  await nextTick()
  const zoneEl = zoneRefs.value[zoneId]
  if (zoneEl) {
    zoneEl.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' })
  }
  pdfStore.clearLastAddedZone()
})

onUnmounted(() => {
  cancelAllRenderTasks()
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDragging)
  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResizing)
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateCanvasRects)
    if (wheelZoomRaf.value != null) {
      window.cancelAnimationFrame(wheelZoomRaf.value)
    }
  }
  if (zoomRenderTimeout.value != null) {
    clearTimeout(zoomRenderTimeout.value)
    zoomRenderTimeout.value = null
  }
  if (intersectionObserver) {
    intersectionObserver.disconnect()
  }
})
</script>

<style scoped>
.pdf-zone-editor {
  position: relative;
}
</style>
