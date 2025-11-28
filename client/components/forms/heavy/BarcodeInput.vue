<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div
      v-if="isScanning"
      class="relative w-full"
    >
      <ClientOnly>
      <CameraUpload
        :is-barcode-mode="true"
        :decoders="decoders"
        @stop-webcam="stopScanning"
        @barcode-detected="handleBarcodeDetected"
      />
      </ClientOnly>
    </div>

    <div
      v-else-if="scannedValue"
      class="flex items-center justify-between border border-neutral-300 dark:border-neutral-600 w-full bg-white text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300 rounded-lg px-4 py-2"
    >
      <div class="flex-1 break-all">
        {{ scannedValue }}
      </div>
      <button
        v-if="!disabled"
        type="button"
        class="pt-1 text-neutral-400 hover:text-neutral-600"
        @click="clearValue"
      >
        <Icon
          name="i-heroicons-x-mark-20-solid"
          class="h-5 w-5"
        />
      </button>
    </div>
          
    <div
      v-else
      :style="inputStyle"
      class="cursor-pointer"
      :class="ui.container({ class: props.ui?.slots?.container })"
      tabindex="0"
      role="button"
      aria-label="Click to open a camera"
      @click="startScanning"
      @keydown.enter="handleEnterPress"
    >
      <div class="flex w-full items-center justify-center">
        <div class="text-center">
          <template v-if="!scannedValue && !isScanning">
            <div class="text-neutral-500 w-full flex justify-center">
              <Icon
                name="i-material-symbols-barcode-scanner-rounded"
                class="w-6 h-6"
              />
            </div>

            <p class="mt-2 text-sm text-neutral-500 font-medium select-none">
              {{ $t('forms.barcodeInput.clickToOpenCamera') }}
            </p>
          </template>
        </div>
      </div>
    </div>
    
    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import { inputProps, useFormInput } from '../useFormInput.js'
import CameraUpload from './components/CameraUpload.vue'
import { fileInputTheme } from '~/lib/forms/themes/file-input.theme.js'

export default {
  name: 'BarcodeInput',
  components: { CameraUpload },

  props: {
    ...inputProps,
    decoders: {
      type: Array,
      default: () => []
    },
    preventEnter: {type: Boolean, default: true},
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: fileInputTheme
    })
    return {
      ...formInput,
      props
    }
  },

  data: () => ({
    isScanning: false,
    scannedValue: null
  }),

  watch: {
    scannedValue: {
      handler(value) {
        this.compVal = value
      },
      immediate: true
    }
  },

  beforeUnmount() {
    this.stopScanning()
  },

  methods: {
    handleEnterPress(event) {
      if (this.preventEnter) {
        event.preventDefault()
        this.startScanning()
      }
    },
    startScanning() {
      if (this.disabled) return
      this.isScanning = true
    },

    stopScanning() {
      this.isScanning = false
    },

    handleBarcodeDetected(code) {
      this.scannedValue = code
      this.stopScanning()
    },

    clearValue() {
      this.scannedValue = null
    }
  }
}
</script>

<style scoped>
video {
  /* Ensure the video displays properly */
  max-width: 100%;
  height: auto;
}
</style> 
