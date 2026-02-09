<template>
  <div
    ref="elementRef"
    class="relative bg-white dark:bg-gray-800 border-r border-gray-300 dark:border-gray-700 flex flex-col overflow-hidden flex-shrink-0"
    :class="isResizable ? '' : 'w-40'"
    :style="isResizable ? dynamicStyles : {}"
  >
    <!-- Resize handle on right edge (wider hit area for easier drag) -->
    <div
      class="absolute top-0 right-0 bottom-0 w-2 z-10 cursor-col-resize flex justify-end"
      @mousedown="startResize($event)"
    >
      <ResizeHandle
        :show="isResizable"
        direction="left"
        @start-resize="startResize($event)"
      />
    </div>
    <!-- Header -->
    <div class="p-3 border-b border-gray-300 dark:border-gray-600">
      <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
        {{ pdfTemplate?.page_count }} page{{ pdfTemplate?.page_count > 1 ? 's' : '' }} •
        {{ (pdfTemplate?.zone_mappings?.length || 0) }} zone{{ (pdfTemplate?.zone_mappings?.length || 0) > 1 ? 's' : '' }}
      </p>
    </div>

    <!-- Pages List -->
    <div class="flex-1 overflow-y-auto p-3 space-y-3">
      <div
        v-for="pageNum in pageList"
        :key="pageNum"
        class="cursor-pointer group relative"
        @click="selectPage(pageNum)"
      >
        <!-- Thumbnail Container -->
        <div
          class="relative rounded-lg overflow-hidden border-2 transition-all shadow-sm"
          :class="[
            currentPage === pageNum
              ? 'border-blue-500 ring-2 ring-blue-500/30'
              : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
          ]"
        >
          <canvas
            v-if="!isNewPage(pageNum)"
            :ref="el => setCanvasRef(el, pageNum)"
            class="w-full h-auto bg-white"
          />
          <!-- Blank page placeholder -->
          <div
            v-else
            class="w-full aspect-[8.5/11] bg-gray-200 dark:bg-gray-600 flex items-center justify-center"
          >
            <span class="text-xs text-gray-500 dark:text-gray-400">New Page</span>
          </div>
          <!-- Loading overlay -->
          <div
            v-if="!isNewPage(pageNum) && !thumbnailsLoaded[pageNum]"
            class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80"
          >
            <Loader class="h-4 w-4 text-blue-600" />
          </div>
          <!-- Top-right: Add page / Remove page (hover only) -->
          <div
            class="absolute top-1 right-1 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
            @click.stop
          >
            <button
              type="button"
              class="p-1 rounded bg-blue-600 hover:bg-blue-700 text-white shadow"
              title="Add page after this"
              @click.stop="addPageAfter(Number(pageNum))"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </button>
            <button
              v-if="pageList.length > 1"
              type="button"
              class="p-1 rounded bg-red-600 hover:bg-red-700 text-white shadow"
              title="Remove this page"
              @click.stop="removePage(Number(pageNum))"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
        <!-- Page Number -->
        <p
          class="mt-1.5 text-center text-xs font-medium transition-colors"
          :class="[
            currentPage === pageNum
              ? 'text-blue-600 dark:text-blue-400'
              : 'text-gray-500 dark:text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-300'
          ]"
        >
          {{ pageNum }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import ResizeHandle from '@/components/global/ResizeHandle.vue'
import { useResizable } from '~/composables/components/useResizable'

const alert = useAlert()
const pdfStore = useWorkingPdfStore()

// Resizable left sidebar (persisted width)
const {
  elementRef,
  isResizable,
  dynamicStyles,
  startResize,
} = useResizable({
  storageKey: 'pdfEditorLeftSidebarWidth',
  defaultWidth: 160,
  minWidth: 120,
  maxWidth: 360,
  direction: 'left',
})
const { 
  content: pdfTemplate,
  form,
  currentPage,
  pageList,
} = storeToRefs(pdfStore)

function isNewPage(pageNum) {
  return pdfStore.isNewPage(pageNum)
}

function addPageAfter(pageNum) {
  pdfStore.addPageAfter(pageNum)
}

function removePage(pageNum) {
  alert.confirm(
    'All zones on this page will be permanently deleted. Are you sure you want to remove this page?',
    () => pdfStore.removePage(pageNum)
  )
}

// PDF state
const pdfDoc = shallowRef(null)
const pdfjsLibRef = shallowRef(null)
const canvasRefs = ref({})
const thumbnailsLoaded = ref({})

// Set canvas ref for each page
const setCanvasRef = (el, pageNum) => {
  if (el) {
    canvasRefs.value[pageNum] = el
  }
}

// Select page
const selectPage = (pageNum) => {
  pdfStore.setCurrentPage(pageNum)
  pdfStore.setSelectedZone(null)
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

// Load PDF and render thumbnails
const loadPdf = async () => {
  if (!pdfTemplate.value?.id) return
  
  try {
    const pdfjsLib = await initPdfJs()
    
    const config = useRuntimeConfig()
    const authStore = useAuthStore()
    const apiBase = config.public.apiBase
    const url = `${apiBase}open/forms/${form.value.id}/pdf-templates/${pdfTemplate.value.id}/download`
    
    const loadingTask = pdfjsLib.getDocument({
      url,
      httpHeaders: {
        'Authorization': `Bearer ${authStore.token}`,
      },
    })
    pdfDoc.value = await loadingTask.promise
    
    // Render all thumbnails
    await renderAllThumbnails()
  } catch (err) {
    console.error('Failed to load PDF for thumbnails:', err)
  }
}

// Render all page thumbnails (skip new/blank pages)
const renderAllThumbnails = async () => {
  if (!pdfDoc.value) return
  for (const pageNum of pageList.value) {
    if (pdfStore.isNewPage(pageNum)) continue
    await renderThumbnail(pageNum)
  }
}

// Render single thumbnail (pageNum is logical)
const renderThumbnail = async (pageNum) => {
  if (!pdfDoc.value) return
  
  const canvas = canvasRefs.value[pageNum]
  if (!canvas) {
    await new Promise(resolve => setTimeout(resolve, 50))
    if (canvasRefs.value[pageNum]) {
      await renderThumbnail(pageNum)
    }
    return
  }
  
  const physicalPage = pdfStore.getPhysicalPageNumber(pageNum)
  if (physicalPage == null) return
  try {
    const page = await pdfDoc.value.getPage(physicalPage)
    // Use a smaller scale for thumbnails
    const viewport = page.getViewport({ scale: 0.3 })
    
    const context = canvas.getContext('2d')
    canvas.height = viewport.height
    canvas.width = viewport.width
    
    await page.render({
      canvasContext: context,
      viewport
    }).promise
    
    thumbnailsLoaded.value[pageNum] = true
  } catch (err) {
    console.error(`Failed to render thumbnail for page ${pageNum}:`, err)
  }
}

// When template id is set/changed: load PDF from server
watch(() => pdfTemplate.value?.id, loadPdf, { immediate: true })

// When page_count or new_pages change (add/remove page): refresh thumbnail state and re-render list
watch(
  () => ({
    page_count: pdfTemplate.value?.page_count,
    new_pages: pdfTemplate.value?.new_pages,
  }),
  () => {
    thumbnailsLoaded.value = {}
    if (pdfDoc.value) {
      nextTick(() => renderAllThumbnails())
    }
  },
  { deep: true }
)

// Re-render thumbnails when canvasRefs become available
watch(canvasRefs, async () => {
  if (!pdfDoc.value) return
  for (const pageNum of pageList.value) {
    if (pdfStore.isNewPage(pageNum)) continue
    if (!thumbnailsLoaded.value[pageNum] && canvasRefs.value[pageNum]) {
      await renderThumbnail(pageNum)
    }
  }
}, { deep: true })
</script>
