<template>
  <div
    class="relative overflow-hidden"
    :class="variantSlots.container()"
  >
    <video
      ref="webcamRef"
      autoplay
      playsinline
      muted
      :class="[
        { hidden: !isCapturing }, 
        'w-full h-full object-cover bg-neutral-500'
      ]"
      webkit-playsinline
    />
    <canvas
      ref="canvasRef"
      :class="[
        { hidden: !capturedImage },
        'w-full h-full object-cover'
      ]"
    />
    
    <!-- Barcode scanning overlay -->
    <div 
      v-if="isCapturing && isBarcodeMode" 
      class="absolute inset-0 pointer-events-none"
    >
      <!-- Scanning area (transparent window) -->
      <div
        class="absolute inset-0 flex items-strech justify-center px-8 py-12"
      >
        <div class="flex-grow w-full relative">
          <!-- Corner indicators -->
          <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 rounded-tl-md border-white" />
          <div class="absolute top-0 right-0 w-8 h-8 border-t-2 border-r-2 rounded-tr-md border-white" />
          <div class="absolute bottom-0 left-0 w-8 h-8 border-b-2 border-l-2 rounded-bl-md border-white" />
          <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 rounded-br-md border-white" />
        </div>
      </div>
    </div>
    
    <div
      v-if="cameraPermissionStatus === 'allowed'"
      class="absolute inset-x-0 grid place-content-center bottom-2"
    >
      <div
        v-if="isCapturing"
        class="p-2 px-4 flex items-center justify-center text-xs space-x-3"
      >
        <span
          v-if="!isBarcodeMode"
                          class="cursor-pointer rounded-full w-12 h-12 border-2 grid place-content-center bg-white/20 backdrop-blur-xs shadow-sm"
          @click="processCapturedImage"
        >
          <span
            class="cursor-pointer bg-white rounded-full w-8 h-8 grid place-content-center"
          />
        </span>
        <span
          class="text-white cursor-pointer bg-black/40 rounded-full backdrop-blur-xs shadow-sm w-12 h-12 grid place-content-center"
          @click="cancelCamera"
        >
          <Icon
            name="heroicons:x-mark"
            class="w-6 h-6"
          />
        </span>
        <span
          v-if="isMobileDevice"
          class="text-white cursor-pointer bg-black/40 rounded-full backdrop-blur-xs shadow-sm w-12 h-12 grid place-content-center"
          @click="switchCamera"
        >
          <Icon
            name="heroicons:arrow-path"
            class="w-6 h-6"
          />
        </span>
      </div>
    </div>
    <div
      v-else-if="cameraPermissionStatus === 'blocked'"
      class="absolute p-5 top-0 inset-x-0 flex flex-col items-center justify-center space-y-4 text-center border border-neutral-400/30 h-full"
      :class="variantSlots.container()"
      @click="openCameraUpload"
    >
      <Icon
        :name="isBarcodeMode ? 'i-material-symbols-barcode-scanner-rounded' : 'i-heroicons-camera'"
        class="w-6 h-6"
      />
      <p class="text-center font-bold">
        {{ $t('forms.cameraUpload.allowCameraPermission') }}
      </p>
      <p class="text-xs">
        {{ $t('forms.cameraUpload.allowCameraPermissionDescription') }}
      </p>
      <UButton
        color="neutral"
        variant="outline"
        @click.stop="cancelCamera"
      >
        {{ $t('forms.cameraUpload.gotIt') }}
      </UButton>
    </div>

    <div
      v-else-if="cameraPermissionStatus === 'loading'"
      class="absolute p-5 top-0 inset-x-0 flex flex-col items-center justify-center space-y-4 text-center border border-neutral-400/30 h-full"
      :class="variantSlots.container()"
    >
      <div class="w-6 h-6">
        <Loader />
      </div>
    </div>
    <div
      v-else
      class="absolute p-5 top-0 inset-x-0 flex flex-col items-center justify-center space-y-4 text-center border border-neutral-400/30 h-full"
      :class="variantSlots.container()"
      @click="openCameraUpload"
    >
      <Icon
        :name="isBarcodeMode ? 'i-material-symbols-barcode-scanner-rounded' : 'heroicons:video-camera-slash'"
        class="w-6 h-6"
      />
      <p class="text-center font-bold">
        {{ $t('forms.cameraUpload.cameraDeviceError') }}
      </p>
      <p class="text-xs">
        {{ $t('forms.cameraUpload.cameraDeviceErrorDescription') }}
      </p>
      <UButton
        color="neutral"
        variant="outline"
        @click.stop="cancelCamera"
      >
        {{ $t('forms.cameraUpload.goBack') }}
      </UButton>
    </div>
  </div>
</template>

<script>
import Webcam from "webcam-easy"
import { BrowserMultiFormatReader, DecodeHintType, BarcodeFormat } from '@zxing/library'
import { tv } from 'tailwind-variants'
import { fileInputTheme } from '~/lib/forms/themes/file-input.theme.js'

export default {
  name: "CameraUpload",
  props: {
    isBarcodeMode: {
      type: Boolean,
      default: false
    },
    decoders: {
      type: Array,
      default: () => []
    }
  },
  emits: ['stopWebcam', 'uploadImage', 'barcodeDetected'],
  data: () => ({
    webcam: null,
    isCapturing: false,
    capturedImage: null,
    cameraPermissionStatus: "loading",
    zxingReader: null,
    currentFacingMode: 'user',
    mediaStream: null
  }),
  setup() {
    const themeName = inject('formTheme', null)
    const size = inject('formSize', null)
    const borderRadius = inject('formBorderRadius', null)
    const resolvedTheme = computed(() => themeName?.value || 'default')
    const resolvedSize = computed(() => size?.value || 'md')
    const resolvedBorderRadius = computed(() => borderRadius?.value || 'small')
    const variants = computed(() => tv(fileInputTheme, {}))
    const variantSlots = computed(() => variants.value({
      theme: resolvedTheme.value,
      size: resolvedSize.value,
      borderRadius: resolvedBorderRadius.value
    }))
    return { variantSlots }
  },
  computed: {
    videoDisplay() {
      return this.isCapturing ? "" : "hidden"
    },
    canvasDisplay() {
      return !this.isCapturing && this.capturedImage ? "" : "hidden"
    },
    isMobileDevice() {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
    }
  },
  mounted() {
    // For regular camera mode, we still need the webcam.js setup
    if (!this.isBarcodeMode) {
      this.webcam = new Webcam(this.$refs.webcamRef, "user", this.$refs.canvasRef)
    }
    this.openCameraUpload()
  },

  beforeUnmount() {
    this.cleanupCurrentStream()
  },

  methods: {
    async cleanupCurrentStream() {
      if (this.zxingReader) {
        this.zxingReader.reset()
        this.zxingReader = null
      }

      if (this.webcam) {
        this.webcam.stop()
        this.webcam = null
      }

      // Clean up video element if needed
      if (this.$refs.webcamRef && this.$refs.webcamRef.srcObject) {
        const tracks = this.$refs.webcamRef.srcObject.getTracks()
        tracks.forEach(track => track.stop())
        this.$refs.webcamRef.srcObject = null
      }
    },

    async switchCamera() {
      if (!this.isMobileDevice) return
      
      try {
        // Stop current camera and clean up resources
        this.cleanupCurrentStream()

        // Toggle facing mode
        this.currentFacingMode = this.currentFacingMode === 'user' ? 'environment' : 'user'

        // Restart camera
        if (this.isBarcodeMode) {
          setTimeout(() => {
            this.initZxingDirect()
          }, 500)
        } else {
          await this.openCameraUpload()
        }
      } catch (error) {
        console.error('Error switching camera:', error)
        this.cameraPermissionStatus = "unknown"
      }
    },

    async openCameraUpload() {
      this.isCapturing = true
      this.capturedImage = null

      try {
        if (this.isBarcodeMode) {
          // For barcode mode, let ZXing handle everything
          this.cameraPermissionStatus = "allowed"
          setTimeout(() => {
            this.initZxingDirect()
          }, 500)
          return
        }

        // Regular camera mode - use existing logic
        // Determine the facing mode to use
        let facingMode = this.currentFacingMode

        // Create constraints based on device capabilities
        const constraints = {
          audio: false,
          video: {
            width: { ideal: 1280 },
            height: { ideal: 720 }
          }
        }

        // Use exact constraints for mobile devices to ensure proper camera selection
        if (this.isMobileDevice) {
          constraints.video.facingMode = { exact: facingMode }
        } else {
          constraints.video.facingMode = facingMode
        }

        // Try to get the stream with the specified constraints
        let stream
        try {
          stream = await navigator.mediaDevices.getUserMedia(constraints)
        } catch (err) {
          // If exact constraint fails, fall back to preference
          if (this.isMobileDevice && err.name === 'OverconstrainedError') {
            constraints.video.facingMode = facingMode
            stream = await navigator.mediaDevices.getUserMedia(constraints)
          } else {
            throw err
          }
        }

        this.mediaStream = stream  // Store the stream reference
        this.$refs.webcamRef.srcObject = stream
        
        this.webcam = new Webcam(
          this.$refs.webcamRef,
          facingMode,
          this.$refs.canvasRef
        )
        
        await new Promise((resolve) => {
          this.$refs.webcamRef.onloadedmetadata = () => {
            this.$refs.webcamRef.play().then(() => {
              resolve()
            }).catch(err => {
              console.error('Error playing video:', err)
              resolve() // Continue anyway
            })
          }
        })

        this.cameraPermissionStatus = "allowed"
      } catch (err) {
        console.error('Camera error:', err)
        if (err.name === 'NotAllowedError' || err.toString().includes('Permission denied')) {
          this.cameraPermissionStatus = "blocked"
        } else {
          this.cameraPermissionStatus = "unknown"
        }
      }
    },
    initZxingDirect() {
      if (this.zxingReader) {
        this.zxingReader.reset()
        this.zxingReader = null
      }

      const hints = new Map()
      const formats = (this.decoders || []).map(decoder => {
        // Map decoder strings to BarcodeFormat enum values
        // Remove _reader suffix for mapping
        const cleanDecoder = decoder.replace('_reader', '').toLowerCase()
        
        switch(cleanDecoder) {
          case 'ean_8': return BarcodeFormat.EAN_8
          case 'ean': 
          case 'ean_13': return BarcodeFormat.EAN_13
          case 'upc':
          case 'upc_a': return BarcodeFormat.UPC_A
          case 'upc_e': return BarcodeFormat.UPC_E
          case 'code_39': return BarcodeFormat.CODE_39
          case 'code_93': return BarcodeFormat.CODE_93
          case 'code_128': return BarcodeFormat.CODE_128
          case 'codabar': return BarcodeFormat.CODABAR
          case 'itf': return BarcodeFormat.ITF
          case 'qr':
          case 'qr_code': return BarcodeFormat.QR_CODE
          case 'data_matrix': return BarcodeFormat.DATA_MATRIX
          case 'aztec': return BarcodeFormat.AZTEC
          case 'pdf_417': return BarcodeFormat.PDF_417
          default: 
            console.warn('Unsupported barcode format:', decoder)
            return null
        }
      }).filter(format => format !== null)

      if (formats.length > 0) {
        hints.set(DecodeHintType.POSSIBLE_FORMATS, formats)
      }

      this.zxingReader = new BrowserMultiFormatReader(hints)

      // Use simple constraints approach instead of device enumeration
      const facingMode = this.isMobileDevice && this.currentFacingMode === 'user' ? 'environment' : this.currentFacingMode
      const constraints = {
        audio: false,
        video: {
          facingMode: facingMode,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      }

      // Use ZXing's decodeFromConstraints method
      this.zxingReader.decodeFromConstraints(constraints, this.$refs.webcamRef, (result, error) => {
        if (result) {
          this.$emit('barcodeDetected', result.text)
        }
        // Don't log NotFoundException errors - they're expected during scanning
        // Only log other types of errors
        else if (error && error.name && !error.name.includes('NotFoundException') && !error.message?.includes('No MultiFormat Readers')) {
          console.error('ZXing decoding error:', error.name, error.message)
        }
      })
      .then(() => {
        this.cameraPermissionStatus = "allowed"
      })
      .catch(err => {
        console.error('Camera error in ZXing Direct:', err)
        if (err.name === 'NotAllowedError' || err.toString().includes('Permission denied')) {
          this.cameraPermissionStatus = "blocked"
        } else {
          this.cameraPermissionStatus = "unknown"
        }
      })
    },
    cancelCamera() {
      this.isCapturing = false
      this.capturedImage = null
      this.cleanupCurrentStream()  // Use the cleanup method
      this.$emit("stopWebcam")
    },
    processCapturedImage() {
      if (!this.webcam) {
        return
      }
      
      this.capturedImage = this.webcam.snap()
      this.isCapturing = false
      this.webcam.stop()
      const byteCharacters = atob(this.capturedImage.split(",")[1])
      const byteArrays = []
      for (let offset = 0; offset < byteCharacters.length; offset += 512) {
        const slice = byteCharacters.slice(offset, offset + 512)

        const byteNumbers = new Array(slice.length)
        for (let i = 0; i < slice.length; i++) {
          byteNumbers[i] = slice.charCodeAt(i)
        }

        const byteArray = new Uint8Array(byteNumbers)
        byteArrays.push(byteArray)
      }

      const blob = new Blob(byteArrays, { type: "image/png" })
      const filename = Date.now()
      const file = new File([blob], `${filename}.png`, { type: "image/png" })
      this.$emit("uploadImage", file)
    },
  },
}
</script>
