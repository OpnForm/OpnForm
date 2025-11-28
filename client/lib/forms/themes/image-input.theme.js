/**
 * ImageInput tailwind-variants configuration
 */
export const imageInputTheme = {
  slots: {
    button: [
      'cursor-pointer relative w-full',
      'flex-1 appearance-none w-full',
      'focus:outline-hidden'
    ],
    help: 'text-neutral-500'
  },
  variants: {
    theme: {
      default: {
        button: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300',
          'shadow-xs'
        ]
      },
      minimal: {
        button: [
          'border-2 border-transparent',
          'bg-neutral-100 text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300'
        ]
      },
      notion: {
        button: [
          'border border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100'
        ]
      },
      transparent: {
        button: [
          'border-0',
          'bg-transparent dark:bg-transparent',
          'text-neutral-700 dark:text-neutral-300',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]',
          '!rounded-none',
          'transition-shadow duration-200',
          'focus:ring-0 focus:shadow-[inset_0_-2px_0_0_var(--color-form)]'
        ]
      }
    },
    size: {
      xs: { button: 'px-2.5 py-1.5 text-xs' },
      sm: { button: 'px-2 py-1.5 text-sm' },
      md: { button: 'px-4 py-2 text-base' },
      lg: { button: 'px-5 py-3 text-lg' }
    },
    borderRadius: {
      none: { button: 'rounded-none' },
      small: { button: 'rounded-lg' },
      full: { button: 'rounded-[20px]' }
    },
    hasError: {
      true: { button: 'ring-red-500 ring-2 border-transparent' }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small',
    hasError: false
  }
}

