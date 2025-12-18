/**
 * FlatSelectInput tailwind-variants configuration
 */
export const flatSelectInputTheme = {
  slots: {
    container: [
      'relative overflow-hidden',
      'border bg-white dark:bg-notion-dark-light',
      'text-neutral-700 dark:text-neutral-300',
      'focus-within:outline-hidden'
    ],
    // Keep base minimal; theme variants add interaction + spacing
    option: [
      'relative',
      'focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none',
      'flex items-center',
      'border-t first:border-t-0 px-2',
      'transition-colors duration-150'
    ],
    help: 'text-neutral-500'
  },
  variants: {
    theme: {
      default: {
        container: [
          'relative overflow-hidden',
          'border-neutral-300 dark:border-neutral-600',
          'shadow-xs'
        ],
        option: [
          'relative',
          'focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none',
          'border-neutral-300 dark:border-neutral-600',
          'gap-x-2'
        ]
      },
      simple: {
        container: [
          'relative overflow-hidden',
          'border-neutral-300 dark:border-neutral-600'
        ],
        option: [
          'relative',
          'focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none',
          'border-neutral-300 dark:border-neutral-600',
          'gap-x-2'
        ]
      },
      notion: {
        container: [
          'relative overflow-hidden',
          'border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100'
        ],
        option: [
          'relative',
          'focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none',
          'border-notion-input-border dark:border-notion-input-borderDark',
          'space-x-2'
        ]
      },
      minimal: {
        container: [
          'relative overflow-hidden',
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light',
          'text-neutral-700 dark:text-neutral-300'
        ],
        option: [
          'relative',
          'focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none',
          'border-neutral-200 dark:border-neutral-700',
          'gap-x-2'
        ]
      },
      transparent: {
        container: [
          'relative overflow-hidden',
          'border-0',
          'bg-transparent dark:bg-transparent',
          'text-neutral-700 dark:text-neutral-300',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]',
          '!rounded-none',
          'transition-shadow duration-200',
          'focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)]'
        ],
        option: [
          'relative',
          'focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none',
          'border-neutral-200 dark:border-neutral-700',
          'gap-x-2',
          '!px-0'
        ]
      }
    },
    size: {
      xs: { option: 'px-2 py-1 text-xs' },
      sm: { option: 'px-2 py-1.5 text-sm' },
      md: { option: 'px-3 py-2 text-base' },
      lg: { option: 'px-4 py-3 text-lg' }
    },
    borderRadius: {
      none: { container: 'rounded-none' },
      small: { container: 'rounded-lg' },
      full: { container: 'rounded-[20px]' }
    },
    hasError: {
      true: { container: '!ring-red-500 !ring-2 !border-transparent' }
    },
    optionDisabled: {
      true: {
        option: '!cursor-not-allowed opacity-50'
      },
      false: {
        option: 'cursor-pointer'
      }
    }
  },
  compoundVariants: [
    // Default theme - enabled state (hover effects)
    {
      theme: 'default',
      optionDisabled: false,
      class: {
        option: 'hover:bg-neutral-50 dark:hover:bg-neutral-900'
      }
    },
    // Default theme - disabled state (grayout)
    {
      theme: 'default',
      optionDisabled: true,
      class: {
        option: '!bg-neutral-100 dark:!bg-neutral-800 !text-neutral-400 dark:!text-neutral-600'
      }
    },
    // Simple theme - enabled state
    {
      theme: 'simple',
      optionDisabled: false,
      class: {
        option: 'hover:bg-neutral-50 dark:hover:bg-neutral-900'
      }
    },
    // Simple theme - disabled state
    {
      theme: 'simple',
      optionDisabled: true,
      class: {
        option: '!bg-neutral-100 dark:!bg-neutral-800 !text-neutral-400 dark:!text-neutral-600'
      }
    },
    // Notion theme - enabled state
    {
      theme: 'notion',
      optionDisabled: false,
      class: {
        option: 'hover:backdrop-brightness-95'
      }
    },
    // Notion theme - disabled state
    {
      theme: 'notion',
      optionDisabled: true,
      class: {
        option: '!bg-neutral-100 dark:!bg-neutral-800 !text-neutral-400 dark:!text-neutral-600'
      }
    },
    // Minimal theme - enabled state
    {
      theme: 'minimal',
      optionDisabled: false,
      class: {
        option: 'hover:bg-neutral-200/50 dark:hover:bg-neutral-900'
      }
    },
    // Minimal theme - disabled state
    {
      theme: 'minimal',
      optionDisabled: true,
      class: {
        option: '!bg-neutral-200 dark:!bg-neutral-800 !text-neutral-400 dark:!text-neutral-600'
      }
    }
  ],
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small',
    hasError: false,
    optionDisabled: false
  }
}
