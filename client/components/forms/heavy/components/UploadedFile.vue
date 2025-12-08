<template>
  <div
    class="overflow-hidden"
    :class="[variantSlots.root({ class: props.ui?.slots?.root }), { 'bg-white': !disabled, 'bg-gray-300': disabled }]"
    :title="file.file.name"
  >
    <div
      v-if="file.src && !isImageHide"
      class="h-20 overflow-hidden flex"
    >
      <img
        class="block object-cover object-center w-full"
        :src="file.src"
        @error="isImageHide = true"
      >
    </div>
    <div
      v-else
      class="h-20 flex items-center justify-center"
    >
      <svg
        class="w-10 h-10 text-neutral-500"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="0.8"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
        />
      </svg>
    </div>
    <div class="flex gap-2 items-center border-t py-1 px-2">
      <p class="flex-grow text-left truncate text-neutral-500 text-xs">
        {{ file.file.name }}
      </p>
      <a
        v-if="showRemove && !disabled"
        href="javascript:void(0);"
                  class="flex text-neutral-400 rounded-sm hover:bg-neutral-50 hover:text-red-500 dark:text-neutral-600 p-1"
        role="button"
        title="Remove"
        @click.stop="$emit('remove')"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
          />
        </svg>
      </a>
    </div>
  </div>
</template>

<script>
import { tv } from 'tailwind-variants'
import { uploadedFileTheme } from '~/lib/forms/themes/uploaded-file.theme.js'

export default {
  name: "UploadedFile",

  props: {
    file: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
    showRemove: { type: Boolean, default: true },
    ui: { type: Object, default: () => ({}) }
  },
  emits: ['remove'],
  data: () => ({
    isImageHide: false,
  }),
  setup(props) {
    const injectedTheme = inject('formTheme', null)
    const resolvedTheme = computed(() => injectedTheme?.value || 'default')
    const uploadedVariants = computed(() => tv(uploadedFileTheme, props.ui || {}))
    const variantSlots = computed(() => uploadedVariants.value({ theme: resolvedTheme.value }))
    return { variantSlots, props }
  },
}
</script>
