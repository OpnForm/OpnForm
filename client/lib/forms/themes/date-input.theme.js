/**
 * DateInput tailwind-variants configuration
 */
export const dateInputTheme = {
  slots: {
    input: [
      'w-full border bg-white dark:bg-notion-dark-light',
      'text-neutral-700 dark:text-neutral-300',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:border-transparent'
    ],
    inner: ''
  },
  variants: {
    theme: {
      default: { 
        input: [
          'border-neutral-300 dark:border-neutral-600',
          'focus-visible:ring-form/100'
        ]
      },
      simple: { 
        input: [
          'border-neutral-300 dark:border-neutral-600',
          'focus-visible:ring-form/100'
        ]
      },
      notion: {
        input: [
          'border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100',
          'focus-visible:ring-form/40'
        ]
      },
      minimal: {
        input: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300',
          'focus-visible:ring-0 focus-visible:border-form'
        ]
      }
    },
    size: {
      xs: { input: 'text-xs', inner: 'px-2.5 py-1.5 text-xs' },
      sm: { input: 'text-sm', inner: 'px-2 py-1.5 text-sm' },
      md: { input: 'text-base', inner: 'px-4 py-2 text-base' },
      lg: { input: 'text-lg', inner: 'px-5 py-3 text-lg' }
    },
    borderRadius: {
      none: { input: 'rounded-none' },
      small: { input: 'rounded-lg' },
      full: { input: 'rounded-[20px]' }
    },
    hasError: {
      true: { input: '!ring-red-500 !ring-2 !border-transparent' }
    },
    disabled: {
      true: { 
        input: [
          '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800',
          '!focus-visible:ring-0'
        ]
      }
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
