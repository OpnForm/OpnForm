<template>
  <InputWrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <span class="inline-block w-full rounded-md shadow-xs">
      <button
        type="button"
        aria-haspopup="listbox"
        aria-expanded="true"
        aria-labelledby="listbox-label"
        class="cursor-pointer relative w-full"
        :class="ui.button({ class: props.ui?.slots?.button })"
        :style="inputStyle"
        :disabled="disabled ? true : null"
        @click.prevent="showUploadModal = true"
      >
        <div
          v-if="currentUrl == null"
          class="text-neutral-600 dark:text-neutral-400 flex justify-center"
        >
          <Icon
            name="heroicons:cloud-arrow-up"
            class="h-5 w-5"
          />
          <span class="ml-2">
            Upload
          </span>
        </div>
        <div
          v-else
          class="text-neutral-600 dark:text-neutral-400 flex items-center gap-2"
        >
          <div class="flex-grow min-w-0">
            <EmbedMedia
              :src="currentUrl"
              :theme="resolvedTheme"
              :size="resolvedSize"
              :border-radius="resolvedBorderRadius"
            />
          </div>
          <a
            v-if="!disabled"
            href="#"
            class="text-neutral-500 hover:text-red-500 flex items-center shrink-0"
            @click.stop.prevent="clearUrl"
          >
            <Icon
              name="heroicons:trash"
              class="h-5 w-5"
            />
          </a>
        </div>
      </button>
    </span>

    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>

    <UModal
      v-model:open="showUploadModal"
      title="Upload an audio file"
      :ui="{ content: 'sm:max-w-xl', body: 'pt-2!' }"
    >
      <template #body>
        <div class="max-w-3xl mx-auto lg:max-w-none">
          <UTabs
            v-model="activeTab"
            :items="tabItems"
            :content="false"
            variant="link"
            class="mb-2"
          />

          <div v-if="activeTab === 'upload'" class="sm:grid sm:grid-cols-1 sm:gap-4 sm:items-start">
            <div class="sm:col-span-2 mb-5 mt-2">
              <div
                v-cloak
                class="w-full flex justify-center items-center px-6 pt-5 pb-6 border-2 border-neutral-300 border-dashed rounded-md h-54"
                @dragover.prevent="onUploadDragoverEvent($event)"
                @drop.prevent="onUploadDropEvent($event)"
              >
                <div
                  v-if="loading"
                  class="text-neutral-600 dark:text-neutral-400"
                >
                  <loader class="h-5 w-5 mx-auto m-10" />
                  <p class="text-center mt-6">
                    Uploading your file...
                  </p>
                </div>
                <template v-else>
                  <div
                    class="absolute rounded-full bg-neutral-100 h-20 w-20 z-10 transition-opacity duration-500 ease-in-out"
                    :class="{
                      'opacity-100': uploadDragoverTracking,
                      'opacity-0': !uploadDragoverTracking,
                    }"
                  />
                  <div class="relative z-20 text-center">
                    <input
                      ref="actual-input"
                      class="hidden"
                      type="file"
                      :name="name"
                      accept="audio/mpeg, audio/wav, audio/ogg, audio/mp4, audio/aac, audio/webm, .mp3, .wav, .ogg, .m4a, .aac, .webm"
                      @change="manualFileUpload"
                    >
                    <Icon
                      name="heroicons:cloud-arrow-up"
                      class="x-auto h-24 w-24 text-neutral-200"
                    />
                    <p class="mt-5 text-sm text-neutral-600">
                      <button
                        type="button"
                        class="font-semibold text-blue-500 hover:text-blue-800 focus:outline-hidden focus:underline transition duration-150 ease-in-out"
                        @click="openFileUpload"
                      >
                        Upload your audio,
                      </button>
                      use drag and drop or paste it
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">
                      .mp3, .wav, .ogg, .m4a, .aac, .webm up to 5mb
                    </p>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'url'" class="p-4">
            <TextInput
              v-model="urlInput"
              name="audio_url"
              label="Enter the URL of the audio you want to use"
              placeholder="https://example.com/audio.mp3"
            />
            <div class="mt-4 flex justify-end gap-2">
              <UButton color="primary" :disabled="!urlInput" @click="insertUrl">
                Insert
              </UButton>
            </div>
          </div>
        </div>
      </template>
    </UModal>
  </InputWrapper>
</template>

<script>
import { inputProps, useFormInput } from "../useFormInput.js"
import { storeFile } from "~/lib/file-uploads.js"
import { formsApi } from '~/api'
import { imageInputTheme } from '~/lib/forms/themes/image-input.theme.js'

export default {
  props: {
    ...inputProps,
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: imageInputTheme
    })
    return {
      ...formInput,
      props
    }
  },

  data: () => ({
    showUploadModal: false,
    activeTab: 'upload',
    urlInput: '',

    file: [],
    uploadDragoverTracking: false,
    uploadDragoverEvent: false,
    loading: false,
  }),

  computed: {
    currentUrl() {
      return this.compVal
    },
    tabItems() {
      return [
        { label: 'Upload', value: 'upload' },
        { label: 'URL', value: 'url' },
      ]
    },
  },

  watch: {
    showUploadModal: {
      handler() {
        if (import.meta.server) return
        document.removeEventListener("paste", this.onUploadPasteEvent)
        if (this.showUploadModal) {
          document.addEventListener("paste", this.onUploadPasteEvent)
        }
      },
    },
  },

  methods: {
    clearUrl() {
      if (this.disabled) return
      this.compVal = null
    },
    onUploadDragoverEvent() {
      this.uploadDragoverEvent = true
      this.uploadDragoverTracking = true
    },
    onUploadDropEvent(e) {
      this.uploadDragoverEvent = false
      this.uploadDragoverTracking = false
      this.droppedFiles(e.dataTransfer.files)
    },
    onUploadPasteEvent(e) {
      if (!this.showUploadModal || this.activeTab !== 'upload') return
      this.uploadDragoverEvent = false
      this.uploadDragoverTracking = false
      this.droppedFiles(e.clipboardData.files)
    },
    insertUrl() {
      if (this.disabled) return
      if (!this.urlInput || !/^https?:\/\/[^\s$.?#].[^\s]*$/i.test(this.urlInput)) return
      this.compVal = this.urlInput
      this.urlInput = ''
      this.showUploadModal = false
    },
    droppedFiles(droppedFiles) {
      if (this.disabled) return
      if (!droppedFiles) return

      this.file = droppedFiles[0]
      this.uploadFileToServer()
    },
    openFileUpload() {
      if (this.disabled) return
      this.$refs["actual-input"].click()
    },
    manualFileUpload(e) {
      if (this.disabled) return
      this.file = e.target.files[0]
      this.uploadFileToServer()
    },
    uploadFileToServer() {
      this.loading = true
      storeFile(this.file)
        .then((response) => {
          formsApi.assets.upload({
            url:
              this.file.name.split(".").slice(0, -1).join(".") +
              "_" +
              response.uuid +
              "." +
              response.extension,
          })
            .then((moveFileResponseData) => {
              this.compVal = moveFileResponseData.url
            })
            .catch((error) => {
              this.compVal = null
              this.showUploadError(error)
            })
        })
        .catch((error) => {
          this.compVal = null
          this.showUploadError(error)
        })
        .finally(() => {
          this.showUploadModal = false
          this.loading = false
        })
    },
    showUploadError(error) {
      const data = error?.data || error?.response?._data
      useAlert().error(data?.message || error?.message || 'Failed to upload audio file. Please try again.')
    },
  },
}
</script>
