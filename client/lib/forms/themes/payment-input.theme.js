/**
 * PaymentInput tailwind-variants configuration
 */
export const paymentInputTheme = {
  slots: {
    container: [],
    card: [],
    amountBar: ['flex items-center justify-between'],
    section: [],
    stack: ['flex flex-col']
  },
  variants: {
    theme: {
      default: {
        container: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white dark:bg-neutral-900',
          'text-neutral-700 dark:text-neutral-200'
        ],
        card: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white dark:bg-neutral-900',
          'text-neutral-700 dark:text-neutral-200'
        ],
        amountBar: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-neutral-50 dark:bg-neutral-800'
        ],
      },
      simple: {
        container: [
          'bg-neutral-50 dark:bg-neutral-800',
        ],
        card: [
          'bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-600',
        ],
        amountBar: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-neutral-100/50 dark:bg-neutral-700'
        ]
      },
      minimal: {
        container: [
          'border-2 border-transparent',
          'bg-neutral-50 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300'
        ],
        card: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300'
        ],
        amountBar: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-neutral-900'
        ]
      },
      notion: {
        container: [
          'border border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100'
        ],
        card: [
          'border border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100'
        ],
        amountBar: [
          'border border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light'
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
        ],
        amountBar: [
          'border-0',
          'bg-transparent',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]'
        ]
      }
    },
    size: {
      xs: { container: 'px-2.5 py-1.5 text-xs', card: 'px-2.5 py-1.5 text-xs', amountBar: 'px-2.5 py-1.5 text-xs mb-2', section: 'my-2', stack: 'gap-2' },
      sm: { container: 'px-2 py-1.5 text-sm', card: 'px-2 py-1.5 text-sm', amountBar: 'px-2 py-1.5 text-sm mb-2', section: 'my-2', stack: 'gap-2' },
      md: { container: 'px-4 py-2 text-base', card: 'px-4 py-2 text-base', amountBar: 'px-4 py-2 text-base mb-3', section: 'my-3', stack: 'gap-3' },
      lg: { container: 'px-5 py-3 text-lg', card: 'px-5 py-3 text-lg', amountBar: 'px-5 py-3 text-lg mb-4', section: 'my-4', stack: 'gap-4' }
    },
    borderRadius: {
      none: { container: 'rounded-none', card: 'rounded-none', amountBar: 'rounded-none' },
      small: { container: 'rounded-lg', card: 'rounded-lg', amountBar: 'rounded-lg' },
      full: { container: 'rounded-[20px]', card: 'rounded-[20px]', amountBar: 'rounded-[20px]' }
    },
    hasError: {
      true: { container: '!ring-red-500 !ring-2 !border-transparent', card: '!ring-red-500 !ring-2 !border-transparent' }
    },
    disabled: {
      true: { container: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800', card: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800' }
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

