/**
 * RichTextAreaInput tailwind-variants configuration
 */
export const richTextAreaInputTheme = {
  slots: {
    container: [
      'rich-editor resize-y notranslate relative'
    ],
    help: 'text-neutral-500'
  },
  variants: {
    theme: {
      default: {
        container: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300'
        ]
      },
      simple: {
        container: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300'
        ]
      },
      notion: {
        container: [
          'border border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100'
        ]
      },
      minimal: {
        container: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300',
        ]
      },
      transparent: {
        container: [
          'border-0',
          'bg-transparent dark:bg-transparent',
          'text-neutral-700 dark:text-neutral-300',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]',
          '!rounded-none',
          'transition-shadow duration-200',
          'focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)]'
        ]
      }
    },
    size: {
      xs: { container: 'text-xs' },
      sm: { container: 'text-sm' },
      md: { container: 'text-base' },
      lg: { container: 'text-lg' }
    },
    borderRadius: {
      none: { container: 'rounded-none' },
      small: { container: 'rounded-lg' },
      full: { container: 'rounded-[20px]' }
    },
    hasError: {
      true: { container: '!ring-red-500 !ring-2 !border-transparent' }
    },
    disabled: {
      true: { container: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800' }
    },
    focused: {
      true: { container: 'focus-within:ring-2 focus-within:ring-form/100 focus-within:border-transparent' }
    }
  },
  compoundVariants: [
    {
      theme: 'transparent',
      focused: true,
      class: { container: 'focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)] outline-none' }
    }
  ],
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small',
    hasError: false,
    disabled: false,
    focused: false
  }
}

