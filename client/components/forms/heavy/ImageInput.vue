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
          class=" text-neutral-600 dark:text-neutral-400 flex"
        >
          <div class="flex-grow">
            <img
              :src="currentUrl"
              class="h-5 rounded-sm shadow-sm border"
            >
          </div>
          <slot name="left-action" />
          <a
            href="#"
            class="text-neutral-500 hover:text-red-500 flex items-center"
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

    <!--  Modal  -->
    <UModal
      v-model:open="showUploadModal"
      title="Upload an image"
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
                      accept="image/png, image/gif, image/jpeg, image/bmp, image/svg+xml"
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
                        Upload your image,
                      </button>
                      use drag and drop or paste it
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">
                      .jpg, .jpeg, .png, .bmp, .gif, .svg up to 5mb
                    </p>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'unsplash'">
            <UnsplashImages @selectImage="selectUnsplashImage" />
          </div>

          <div v-else-if="activeTab === 'url'" class="p-4">
            <TextInput
              v-model="urlInput"
              name="image_url"
              label="Enter the URL of the image you want to use"
              placeholder="https://example.com/image.jpg"
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
import { useFeatureFlag } from '~/composables/useFeatureFlag.js'

export default {
  components: {  },
  mixins: [],
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
      const baseTabs = [
        { label: 'Upload', value: 'upload' },
        { label: 'URL', value: 'url' },
      ]
      if (useFeatureFlag('services.unsplash')) {
        baseTabs.splice(1, 0, { label: 'Unsplash', value: 'unsplash' })
      }
      return baseTabs
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
    selectUnsplashImage(imageUrl) {
      this.compVal = imageUrl
      this.showUploadModal = false
    },
    insertUrl() {
      if (!this.urlInput || !/^https?:\/\/[^\s$.?#].[^\s]*$/i.test(this.urlInput)) return
      this.compVal = this.urlInput
      this.urlInput = ''
      this.showUploadModal = false
    },
    droppedFiles(droppedFiles) {
      if (!droppedFiles) return

      this.file = droppedFiles[0]
      this.uploadFileToServer()
    },
    openFileUpload() {
      this.$refs["actual-input"].click()
    },
    manualFileUpload(e) {
      this.file = e.target.files[0]
      this.uploadFileToServer()
    },
    uploadFileToServer() {
      this.loading = true
      // Store file in s3
      storeFile(this.file)
        .then((response) => {
          // Move file to permanent storage for form assets
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
            .catch(() => {
              this.compVal = null
            })
        })
        .catch(() => {
          this.compVal = null
        })
        .finally(() => {
          this.showUploadModal = false
          this.loading = false
        })
    },
  },
}
</script>
