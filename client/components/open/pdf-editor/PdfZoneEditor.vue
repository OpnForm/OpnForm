<template>
  <div class="pdf-zone-editor">
    <!-- Loading overlay -->
    <div
      v-if="pdfLoading"
      class="fixed inset-0 z-20 flex items-center justify-center bg-white/80 dark:bg-gray-900/80"
    >
      <Loader class="h-8 w-8 text-blue-600" />
    </div>

    <!-- Multi-page stack: pages vertically with spacing -->
    <div
      ref="pagesContainer"
      class="flex flex-col items-center gap-6 py-6 min-w-full"
      :style="{ minHeight: '520px' }"
      @click="handleBackgroundClick"
    >
      <div
        v-for="pageNum in pageList"
        :key="pageNum"
        :ref="el => setPageRef(el, pageNum)"
        class="relative flex flex-col items-center"
      >
        <!-- Page wrapper: shadow/paper style -->
        <div
          class="relative overflow-hidden bg-white dark:bg-gray-800 shadow-lg"
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
            class="w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-700"
          >
            <span class="text-sm text-gray-500 dark:text-gray-400">New Page</span>
          </div>

          <!-- Zones for this page -->
          <div
            v-for="zone in zonesForPage(pageNum)"
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
        <!-- Page number label below -->
        <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">
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
const canvasRects = ref({})

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

// Initialize PDF.js library
const initPdfJs = async () => {
  if (pdfjsLibRef.value) return pdfjsLibRef.value
  const pdfjsLib = await import('pdfjs-dist')
  const pdfjsWorker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url')
  pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker.default
  pdfjsLibRef.value = pdfjsLib
  return pdfjsLib
}

// Load PDF and render all pages
const loadPdf = async () => {
  if (!pdfTemplate.value?.id) return

  pdfLoading.value = true
  pdfDoc.value = null
  canvasRefs.value = {}
  pageRefs.value = {}

  try {
    const pdfjsLib = await initPdfJs()
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
const renderAllPages = async () => {
  if (!pdfDoc.value) return

  // Get dimensions from first physical page (for new pages and initial layout)
  const firstPhysical = pageList.value.find((p) => !isNewPage(p))
  if (firstPhysical) {
    const page = await pdfDoc.value.getPage(pdfStore.getPhysicalPageNumber(firstPhysical))
    const viewport = page.getViewport({ scale: zoomScale.value })
    canvasWidth.value = viewport.width
    canvasHeight.value = viewport.height
  }

  for (const pageNum of pageList.value) {
    if (isNewPage(pageNum)) continue
    await renderPage(pageNum)
  }
}

// Render single page
const renderPage = async (pageNum) => {
  if (!pdfDoc.value) return

  const canvas = canvasRefs.value[pageNum]
  if (!canvas) {
    await nextTick()
    if (canvasRefs.value[pageNum]) await renderPage(pageNum)
    return
  }

  const physicalPage = pdfStore.getPhysicalPageNumber(pageNum)
  if (physicalPage == null) return

  try {
    const page = await pdfDoc.value.getPage(physicalPage)
    const viewport = page.getViewport({ scale: zoomScale.value })

    const context = canvas.getContext('2d')
    canvas.height = viewport.height
    canvas.width = viewport.width

    await page.render({
      canvasContext: context,
      viewport,
    }).promise

    nextTick(() => {
      canvasRects.value[pageNum] = canvas.getBoundingClientRect()
    })
  } catch (err) {
    console.error(`Failed to render page ${pageNum}:`, err)
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
    await renderAllPages()
    nextTick(updateCanvasRects)
  },
  { deep: true }
)

// Watch for zoom changes
watch(zoomScale, async () => {
  if (!pdfDoc.value) return
  await renderAllPages()
  nextTick(updateCanvasRects)
})

// When currentPage changes (e.g. from left nav click), scroll to that page
watch(currentPage, (newPage) => {
  if (isScrollingToPage.value) return
  scrollToPage(newPage)
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
  const rect = canvasRects.value[activeZone.value.page]
  if (rect) {
    return { w: rect.width, h: rect.height }
  }
  return { w: canvasWidth.value, h: canvasHeight.value }
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
  const { w, h } = getCanvasDimensions()
  const rect = canvasRects.value[activeZone.value.page]
  if (!rect) return

  const dx = event.clientX - dragStart.value.x
  const dy = event.clientY - dragStart.value.y
  const dxPercent = (dx / w) * 100
  const dyPercent = (dy / h) * 100

  let newX = Math.max(0, Math.min(100 - zoneStart.value.width, zoneStart.value.x + dxPercent))
  let newY = Math.max(0, Math.min(100 - zoneStart.value.height, zoneStart.value.y + dyPercent))

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

onUnmounted(() => {
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDragging)
  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResizing)
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateCanvasRects)
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
