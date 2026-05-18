/**
 * FocusedSelectorInput tailwind-variants configuration
 * Used for focused mode one-per-line option selection
 */
export const focusedSelectorInputTheme = {
  slots: {
    container: 'space-y-2 focus:outline-hidden',
    option: [
      'w-full border border-transparent transition-all duration-200',
      'overflow-hidden',
      'group'
    ],
    optionButton: [
      'w-full flex items-center gap-3 transition-all duration-200',
      'focus:outline-hidden'
    ],
    label: [
      'shrink-0 flex items-center justify-center',
      'font-medium transition-all duration-200'
    ],
    optionText: [
      'flex-1 text-left transition-colors duration-200'
    ],
    checkmark: [
      'shrink-0 opacity-0 transition-all duration-200'
    ]
  },
  variants: {
    theme: {
      default: {
        option: [
          'bg-white dark:bg-notion-dark-light'
        ],
        optionButton: [
          'text-neutral-700 dark:text-neutral-300'
        ],
        label: [
          'text-neutral-500 dark:text-neutral-400'
        ]
      },
      notion: {
        option: [
          'bg-notion-input-background dark:bg-notion-dark-light'
        ],
        optionButton: [
          'text-notion-text dark:text-notion-dark-text'
        ],
        label: [
          'text-neutral-500 dark:text-neutral-400'
        ]
      },
      minimal: {
        option: [
          'bg-neutral-100 dark:bg-notion-dark-light'
        ],
        optionButton: [
          'text-neutral-700 dark:text-neutral-300'
        ],
        label: [
          'text-neutral-500 dark:text-neutral-400'
        ]
      }
    },
    size: {
      xs: {
        optionButton: 'px-3 py-2 text-xs',
        label: 'w-6 h-6 text-xs',
        optionText: 'text-xs',
        checkmark: 'w-4 h-4'
      },
      sm: {
        optionButton: 'px-4 py-2.5 text-sm',
        label: 'w-7 h-7 text-sm',
        optionText: 'text-sm',
        checkmark: 'w-5 h-5'
      },
      md: {
        optionButton: 'px-5 py-3 text-base',
        label: 'w-8 h-8 text-base',
        optionText: 'text-base',
        checkmark: 'w-5 h-5'
      },
      lg: {
        optionButton: 'px-6 py-4 text-lg',
        label: 'w-10 h-10 text-lg',
        optionText: 'text-lg',
        checkmark: 'w-6 h-6'
      }
    },
    borderRadius: {
      none: {
        option: 'rounded-none',
        label: 'rounded-none'
      },
      small: {
        option: 'rounded-lg',
        label: 'rounded-md'
      },
      full: {
        option: 'rounded-[20px]',
        label: 'rounded-xl'
      }
    },
    selected: {
      true: {
        option: [
          'bg-[color-mix(in_srgb,var(--bg-form-color)_20%,transparent)]',
          'dark:bg-[color-mix(in_srgb,var(--bg-form-color)_25%,transparent)]',
          'shadow-[0_0_0_1px_color-mix(in_srgb,var(--bg-form-color)_60%,transparent)]',
          'dark:shadow-[0_0_0_1px_color-mix(in_srgb,var(--bg-form-color)_70%,transparent)]'
        ],
        optionButton: [
          'text-form-color',
          'hover:bg-[color-mix(in_srgb,var(--bg-form-color)_30%,transparent)]',
          'dark:hover:bg-[color-mix(in_srgb,var(--bg-form-color)_35%,transparent)]'
        ],
        optionText: 'text-neutral-900 dark:text-white',
        label: [
          'bg-form text-white dark:text-white'
        ],
        checkmark: 'opacity-100 text-form dark:text-form'
      },
      false: {
        option: [
          'bg-[color-mix(in_srgb,var(--bg-form-color)_8%,transparent)]',
          'dark:bg-[color-mix(in_srgb,var(--bg-form-color)_12%,transparent)]',
          'hover:bg-[color-mix(in_srgb,var(--bg-form-color)_15%,transparent)]',
          'dark:hover:bg-[color-mix(in_srgb,var(--bg-form-color)_20%,transparent)]'
        ],
        optionText: 'text-neutral-700 dark:text-neutral-200',
        label: [
          'border border-current'
        ]
      }
    },
    animating: {
      true: {
        option: 'flash-animation',
        optionText: 'text-neutral-900 dark:text-white',
        checkmark: 'opacity-100 text-form dark:text-form'
      }
    },
    disabled: {
      true: {
        option: 'opacity-50 cursor-not-allowed',
        optionButton: 'pointer-events-none',
        optionText: 'text-neutral-400 dark:text-neutral-500'
      }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small',
    width: 'full',
    selected: false,
    animating: false,
    disabled: false
  }
}

