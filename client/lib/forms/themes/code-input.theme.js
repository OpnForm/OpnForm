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
      notion: { container: 'border border-notion-input-border dark:border-notion-input-borderDark' }
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

