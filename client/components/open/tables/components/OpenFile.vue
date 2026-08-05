<template>
  <div class="text-xs">
    <span
      v-for="file in parsedFiles"
      :key="file.file_url"
      class="whitespace-nowrap rounded-md transition-colors hover:decoration-none"
      :class="{
        'open-file text-neutral-700 dark:text-neutral-300 truncate': !file.is_image,
        'open-file-img': file.is_image,
      }"
    >
      <button
        v-if="file.is_image"
        type="button"
        class="block h-8 w-8 overflow-hidden rounded border border-neutral-200 transition-opacity hover:opacity-80 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
        :aria-label="`Preview ${file.displayed_file_name}`"
        @click="openFilePreview(file)"
      >
        <img
          class="h-full w-full object-cover"
          :src="file.file_url"
          :alt="file.file_name"
          @error="failedImages.push(file.file_url)"
        >
      </button>
      <button
        v-else-if="file.is_pdf"
        type="button"
        class="rounded focus:outline-hidden focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
        :aria-label="`Preview ${file.displayed_file_name}`"
        @click="openFilePreview(file)"
      >
        <UBadge
          size="sm"
          color="neutral"
          variant="ghost"
          icon="i-material-symbols-picture-as-pdf-rounded"
        >
          {{ file.displayed_file_name }}
        </UBadge>
      </button>
      <UBadge
        v-else
        size="sm"
        color="neutral"
        variant="ghost"
      >
        <a
          :href="file.file_url"
          target="_blank"
          rel="nofollow"
          download
        >
          {{ file.displayed_file_name }}
        </a>
      </UBadge>
    </span>

    <UModal
      v-model:open="isFilePreviewOpen"
      :title="selectedFile?.file_name"
      :description="selectedFile ? `Preview ${selectedFile.file_name}` : undefined"
      :ui="{ content: 'sm:max-w-5xl', body: 'p-0!', description: 'sr-only' }"
      :dismissible="true"
    >
      <template #actions>
        <UButton
          :href="selectedFile?.file_url"
          :download="selectedFile?.file_name"
          target="_blank"
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-heroicons-arrow-down-tray-20-solid"
          label="Download"
        />
      </template>

      <template #body>
        <div
          v-if="selectedFile?.is_image"
          class="flex min-h-64 items-center justify-center bg-neutral-950"
        >
          <img
            class="max-h-[75vh] w-full object-contain"
            :src="selectedFile.file_url"
            :alt="selectedFile.file_name"
          >
        </div>
        <div
          v-else-if="selectedFile?.is_pdf"
          class="h-[75vh] overflow-y-auto bg-neutral-200 p-4 dark:bg-neutral-900"
        >
          <div
            v-if="hasPdfError"
            class="flex h-full flex-col items-center justify-center gap-3 text-center text-neutral-600 dark:text-neutral-300"
          >
            <Icon
              name="i-heroicons-document-text"
              class="h-10 w-10"
            />
            <span>Unable to preview this PDF.</span>
          </div>
          <div
            v-else
            class="relative min-h-full"
          >
            <div
              v-if="isPdfLoading"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 text-neutral-600 dark:text-neutral-300"
            >
              <Loader class="h-8 w-8 text-blue-600" />
              <span>Loading PDF...</span>
            </div>
            <div
              class="mx-auto flex max-w-4xl flex-col items-center gap-4 transition-opacity"
              :class="isPdfLoading ? 'opacity-0' : 'opacity-100'"
            >
              <div
                v-if="hasHiddenPdfPages"
                class="w-full rounded bg-white px-4 py-3 text-center text-sm text-neutral-600 shadow dark:bg-neutral-800 dark:text-neutral-300"
              >
                Showing first {{ pdfPages.length }} of {{ pdfTotalPages }} pages.
              </div>
              <canvas
                v-for="pageNumber in pdfPages"
                :key="pageNumber"
                :ref="(element) => setPdfCanvasRef(element, pageNumber)"
                class="h-auto max-w-full bg-white shadow"
                :aria-label="`Page ${pageNumber}`"
              />
            </div>
          </div>
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup>
const props = defineProps({
  value: {
    type: Array,
    required: false,
  },
  property: {
    type: Object,
    required: false,
    default: null,
  },
})

const PDF_PREVIEW_PAGE_LIMIT = 10

const failedImages = ref([])
const isFilePreviewOpen = ref(false)
const selectedFile = ref(null)
const isPdfLoading = ref(false)
const hasPdfError = ref(false)
const pdfPages = ref([])
const pdfTotalPages = ref(0)
const pdfCanvasRefs = {}
const pdfDocument = shallowRef(null)
const pdfjsLibRef = shallowRef(null)
let pdfLoadId = 0

const openFilePreview = (file) => {
  selectedFile.value = file
  isFilePreviewOpen.value = true
}

const setPdfCanvasRef = (element, pageNumber) => {
  if (element) {
    pdfCanvasRefs[pageNumber] = element
  }
}

const clearPdfCanvasRefs = () => {
  Object.keys(pdfCanvasRefs).forEach((pageNumber) => {
    delete pdfCanvasRefs[pageNumber]
  })
}

const initPdfJs = () => {
  if (pdfjsLibRef.value) {
    return Promise.resolve(pdfjsLibRef.value)
  }

  return Promise.all([
    import("pdfjs-dist"),
    import("pdfjs-dist/build/pdf.worker.min.mjs?url"),
  ]).then(([pdfjsLib, pdfjsWorker]) => {
    pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker.default
    pdfjsLibRef.value = pdfjsLib
    return pdfjsLib
  })
}

const renderPdfPage = (document, pageNumber, loadId) => {
  return document.getPage(pageNumber).then((page) => {
    if (loadId !== pdfLoadId) return

    const canvas = pdfCanvasRefs[pageNumber]
    if (!canvas) return

    const viewport = page.getViewport({ scale: 1.5 })
    const context = canvas.getContext("2d")
    if (!context) return

    canvas.width = viewport.width
    canvas.height = viewport.height

    const renderTask = page.render({
      canvasContext: context,
      viewport,
    })

    return renderTask.promise.finally(() => page.cleanup())
  })
}

const renderPdfPages = (document, loadId) => {
  return pdfPages.value.reduce((promise, pageNumber) => {
    return promise.then(() => renderPdfPage(document, pageNumber, loadId))
  }, Promise.resolve())
}

const loadPdfPreview = () => {
  const loadId = ++pdfLoadId
  isPdfLoading.value = true
  hasPdfError.value = false
  pdfPages.value = []
  pdfTotalPages.value = 0
  clearPdfCanvasRefs()

  initPdfJs()
    .then((pdfjsLib) => {
      return pdfjsLib.getDocument(selectedFile.value.file_url).promise
    })
    .then((document) => {
      if (loadId !== pdfLoadId) {
        return document.destroy()
      }

      pdfDocument.value = document
      pdfTotalPages.value = document.numPages
      pdfPages.value = Array.from(
        { length: Math.min(document.numPages, PDF_PREVIEW_PAGE_LIMIT) },
        (_, index) => index + 1,
      )

      return nextTick().then(() => renderPdfPages(document, loadId))
    })
    .catch((error) => {
      if (loadId !== pdfLoadId) return

      console.error("Failed to load PDF preview:", error)
      hasPdfError.value = true
    })
    .finally(() => {
      if (loadId === pdfLoadId) {
        isPdfLoading.value = false
      }
    })
}

const resetPdfPreview = () => {
  pdfLoadId++
  pdfPages.value = []
  pdfTotalPages.value = 0
  hasPdfError.value = false
  isPdfLoading.value = false
  clearPdfCanvasRefs()

  if (pdfDocument.value) {
    pdfDocument.value.destroy()
    pdfDocument.value = null
  }
}

watch(isFilePreviewOpen, (isOpen) => {
  if (isOpen && selectedFile.value?.is_pdf) {
    loadPdfPreview()
  } else if (!isOpen) {
    resetPdfPreview()
  }
})

const hasHiddenPdfPages = computed(() => {
  return pdfTotalPages.value > pdfPages.value.length
})

const parsedFiles = computed(() => {
  return props.value && Array.isArray(props.value)
    ? props.value.map((file) => {
        return {
          file_name: file.file_name,
          file_url: file.file_url,
          displayed_file_name: displayedFileName(file.file_name),
          is_image:
            !failedImages.value.includes(file.file_url) &&
            isImage(file.file_name),
          is_pdf: isPdf(file.file_name),
        }
      })
    : []
})

const isImage = (fileName) => {
  return ["png", "gif", "jpg", "jpeg", "tif"].includes(
    getFileExtension(fileName),
  )
}

const isPdf = (fileName) => {
  return getFileExtension(fileName) === "pdf"
}

const getFileExtension = (fileName) => {
  return fileName?.split(".").pop()?.toLowerCase()
}

const displayedFileName = (fileName) => {
  const extension = fileName.substr(fileName.lastIndexOf(".") + 1)
  const filename = fileName.substr(0, fileName.lastIndexOf("."))

  if (filename.length > 10) {
    return filename.substr(0, 10) + "[...]." + extension
  }
  return filename + "." + extension
}
</script>

<style lang="scss">
.open-file {
  max-width: 120px;
  background-color: #e3e2e0;
}

.dark {
  .open-file {
    background-color: #5a5a5a;
  }
}
</style>
