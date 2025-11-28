/**
 * TextAreaInput tailwind-variants configuration
 */
export const textAreaInputTheme = {
  slots: {
    input: [
      'flex-1 appearance-none w-full',
      'border',
      'bg-white dark:bg-notion-dark-light',
      'text-neutral-700 dark:text-neutral-300',
      'placeholder-neutral-400 dark:placeholder-neutral-500',
      'focus:outline-hidden',
      'disabled:cursor-not-allowed disabled:opacity-75',
      'min-h-[100px] resize-y block'
    ],
    help: 'text-neutral-500'
  },
  variants: {
    theme: {
      default: {
        input: [
          'border-neutral-300 dark:border-neutral-600',
          'shadow-xs',
          'focus:ring-2 focus:ring-form/100 focus:border-transparent'
        ]
      },
      simple: {
        input: [
          'border-neutral-300 dark:border-neutral-600',
          'focus:ring-2 focus:ring-form/100 focus:border-transparent'
        ]
      },
      notion: {
        input: [
          'border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100',
          'focus:ring-2 focus:ring-form/40 focus:border-transparent'
        ]
      },
      minimal: {
        input: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300',
          'focus:ring-0 focus:border-form'
        ]
      }
    },
    size: {
      xs: { input: 'px-2.5 py-1.5 text-xs' },
      sm: { input: 'px-2 py-1.5 text-sm' },
      md: { input: 'px-4 py-2 text-base' },
      lg: { input: 'px-5 py-3 text-lg' }
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
      true: { input: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800' }
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

