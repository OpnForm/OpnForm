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
              class="pdf-zone-text-preview w-full h-full overflow-hidden leading-tight pointer-events-none select-none"
              :style="getZoneTextPreviewStyle(zone)"
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
            <!-- In-canvas mapped field preview (actual submissions render here) -->
            <div
              v-else-if="zone.field_id"
              class="pdf-zone-text-preview w-full h-full overflow-hidden pointer-events-none select-none leading-tight"
              :style="getZoneTextPreviewStyle(zone)"
            >
              {{ getZoneLabel(zone) }}
            </div>
            <div
              class="absolute bottom-0 right-0 w-3 h-3 bg-blue-500 cursor-se-resize"
              @mousedown.stop="startResizing($event, zone)"
            />
          </div>
          <div
            v-if="snapGuides.page === pageNum && snapGuides.vertical !== null"
            class="absolute top-0 bottom-0 w-px bg-blue-500/80 pointer-events-none z-20"
            :style="{ left: `${snapGuides.vertical}%` }"
          />
          <div
            v-if="snapGuides.page === pageNum && snapGuides.horizontal !== null"
            class="absolute left-0 right-0 h-px bg-blue-500/80 pointer-events-none z-20"
            :style="{ top: `${snapGuides.horizontal}%` }"
          />
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
const INITIAL_FIT_HORIZONTAL_PADDING = 48
const SNAP_THRESHOLD_PX = 6
const MIN_ZONE_WIDTH_PERCENT = 5
const MIN_ZONE_HEIGHT_PERCENT = 2
const snapGuides = ref({ page: null, vertical: null, horizontal: null })

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

const getZoneTextPreviewStyle = (zone) => {
  const fontSize = Number(zone.font_size) || 12
  return {
    color: zone.font_color || '#111827',
    fontFamily: 'Helvetica, Arial, sans-serif',
    fontSize: `${fontSize * zoomScale.value}px`,
  }
}

const setCanvasRef = (el, pageNum) => {
  if (el) {
    canvasRefs.value[pageNum] = el
    return
  }
  delete canvasRefs.value[pageNum]
  delete canvasRects.value[pageNum]
}

const setPageRef = (el, pageNum) => {
  if (el) {
    pageRefs.value[pageNum] = el
    return
  }
  delete pageRefs.value[pageNum]
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

const getActiveZoneLive = () => {
  if (!activeZone.value?.id || !pdfTemplate.value?.zone_mappings) return null
  return pdfTemplate.value.zone_mappings.find((z) => z.id === activeZone.value.id) || null
}

const clearSnapGuides = () => {
  snapGuides.value = { page: null, vertical: null, horizontal: null }
}

const setSnapGuides = (page, snapResult) => {
  snapGuides.value = {
    page,
    vertical: snapResult.vertical ?? null,
    horizontal: snapResult.horizontal ?? null,
  }
}

const clampPercent = (value, min, max) => {
  return Math.max(min, Math.min(max, value))
}

const getSnapTargetsForPage = (activeZoneId, page) => {
  const targets = {
    x: [0, 50, 100],
    y: [0, 50, 100],
  }

  for (const zone of pdfTemplate.value?.zone_mappings || []) {
    if (zone.id === activeZoneId || Number(zone.page) !== Number(page)) continue
    targets.x.push(zone.x, zone.x + (zone.width / 2), zone.x + zone.width)
    targets.y.push(zone.y, zone.y + (zone.height / 2), zone.y + zone.height)
  }

  return targets
}

const findClosestSnap = (activeAnchors, targets, thresholdPercent) => {
  let closest = null

  for (const activeAnchor of activeAnchors) {
    for (const target of targets) {
      const delta = target - activeAnchor
      const distance = Math.abs(delta)
      if (distance > thresholdPercent) continue
      if (!closest || distance < closest.distance) {
        closest = { delta, target, distance }
      }
    }
  }

  return closest
}

const snapMovingZone = (zone, rect, dimensions) => {
  const targets = getSnapTargetsForPage(zone.id, zone.page)
  const thresholdX = (SNAP_THRESHOLD_PX / dimensions.w) * 100
  const thresholdY = (SNAP_THRESHOLD_PX / dimensions.h) * 100

  const xSnap = findClosestSnap([
    rect.x,
    rect.x + (rect.width / 2),
    rect.x + rect.width,
  ], targets.x, thresholdX)
  const ySnap = findClosestSnap([
    rect.y,
    rect.y + (rect.height / 2),
    rect.y + rect.height,
  ], targets.y, thresholdY)

  const snappedRect = { ...rect }
  if (xSnap) {
    snappedRect.x = clampPercent(rect.x + xSnap.delta, 0, 100 - rect.width)
  }
  if (ySnap) {
    snappedRect.y = clampPercent(rect.y + ySnap.delta, 0, 100 - rect.height)
  }

  return {
    rect: snappedRect,
    vertical: xSnap?.target,
    horizontal: ySnap?.target,
  }
}

const findResizeSnap = (start, currentSize, targets, thresholdPercent, minSize, maxSize) => {
  const anchors = [
    {
      value: start + currentSize,
      getSize: (target) => target - start,
    },
    {
      value: start + (currentSize / 2),
      getSize: (target) => (target - start) * 2,
    },
  ]
  let closest = null

  for (const anchor of anchors) {
    for (const target of targets) {
      const nextSize = anchor.getSize(target)
      if (nextSize < minSize || nextSize > maxSize) continue
      const delta = target - anchor.value
      const distance = Math.abs(delta)
      if (distance > thresholdPercent) continue
      if (!closest || distance < closest.distance) {
        closest = { size: nextSize, target, distance }
      }
    }
  }

  return closest
}

const snapResizingZone = (zone, rect, dimensions) => {
  const targets = getSnapTargetsForPage(zone.id, zone.page)
  const thresholdX = (SNAP_THRESHOLD_PX / dimensions.w) * 100
  const thresholdY = (SNAP_THRESHOLD_PX / dimensions.h) * 100
  const widthSnap = findResizeSnap(
    rect.x,
    rect.width,
    targets.x,
    thresholdX,
    MIN_ZONE_WIDTH_PERCENT,
    100 - rect.x
  )
  const heightSnap = findResizeSnap(
    rect.y,
    rect.height,
    targets.y,
    thresholdY,
    MIN_ZONE_HEIGHT_PERCENT,
    100 - rect.y
  )

  return {
    rect: {
      ...rect,
      width: widthSnap?.size ?? rect.width,
      height: heightSnap?.size ?? rect.height,
    },
    vertical: widthSnap?.target,
    horizontal: heightSnap?.target,
  }
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

const getEditorScrollRoot = () => {
  return pagesContainer.value?.closest?.('.pdf-editor-scroll-container')
}

const getAvailablePageWidth = () => {
  const scrollRoot = getEditorScrollRoot()
  const containerWidth = scrollRoot?.clientWidth || pagesContainer.value?.clientWidth || 0
  return Math.max(0, containerWidth - INITIAL_FIT_HORIZONTAL_PADDING)
}

const fitZoomToPageWidth = async () => {
  if (!pdfDoc.value) return

  const firstPhysicalPage = pageList.value.find((pageNum) => !isNewPage(pageNum))
  if (!firstPhysicalPage) return

  const sourcePageNumber = pdfStore.getSourcePageNumber(firstPhysicalPage)
  if (sourcePageNumber == null) return

  const availablePageWidth = getAvailablePageWidth()
  if (!availablePageWidth) return

  const page = await pdfDoc.value.getPage(sourcePageNumber)
  const viewport = page.getViewport({ scale: 1 })
  if (!viewport.width) return

  pdfStore.setZoomScale(availablePageWidth / viewport.width)
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
    await nextTick()
    await fitZoomToPageWidth()
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
  const scrollRoot = getEditorScrollRoot()
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
  const liveZone = getActiveZoneLive()
  if (!liveZone) return { w: canvasWidth.value, h: canvasHeight.value }
  const rect = getPageSurfaceRect(liveZone.page)
  if (rect) {
    return { w: rect.width, h: rect.height }
  }
  return { w: canvasWidth.value, h: canvasHeight.value }
}

const moveZoneToAdjacentPage = (direction, event) => {
  const zone = getActiveZoneLive()
  if (!zone) return false
  const currentPageNum = Number(zone.page)
  const currentIndex = pageList.value.indexOf(currentPageNum)
  if (currentIndex === -1) return false

  const targetIndex = currentIndex + direction
  if (targetIndex < 0 || targetIndex >= pageList.value.length) return false

  const targetPageNum = pageList.value[targetIndex]
  zone.page = targetPageNum
  zone.page_id = pdfStore.getPageId(targetPageNum)
  clearSnapGuides()
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
  updateCanvasRects()
  isDragging.value = true
  activeZone.value = { id: zone.id }
  dragStart.value = { x: event.clientX, y: event.clientY }
  zoneStart.value = { x: zone.x, y: zone.y, width: zone.width, height: zone.height }
  selectZone(zone.id)
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDragging)
}

// Dragging
const onDrag = (event) => {
  const zone = getActiveZoneLive()
  if (!isDragging.value || !zone) return
  const PAGE_TRANSFER_THRESHOLD_PX = 24
  const pointerDeltaY = event.clientY - dragStart.value.y
  const pageRect = getPageSurfaceRect(zone.page)
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

  const dimensions = getCanvasDimensions()
  if (!Number.isFinite(dimensions.w) || !Number.isFinite(dimensions.h) || dimensions.w < 20 || dimensions.h < 20) return

  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  const dxPercent = (dx / dimensions.w) * 100
  const dyPercent = (dy / dimensions.h) * 100

  const maxY = 100 - zoneStart.value.height
  let newX = clampPercent(zoneStart.value.x + dxPercent, 0, 100 - zoneStart.value.width)
  let newY = clampPercent(zoneStart.value.y + dyPercent, 0, maxY)

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

  const snapResult = snapMovingZone(zone, {
    x: newX,
    y: newY,
    width: zoneStart.value.width,
    height: zoneStart.value.height,
  }, dimensions)

  zone.x = snapResult.rect.x
  zone.y = snapResult.rect.y
  setSnapGuides(zone.page, snapResult)
}

// Stop dragging
const stopDragging = () => {
  isDragging.value = false
  activeZone.value = null
  clearSnapGuides()
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDragging)
}

// Start resizing
const startResizing = (event, zone) => {
  event.preventDefault()
  updateCanvasRects()
  isResizing.value = true
  activeZone.value = { id: zone.id }
  dragStart.value = { x: event.clientX, y: event.clientY }
  zoneStart.value = { x: zone.x, y: zone.y, width: zone.width, height: zone.height }
  selectZone(zone.id)
  document.addEventListener('mousemove', onResize)
  document.addEventListener('mouseup', stopResizing)
}

// Resizing
const onResize = (event) => {
  const zone = getActiveZoneLive()
  if (!isResizing.value || !zone) return
  const dimensions = getCanvasDimensions()
  if (!Number.isFinite(dimensions.w) || !Number.isFinite(dimensions.h) || dimensions.w < 20 || dimensions.h < 20) return

  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  const dxPercent = (dx / dimensions.w) * 100
  const dyPercent = (dy / dimensions.h) * 100

  let newWidth = clampPercent(
    zoneStart.value.width + dxPercent,
    MIN_ZONE_WIDTH_PERCENT,
    100 - zoneStart.value.x
  )
  let newHeight = clampPercent(
    zoneStart.value.height + dyPercent,
    MIN_ZONE_HEIGHT_PERCENT,
    100 - zoneStart.value.y
  )

  const snapResult = snapResizingZone(zone, {
    x: zoneStart.value.x,
    y: zoneStart.value.y,
    width: newWidth,
    height: newHeight,
  }, dimensions)

  zone.width = snapResult.rect.width
  zone.height = snapResult.rect.height
  setSnapGuides(zone.page, snapResult)
}

// Stop resizing
const stopResizing = () => {
  isResizing.value = false
  activeZone.value = null
  clearSnapGuides()
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
    pageEl.scrollIntoView({ behavior: 'auto', block: 'start' })
  }
  await nextTick()
  const zoneEl = zoneRefs.value[zoneId]
  if (zoneEl) {
    zoneEl.scrollIntoView({ behavior: 'auto', block: 'nearest', inline: 'nearest' })
  }
  pdfStore.clearLastAddedZone()
})

onUnmounted(() => {
  cancelAllRenderTasks()
  clearSnapGuides()
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

.pdf-zone-text-preview :deep(h1),
.pdf-zone-text-preview :deep(h2),
.pdf-zone-text-preview :deep(h3),
.pdf-zone-text-preview :deep(p),
.pdf-zone-text-preview :deep(div) {
  margin: 0;
}

.pdf-zone-text-preview :deep(h1) {
  font-size: 2em;
  font-weight: 700;
  line-height: 1;
}

.pdf-zone-text-preview :deep(h2) {
  font-size: 1.5em;
  font-weight: 700;
  line-height: 1;
}
</style>
