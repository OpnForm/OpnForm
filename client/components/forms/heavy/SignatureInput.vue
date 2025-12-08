<template>
  <InputWrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div
      v-if="loading || file"
      :class="ui.container({ class: props.ui?.slots?.container })"
    >
      <div
        v-if="loading"
        class="text-neutral-600 dark:text-neutral-400"
      >
        <Loader class="mx-auto h-6 w-6" />
        <p class="mt-2 text-center text-sm text-neutral-500">
          {{ $t('forms.fileInput.uploadingFile') }}
        </p>
      </div>
    
      <uploaded-file
        v-else
        :key="file.url"
        :file="file"
        :show-remove="false"
        :disabled="disabled"
      />
    </div>

    <VueSignaturePad
      v-else
      ref="signaturePad"
      class="not-draggable"
      :class="ui.container({ class: props.ui?.slots?.container })"
      height="150px"
      :name="name"
      :options="{ onEnd, penColor }"
    />

    <template #bottom_after_help>
      <small
        v-if="!file"
        :class="ui.help({ class: props.ui?.slots?.help })"
        class="flex-auto"
      >
        <input
          ref="actual-input"
          class="hidden"
          :multiple="false"
          type="file"
          accept=".pdf,.png,.jpg,.jpeg"
          @change="manualFileUpload"
        >
        <a
          :class="ui.help({ class: props.ui?.slots?.help })"
          href="#"
          @click.prevent="openFileUpload"
        >{{ $t('forms.signatureInput.uploadFileInstead') }}</a>
      </small>

      <small :class="ui.help({ class: props.ui?.slots?.help })">
        <a
          :class="ui.help({ class: props.ui?.slots?.help })"
          href="#"
          @click.prevent="clear"
        >{{ $t('forms.signatureInput.clear') }}</a>
      </small>
    </template>

    <template #error>
      <slot name="error" />
    </template>
  </InputWrapper>
</template>

<script>
import { VueSignaturePad } from 'vue-signature-pad'
import { inputProps, useFormInput } from '../useFormInput.js'
import { storeFile } from '~/lib/file-uploads.js'
import { signatureInputTheme } from '~/lib/forms/themes/signature-input.theme.js'

export default {
  name: 'SignatureInput',
  components: { VueSignaturePad },

  props: {
    ...inputProps,
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: signatureInputTheme
    })
    return {
      ...formInput,
      props
    }
  },

  data: () => ({
    file: null,
    loading: false
  }),

  watch: {
    file: {
      handler(file) {
        this.compVal = file?.url || null
      }
    }
  },

  computed: {
    penColor() {
      return this.isDark ? '#fff' : '#000'
    }
  },
  
  mounted() {
    this.$nextTick(() => {
      if (this.$refs.signaturePad) {
        this.$refs.signaturePad.resizeCanvas()
      }
    })
  },

  methods: {
    clear() {
      this.file = null
      this.$refs.signaturePad?.clearSignature()
      this.onEnd()
    },
    onEnd() {
      if (!this.$refs.signaturePad) {
        this.form[this.name] = null
        return
      }

      if (this.disabled) {
        this.$refs.signaturePad.clearSignature()
      } else {
        /* eslint-disable-next-line */
        const { isEmpty, data } = this.$refs.signaturePad?.saveSignature()
        this.form[this.name] = !isEmpty && data ? data : null
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
    uploadFileToServer(file) {
      if (this.disabled) return
      this.loading = true
      storeFile(file)
        .then((response) => {
          this.file = {
            file: file,
            url: file.name.split('.').slice(0, -1).join('.') + '_' + response.uuid + '.' + response.extension,
            src: this.getFileSrc(file)
          }
          this.loading = false
        })
        .catch(() => {
          this.loading = false
          this.file = null
        })
    },
    getFileSrc(file) {
      if (file.type && file.type.split('/')[0] === 'image') {
        return URL.createObjectURL(file)
      }
      return null
    }
  },
}
</script>
