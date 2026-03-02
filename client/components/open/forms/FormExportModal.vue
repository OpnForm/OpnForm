<template>
  <div>
    <!-- Export Button -->
    <UButton
      size="sm"
      color="neutral"
      variant="ghost"
      label="Export"
      :loading="isExporting"
      @click="startExport"
    />

    <!-- Export Progress Modal -->
    <UModal v-model:open="showModal">
      <template #content>         
        <div class="p-6 space-y-4">
          <VTransition name="fade">
            <!-- Queued State -->
            <div v-if="exportStatus === 'queued'" key="queued" class="text-center space-y-4">
              <div class="w-16 h-16 mx-auto relative">
                <!-- Animated pulse ring -->
                <div class="absolute inset-0 bg-blue-100 rounded-full animate-ping opacity-75"></div>
                <div class="relative w-full h-full bg-blue-200 rounded-full flex items-center justify-center">
                  <UIcon name="i-heroicons-clock" class="w-8 h-8 text-blue-600" />
                </div>
              </div>
              <div class="space-y-2">
                <p class="text-neutral-700 font-medium">Export queued</p>
                <p class="text-sm text-neutral-500">Waiting for processing to begin...</p>
              </div>
            </div>

            <!-- Progress Bar -->
            <div v-else-if="exportStatus === 'processing'" key="processing" class="text-center space-y-4">
              <div class="w-20 h-20 mx-auto relative">
                <!-- Spinning progress circle -->
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 64 64">
                  <circle cx="32" cy="32" r="28" fill="none" stroke="#e5e7eb" stroke-width="4"></circle>
                  <circle 
                    cx="32" cy="32" r="28" fill="none" stroke="#3b82f6" stroke-width="4"
                    stroke-linecap="round"
                    stroke-dasharray="175.93"
                    :stroke-dashoffset="175.93 - (175.93 * smoothProgress / 100)"
                    class="transition-all duration-500 ease-out"
                  ></circle>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                  <span class="text-lg font-bold text-blue-600">{{ Math.round(smoothProgress) }}%</span>
                </div>
              </div>
              <div class="space-y-2">
                <p class="text-neutral-700 font-semibold">Processing submissions</p>
                <p class="text-sm text-neutral-500">This may take a few moments...</p>
              </div>
            </div>

            <!-- Success State -->
            <div v-else-if="exportStatus === 'completed'" key="completed" class="text-center space-y-4">
              <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center">
                <UIcon name="i-heroicons-check" class="w-8 h-8 text-green-600" />
              </div>
              <div class="space-y-2">
                <p class="text-green-600 font-semibold text-lg">Export completed!</p>
                <p class="text-sm text-neutral-600">Your download should start automatically</p>
              </div>
            </div>

            <!-- Error State -->
            <div v-else-if="exportStatus === 'failed'" key="failed" class="text-center space-y-4">
              <div class="w-16 h-16 mx-auto bg-red-100 rounded-full flex items-center justify-center">
                <UIcon name="i-heroicons-exclamation-triangle" class="w-8 h-8 text-red-600" />
              </div>
              <div class="space-y-3">
                <p class="text-red-600 font-semibold text-lg">Export failed</p>
                <p class="text-sm text-neutral-600">{{ exportError }}</p>
                <UButton color="red" @click="closeModal">Close</UButton>
              </div>
            </div>

            <!-- Loading State -->
            <div v-else key="loading" class="text-center space-y-4">
              <div class="w-16 h-16 mx-auto relative">
                <div class="absolute inset-0 bg-neutral-200 rounded-full animate-pulse"></div>
                <div class="relative w-full h-full bg-neutral-100 rounded-full flex items-center justify-center">
                  <UIcon name="i-heroicons-arrow-down-tray" class="w-8 h-8 text-neutral-500 animate-bounce" />
                </div>
              </div>
              <div class="space-y-2">
                <p class="text-neutral-700 font-medium">Starting export</p>
                <p class="text-sm text-neutral-500">Please wait...</p>
              </div>
            </div>
          </VTransition>
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup>
import { formsApi } from '~/api'
import VTransition from '~/components/global/transitions/VTransition.vue'

const props = defineProps({
  form: {
    type: Object,
    required: true
  },
  columns: {
    type: Object,
    required: true
  },
  selectedIds: {
    type: Array,
    default: () => []
  }
})

// Internal state - no props needed
const showModal = ref(false)
const isExporting = ref(false)
const exportJobId = ref(null)
const exportProgress = ref(0)
const exportStatus = ref(null)
const exportError = ref(null)
const pollingInterval = ref(null)

// Continuous progress system
const learnedSpeed = ref(0) // Real speed learned from API calls (% per second)
const smoothProgress = ref(0)
const animationFrame = ref(null)
const lastApiCall = ref(null)
const progressStartTime = ref(null)

const startExport = () => {
  if (isExporting.value) return

  isExporting.value = true
  exportError.value = null
  
  // Reset progress tracking
  smoothProgress.value = 0
  learnedSpeed.value = 0
  lastApiCall.value = null
  progressStartTime.value = Date.now()
  
  const payload = {
    columns: props.columns
  }

  if (props.selectedIds.length > 0) {
    payload.submissionIds = props.selectedIds
  }

  formsApi.submissions.export(props.form.id, payload).then(response => {
    // Check if this is an async export
    if (response.is_async) {
      // Start async export with polling
      exportJobId.value = response.job_id
      exportStatus.value = 'queued'
      exportProgress.value = 0
      showModal.value = true
      startPolling()
    } else {
      // Handle sync export (direct download)
      handleDirectDownload(response)
      isExporting.value = false
    }
  }).catch((error) => {
    console.error(error)
    exportError.value = error.response?.data?.message || 'Export failed'
    exportStatus.value = 'failed'
    showModal.value = true
    isExporting.value = false
  })
}

const handleDirectDownload = (data) => {
  // Convert string to Blob if needed
  let blob
  if (typeof data === 'string') {
    blob = new Blob([data], { type: 'text/csv;charset=utf-8;' })
  } else if (data instanceof Blob) {
    blob = data
  } else {
    throw new Error('Invalid export data format')
  }
  
  const filename = `${props.form.slug}-${Date.now()}-submissions.csv`
  const a = document.createElement("a")
  document.body.appendChild(a)
  a.style = "display: none"
  const url = window.URL.createObjectURL(blob)
  a.href = url
  a.download = filename
  a.click()
  window.URL.revokeObjectURL(url)
  document.body.removeChild(a)
}

const startPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
  }
  
  pollingInterval.value = setInterval(() => {
    checkExportStatus()
  }, 5000) // Poll every 5 seconds
  
  // Initial check
  checkExportStatus()
}

// Start smooth continuous progress when processing begins
watch(exportStatus, (newStatus) => {
  if (newStatus === 'processing') {
    startContinuousProgress()
  } else {
    stopProgressAnimation()
  }
})

const checkExportStatus = () => {
  if (!exportJobId.value) return
  
  formsApi.submissions.exportStatus(props.form.id, exportJobId.value)
    .then(response => {
      exportStatus.value = response.status
      const newProgress = response.progress || 0
      
      // Update our learned speed from real API data
      if (exportStatus.value === 'processing') {
        updateProgressWithApiData(newProgress)
      } else {
        exportProgress.value = newProgress
        smoothProgress.value = newProgress
      }
      
      if (response.status === 'completed') {
        stopPolling()
        stopProgressAnimation()
        isExporting.value = false
        smoothProgress.value = 100
        
        if (response.file_url) {
          // Download the file
          const a = document.createElement("a")
          document.body.appendChild(a)
          a.style = "display: none"
          a.href = response.file_url
          a.download = `${props.form.slug}-submissions.csv`
          a.click()
          document.body.removeChild(a)
          
          // Close modal after a short delay
          setTimeout(() => {
            closeModal()
          }, 1000)
        }
      } else if (response.status === 'failed') {
        stopPolling()
        stopProgressAnimation()
        isExporting.value = false
        exportError.value = response.error_message || 'Export failed'
      }
    })
    .catch(error => {
      console.error('Polling error:', error)
      stopPolling()
      stopProgressAnimation()
      isExporting.value = false
      exportError.value = 'Failed to check export status'
    })
}

const updateProgressWithApiData = (newProgress) => {
  const now = Date.now()
  const progressDiff = newProgress - exportProgress.value
  
  if (progressDiff > 0 && lastApiCall.value) {
    // Calculate actual time elapsed since last API call
    const timeElapsed = (now - lastApiCall.value) / 1000 // seconds
    
    // Calculate real progress speed (% per second)
    const realSpeed = progressDiff / timeElapsed
    
    // Update learned speed (use moving average for stability)
    if (learnedSpeed.value === 0) {
      learnedSpeed.value = realSpeed
    } else {
      // Weighted average: 70% new data, 30% previous
      learnedSpeed.value = (realSpeed * 0.7) + (learnedSpeed.value * 0.3)
    }
    
    // If our continuous progress is behind the real progress, catch up quickly
    if (smoothProgress.value < newProgress - 5) {
      smoothProgress.value = newProgress - 2 // Stay slightly behind real progress
    }
    
    exportProgress.value = newProgress
  }
  
  lastApiCall.value = now
}

// Truly continuous progress that never stops moving
const startContinuousProgress = () => {
  let lastFrameTime = Date.now()
  
  const animate = () => {
    if (exportStatus.value === 'processing' && smoothProgress.value < 95) {
      const now = Date.now()
      const deltaTime = (now - lastFrameTime) / 1000 // seconds since last frame
      lastFrameTime = now
      
      // Calculate current speed based on progress phase
      let currentSpeed = 0
      
      if (smoothProgress.value < 5) {
        // Phase 1: Initial slow progress (0-5%)
        currentSpeed = 0.2 // 0.2% per second - very slow but visible
      } else if (smoothProgress.value < 15 && learnedSpeed.value === 0) {
        // Phase 2: Still learning, slightly faster
        currentSpeed = 0.4 // 0.4% per second
      } else if (learnedSpeed.value > 0) {
        // Phase 3: Use learned speed (conservative)
        currentSpeed = learnedSpeed.value * 0.5 // 50% of real speed
        
        if (smoothProgress.value > 30) {
          // Phase 4: More confident, use 70% of learned speed
          currentSpeed = learnedSpeed.value * 0.7
        }
        
        if (smoothProgress.value > 85) {
          // Phase 5: Slow down near completion
          const slowdownFactor = Math.max(0.2, (95 - smoothProgress.value) / 10)
          currentSpeed *= slowdownFactor
        }
      } else {
        // Fallback: slow but steady progress
        currentSpeed = 0.3
      }
      
      // ALWAYS move forward (this is the key!)
      const increment = currentSpeed * deltaTime
      smoothProgress.value = Math.min(smoothProgress.value + increment, 95)
      
      animationFrame.value = requestAnimationFrame(animate)
    }
  }
  
  if (animationFrame.value) {
    cancelAnimationFrame(animationFrame.value)
  }
  animationFrame.value = requestAnimationFrame(animate)
}

const stopProgressAnimation = () => {
  if (animationFrame.value) {
    cancelAnimationFrame(animationFrame.value)
    animationFrame.value = null
  }
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

const closeModal = () => {
  showModal.value = false
  exportJobId.value = null
  exportStatus.value = null
  exportProgress.value = 0
  smoothProgress.value = 0
  learnedSpeed.value = 0
  lastApiCall.value = null
  progressStartTime.value = null
  exportError.value = null
  stopPolling()
  stopProgressAnimation()
}

// Cleanup on unmount
onUnmounted(() => {
  stopPolling()
  stopProgressAnimation()
})
</script>