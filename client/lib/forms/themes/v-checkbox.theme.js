/**
 * VCheckbox tailwind-variants configuration
 * Used for checkbox input components
 */
export const vCheckboxTheme = {
  slots: {
    container: 'flex items-center',
    input: [
      'rounded border-neutral-500 checkbox',
      'size-5', // Default size, overridden by variants
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:border-transparent'
    ],
    label: [
      'text-neutral-700 dark:text-neutral-300 ml-2'
    ]
  },
  variants: {
    size: {
      xs: {
        input: 'size-3'
      },
      sm: {
        input: 'size-4'
      },
      md: {
        input: 'size-5'
      },
      lg: {
        input: 'size-6'
      }
    },
    disabled: {
      true: {
        input: '!cursor-not-allowed',
        label: '!cursor-not-allowed'
      },
      false: {
        input: 'cursor-pointer'
      }
    }
  },
  defaultVariants: {
    size: 'md',
    disabled: false
  }
}