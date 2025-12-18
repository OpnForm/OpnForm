/**
 * UploadedFile tailwind-variants configuration
 */
export const uploadedFileTheme = {
  slots: {
    root: 'border rounded-lg shadow-xs max-w-10'
  },
  variants: {
    theme: {
      default: { root: 'border-neutral-300 dark:border-neutral-600 dark:bg-notion-dark-light' },
      minimal: { root: 'border-2 border-transparent bg-neutral-100 dark:bg-notion-dark-light' },
      notion: { root: 'border-notion-input-border dark:border-notion-input-borderDark dark:bg-notion-dark-light rounded-xs' },
      transparent: { root: 'border-0 bg-transparent dark:bg-transparent shadow-none !rounded-none' }
    }
  },
  defaultVariants: {
    theme: 'default'
  }
}

