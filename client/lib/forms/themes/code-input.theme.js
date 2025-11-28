/**
 * CodeInput tailwind-variants configuration
 */
export const codeInputTheme = {
  slots: {
    container: [
      'h-40 max-h-96 overflow-y-auto relative'
    ]
  },
  variants: {
    theme: {
      default: { container: 'border border-neutral-300 dark:border-neutral-600' },
      minimal: { container: 'border-2 border-transparent bg-neutral-100 dark:bg-notion-dark-light' },
      notion: { container: 'border border-notion-input-border dark:border-notion-input-borderDark' },
      transparent: { container: 'border-0 bg-transparent dark:bg-transparent !rounded-none shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)] transition-shadow duration-200 focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)]' }
    },
    size: {
      xs: { container: 'rounded-none' },
      sm: { container: '' },
      md: { container: '' },
      lg: { container: '' }
    },
    borderRadius: {
      none: { container: 'rounded-none' },
      small: { container: 'rounded-md' },
      full: { container: 'rounded-[20px]' }
    },
    hasError: {
      true: { container: '!ring-red-500 !ring-2 !border-transparent' }
    },
    disabled: {
      true: { container: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800' }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small',
    hasError: false,
    disabled: false
  }
}

