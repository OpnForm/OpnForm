<template>
  <div
    class="border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer"
    :class="{
      'border-blue-400 bg-blue-50': isDragging,
      'border-gray-300 hover:border-gray-400': !isDragging,
      'opacity-50 cursor-not-allowed': loading
    }"
    @click="openFilePicker"
    @dragover.prevent="onDragOver"
    @dragleave.prevent="onDragLeave"
    @drop.prevent="onDrop"
  >
    <input
      ref="fileInput"
      type="file"
      accept=".pdf,application/pdf"
      class="hidden"
      @change="onFileSelected"
    >
    
    <div v-if="loading">
      <Loader class="mx-auto h-8 w-8 text-blue-600" />
      <p class="mt-2 text-sm text-gray-600">
        Uploading PDF template...
      </p>
    </div>
    
    <div v-else>
      <Icon
        name="material-symbols:picture-as-pdf-rounded"
        class="mx-auto h-12 w-12 text-gray-400"
      />
      <p class="mt-2 text-sm font-medium text-gray-700">
        Drop PDF here or click to browse
      </p>
      <p class="mt-1 text-xs text-gray-500">
        .pdf up to 10MB
      </p>
    </div>
  </div>
  
  <p
    v-if="error"
    class="mt-2 text-sm text-red-600"
  >
    {{ error }}
  </p>
</template>

<script setup>
import { formsApi } from '~/api/forms'

const props = defineProps({
  formId: { type: [Number, String], required: true }
})

const emit = defineEmits(['uploaded'])

const fileInput = ref(null)
const loading = ref(false)
const isDragging = ref(false)
const error = ref('')

const openFilePicker = () => {
  if (loading.value) return
  fileInput.value?.click()
}

const onDragOver = () => {
  isDragging.value = true
}

const onDragLeave = () => {
  isDragging.value = false
}

const onDrop = (event) => {
  isDragging.value = false
  const files = event.dataTransfer.files
  if (files.length > 0) {
    handleFile(files[0])
  }
}

const onFileSelected = (event) => {
  const files = event.target.files
  if (files.length > 0) {
    handleFile(files[0])
  }
  // Reset input
  event.target.value = ''
}

const handleFile = async (file) => {
  error.value = ''
  
  // Validate file type
  if (file.type !== 'application/pdf') {
    error.value = 'Please select a PDF file.'
    return
  }
  
  loading.value = true
  
  try {
    const formData = new FormData()
    formData.append('file', file)
    
    // Don't set Content-Type header manually - browser sets it automatically with boundary
    const response = await formsApi.pdfTemplates.upload(props.formId, formData)
    
    emit('uploaded', response.data)
  } catch (err) {
    console.error('Failed to upload PDF template:', err)
    error.value = err.response?._data?.message || 'Failed to upload PDF template. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
