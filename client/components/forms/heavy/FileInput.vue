<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>
    <div
      v-if="cameraUpload && isInWebcam"
      class="hidden sm:block w-full"
      :class="ui.container({ class: props.ui?.slots?.container })"
    >
      <ClientOnly>
      <CameraUpload
        v-if="cameraUpload"
        @upload-image="cameraFileUpload"
        @stop-webcam="isInWebcam=false"
      />
      </ClientOnly>
    </div>
    <div
      v-else
      :style="inputStyle"
      class="cursor-pointer"
      :class="ui.container({ class: props.ui?.slots?.container })"
      tabindex="0"
      role="button"
      :aria-label="multiple ? 'Choose files or drag here' : 'Choose a file or drag here'"
      @dragover.prevent="uploadDragoverEvent=true"
      @dragleave.prevent="uploadDragoverEvent=false"
      @drop.prevent="onUploadDropEvent"
      @click="openFileUpload"
      @keydown.enter="handleEnterPress"
    >
      <div class="flex w-full items-center justify-center">
        <div
          v-if="loading"
          class="text-neutral-600 dark:text-neutral-400"
        >
          <Loader class="mx-auto h-6 w-6" />
          <p class="mt-2 text-center text-sm text-neutral-500">
            {{ $t('forms.fileInput.uploadingFile') }}
          </p>
        </div>
        <template v-else>
          <div class="text-center">
            <input
              ref="actual-input"
              class="hidden"
              :multiple="multiple"
              type="file"
              :name="name"
              :accept="acceptExtensions"
              @change="manualFileUpload"
            >
            <div
              v-if="files.length"
              class="flex flex-wrap items-center justify-center gap-4"
            >
              <uploaded-file
                v-for="(file, index) in files"
                :key="file.url"
                :file="file"
                :disabled="disabled"
                @remove="clearFile(index)"
              />
            </div>
            <template v-else>
              <div class="text-neutral-500 w-full flex justify-center">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="w-5 h-5"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                  />
                </svg>
              </div>

              <p class="mt-2 text-sm text-neutral-500 font-medium select-none">
                {{ $t('forms.fileInput.chooseFiles', { n: multiple ? 1 : 0 }) }}
              </p>
              <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-400 select-none">
                {{ $t('forms.fileInput.sizeLimit', mbLimit) }}
              </p>
            </template>
          </div>
        </template>
      </div>
      <div class="w-full items-center justify-center mt-2  hidden sm:flex">
        <UButton
          v-if="cameraUpload"
          icon="i-heroicons-camera"
          :loading="loading"
          color="neutral"
          variant="outline"
          class="px-2"
          @click.stop="openWebcam"
        />
      </div>
    </div>

    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import {inputProps, useFormInput} from '../useFormInput.js'
import UploadedFile from './components/UploadedFile.vue'
import CameraUpload from './components/CameraUpload.vue'
import {storeFile} from "~/lib/file-uploads.js"
import { formsApi } from '~/api'
import { fileInputTheme } from '~/lib/forms/themes/file-input.theme.js'

export default {
  name: 'FileInput',
  components: {UploadedFile, CameraUpload},
  mixins: [],
  props: {
    ...inputProps,
    multiple: {type: Boolean, default: true},
    cameraUpload: {type: Boolean, default: false},
    mbLimit: {type: Number, default: 5},
    accept: {type: String, default: ''},
    moveToFormAssets: {type: Boolean, default: false},
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
    files: [],
    uploadDragoverEvent: false,
    loading: false,
    isInWebcam: false
  }),

  computed: {
    currentUrl() {
      return this.form[this.name]
    },
    acceptExtensions() {
      if (!this.accept) {
        return null
      }
      return this.accept
        .split(',')
        .map((i) => {
          if (!i) return null
          return '.' + i.trim()
        })
        .join(',')
    }
  },

  watch: {
    files: {
      deep: true,
      handler(files) {
        this.compVal = files.map((file) => file.url)
      }
    },
    'compVal': {
      deep: true,
      handler(newVal, oldVal) {
        if (!oldVal) {
          this.handleCompValChange()
        }
      }
    }
  },

  mounted() {
    this.handleCompValChange()
  },

  methods: {
    async handleCompValChange() {
      this.loading = true
      if (typeof this.compVal === 'string' || this.compVal instanceof String) {
        await this.getFileFromUrl(this.compVal).then((fileObj) => {
          this.files = [{
            file: fileObj,
            url: this.compVal,
            src: this.getFileSrc(fileObj)
          }]
        })
      } else if (this.compVal && this.compVal.length > 0) {
        const tmpFiles = []
        for (let i = 0; i < this.compVal.length; i++) {
          await this.getFileFromUrl(this.compVal[i]).then((fileObj) => {
            tmpFiles.push({
              file: fileObj,
              url: (typeof this.compVal[i] === 'object') ? this.compVal[i]?.file_url : this.compVal[i],
              src: this.getFileSrc(fileObj)
            })
          })
        }
        this.files = tmpFiles
      }
      this.loading = false
    },
    clearAll() {
      // Revoke object URLs to prevent memory leaks
      this.files.forEach(f => {
        if (f && f.src) {
          URL.revokeObjectURL(f.src)
        }
      })
      this.files = []
    },
    clearFile(index) {
      const f = this.files[index]
      if (f && f.src) {
        URL.revokeObjectURL(f.src)
      }
      this.files.splice(index, 1)
    },
    onUploadDropEvent(e) {
      this.uploadDragoverEvent = false
      this.droppedFiles(e.dataTransfer.files)
    },
    droppedFiles(droppedFiles) {
      if (!droppedFiles || this.disabled) return

      for (let i = 0; i < droppedFiles.length; i++) {
        this.uploadFileToServer(droppedFiles.item(i))
      }
    },
    handleEnterPress(event) {
      if (this.preventEnter) {
        event.preventDefault()
        this.openFileUpload()
      }
    },
    openFileUpload() {
      if (this.disabled || !this.$refs['actual-input']) return
      this.$refs['actual-input'].click()
    },
    manualFileUpload(e) {
      const files = e.target.files
      for (let i = 0; i < files.length; i++) {
        this.uploadFileToServer(files.item(i))
      }
    },
    openWebcam() {
      if (!this.cameraUpload) {
        return
      }
      this.isInWebcam = true
    },
    cameraFileUpload(file) {
      this.isInWebcam = false
      this.isUploading = false
      this.uploadFileToServer(file)
    },
    uploadFileToServer(file) {
      if (this.disabled) return
      this.loading = true
      storeFile(file)
        .then((response) => {
          if (!this.multiple) {
            this.files = []
          }
          if (this.moveToFormAssets) {
            // Move file to permanent storage for form assets
            formsApi.assets.upload({
              type: 'files',
              url: file.name.split('.').slice(0, -1).join('.') + '_' + response.uuid + '.' + response.extension
            }).then(moveFileResponseData => {
              this.files.push({
                file: file,
                url: moveFileResponseData.url,
                src: this.getFileSrc(file)
              })
              this.loading = false
            }).catch(() => {
              this.loading = false
            })
          } else {
            this.files.push({
              file: file,
              url: file.name.split('.').slice(0, -1).join('.') + '_' + response.uuid + '.' + response.extension,
              src: this.getFileSrc(file)
            })
            this.loading = false
          }
        })
        .catch(() => {
          this.clearAll()
          this.loading = false
        })
    },
    async getFileFromUrl(url, defaultType = 'image/jpeg') {
      if (typeof url === 'object') {
        url = url?.file_url
      }
      const response = await fetch(url)
      const data = await response.blob()
      const name = url.replace(/^.*(\\|\/|:)/, '')
      return new File([data], name, {
        type: data.type || defaultType
      })
    },
    getFileSrc(file) {
      if (file.type && file.type.split('/')[0] === 'image') {
        return URL.createObjectURL(file)
      }
      return null
    }
  }
}
</script>
