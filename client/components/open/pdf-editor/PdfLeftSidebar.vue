<template>
  <div class="w-40 bg-white dark:bg-gray-800 border-r border-gray-300 dark:border-gray-700 flex flex-col overflow-hidden">
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
        v-for="pageNum in pdfTemplate.page_count"
        :key="pageNum"
        class="cursor-pointer group"
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
            :ref="el => setCanvasRef(el, pageNum)"
            class="w-full h-auto bg-white"
          />
          <!-- Loading overlay -->
          <div
            v-if="!thumbnailsLoaded[pageNum]"
            class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80"
          >
            <Loader class="h-4 w-4 text-blue-600" />
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
const pdfStore = useWorkingPdfStore()
const { 
  content: pdfTemplate,
  form,
  currentPage,
} = storeToRefs(pdfStore)

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

// Render all page thumbnails
const renderAllThumbnails = async () => {
  if (!pdfDoc.value) return
  
  for (let pageNum = 1; pageNum <= pdfTemplate.value.page_count; pageNum++) {
    await renderThumbnail(pageNum)
  }
}

// Render single thumbnail
const renderThumbnail = async (pageNum) => {
  if (!pdfDoc.value) return
  
  const canvas = canvasRefs.value[pageNum]
  if (!canvas) {
    // Canvas not ready yet, retry after a short delay
    await new Promise(resolve => setTimeout(resolve, 50))
    if (canvasRefs.value[pageNum]) {
      await renderThumbnail(pageNum)
    }
    return
  }
  
  try {
    const page = await pdfDoc.value.getPage(pageNum)
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

// Watch for template changes
watch(() => pdfTemplate.value, loadPdf, { immediate: true })

// Re-render thumbnails when canvasRefs become available
watch(canvasRefs, async () => {
  if (pdfDoc.value && Object.keys(canvasRefs.value).length > 0) {
    for (let pageNum = 1; pageNum <= pdfTemplate.value.page_count; pageNum++) {
      if (!thumbnailsLoaded.value[pageNum] && canvasRefs.value[pageNum]) {
        await renderThumbnail(pageNum)
      }
    }
  }
}, { deep: true })
</script>
