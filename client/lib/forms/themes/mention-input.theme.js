/**
 * MentionInput tailwind-variants configuration
 */
export const mentionInputTheme = {
  slots: {
    input: [
      'flex-1 appearance-none w-full',
      'border',
      'focus:outline-hidden',
      'pr-12'
    ]
  },
  variants: {
    theme: {
      default: {
        input: 'border-neutral-300 dark:border-neutral-600 bg-white text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300'
      },
      minimal: {
        input: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300'
        ]
      },
      notion: {
        input: 'border-notion-input-border dark:border-notion-input-borderDark bg-notion-input-background dark:bg-notion-dark-light text-neutral-900 dark:text-neutral-100'
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

