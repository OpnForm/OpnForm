/**
 * VSelect tailwind-variants configuration
 * Used for select dropdown components
 */
export const vSelectTheme = {
  slots: {
    container: 'v-select relative',
    anchor: 'w-full flex overflow-hidden',
    button: [
      'cursor-pointer w-full grow relative focus:outline-hidden min-w-0 truncate'
    ],
    buttonInner: [
      'flex items-center',
      'ltr-only:pr-6 rtl-only:pl-6'
    ],
    placeholder: [
      'text-neutral-400 dark:text-neutral-500 w-full ltr:text-left rtl:text-right! truncate ltr:pr-3 rtl:pl-3 rtl:pr-0!'
    ],
    chevronGradient: 'absolute inset-y-0 ltr-only:right-6 rtl-only:left-6 w-10 pointer-events-none',
    chevronContainer: [
      'absolute inset-y-0 ltr-only:right-0 rtl-only:left-0 rtl-only:right-auto!',
      'flex items-center ltr-only:pr-2 rtl-only:pl-2 rtl-only:pr-0! pointer-events-none'
    ],
    chevronIcon: 'h-5 w-5 text-neutral-500',
    clearButton: [
      'hover:bg-neutral-50 dark:hover:bg-neutral-900',
      'ltr:border-l rtl:border-l-0! rtl:border-r px-2 flex items-center shrink-0'
    ],
    clearIcon: 'w-5 h-5 text-neutral-500',
    dropdown: [
      'leading-6 shadow-xs overflow-auto focus:outline-hidden sm:text-sm sm:leading-5 relative'
    ],
    searchContainer: [
      'sticky top-0 z-10 flex border-b bg-white dark:!bg-notion-dark-light'
    ],
    searchInput: [
      'grow ltr:pl-3 ltr:pr-7 rtl:pr-3! rtl:pl-7 py-2 w-full focus:outline-hidden dark:text-white'
    ],
    searchIconContainer: [
      'flex absolute ltr-only:right-0 rtl-only:left-0 rtl-only:right-auto! inset-y-0 items-center px-2 justify-center pointer-events-none'
    ],
    searchClearContainer: [
      'flex absolute ltr-only:right-0 rtl-only:right-auto! rtl-only:left-0 inset-y-0 items-center px-2 justify-center'
    ],
    searchIcon: 'h-5 w-5 text-neutral-500 dark:text-neutral-400',
    searchClearIcon: 'h-5 w-5 rtl:rotate-180 text-neutral-500 dark:text-neutral-400',
    optionsContainer: 'p-1',
    option: [
      'text-neutral-900 dark:text-neutral-50 select-none relative group rounded-sm focus:outline-hidden'
    ],
    createOption: [
      'text-neutral-900 select-none relative py-2 cursor-pointer group hover:bg-neutral-100 dark:hover:bg-neutral-900 rounded-sm focus:outline-hidden'
    ],
    emptyMessage: 'w-full text-neutral-500 text-center py-2',
    createLabel: 'px-2 bg-neutral-100 border border-neutral-300 rounded-sm group-hover:text-black'
  },
  variants: {
    theme: {
      default: {
        anchor: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white dark:bg-notion-dark-light',
          'shadow-xs'
        ],
        chevronGradient: 'bg-gradient-to-r from-transparent to-white dark:to-notion-dark-light',
        chevronContainer: 'bg-white dark:bg-notion-dark-light'
      },
      simple: {
        anchor: [
          'border border-neutral-300 dark:border-neutral-600',
          'bg-white dark:bg-notion-dark-light'
        ],
        chevronGradient: 'bg-gradient-to-r from-transparent to-white dark:to-notion-dark-light',
        chevronContainer: 'bg-white dark:bg-notion-dark-light'
      },
      notion: {
        anchor: [
          'border border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light'
        ],
        chevronGradient: 'bg-gradient-to-r from-transparent to-notion-input-background dark:to-notion-dark-light',
        chevronContainer: 'bg-notion-input-background dark:bg-notion-dark-light'
      },
      minimal: {
        anchor: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light'
        ],
        chevronGradient: 'bg-gradient-to-r from-transparent to-neutral-100 dark:to-notion-dark-light',
        chevronContainer: 'bg-neutral-100 dark:bg-notion-dark-light'
      },
      transparent: {
        anchor: [
          'border-0',
          'bg-transparent dark:bg-transparent',
          'text-neutral-700 dark:text-neutral-300',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]',
          '!rounded-none',
          'transition-shadow duration-200',
          'focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)]'
        ],
        chevronGradient: 'bg-gradient-to-r from-transparent to-transparent',
        chevronContainer: 'bg-transparent',
        button: '!pl-0'
      }
    },
    // Open state styling for when popover is open (even if not focused)
    isOpen: {
      true: {
        anchor: 'ring-2 ring-form/100 outline-none'
      }
    },
    size: {
      xs: {
        button: 'px-2.5 py-1.5',
        buttonInner: 'min-h-[16px]',
        placeholder: 'text-xs',
        clearButton: 'py-1.5',
        dropdown: 'text-xs',
        option: 'px-2.5'
      },
      sm: {
        button: 'px-2 py-1.5',
        buttonInner: 'min-h-[20px]',
        placeholder: 'text-sm',
        clearButton: 'py-1.5',
        dropdown: 'text-sm',
        option: 'px-2 py-0.5'
      },
      md: {
        button: 'px-4 py-2',
        buttonInner: 'min-h-[24px]',
        placeholder: 'text-base',
        clearButton: 'py-2',
        dropdown: 'text-base',
        option: 'px-4 py-1.5'
      },
      lg: {
        button: 'px-5 py-3',
        buttonInner: 'min-h-[28px]',
        placeholder: 'text-lg',
        clearButton: 'py-3',
        dropdown: 'text-lg',
        option: 'px-5 py-2'
      }
    },
    borderRadius: {
      none: {
        anchor: 'rounded-none'
      },
      small: {
        anchor: 'rounded-lg'
      },
      full: {
        anchor: 'rounded-[20px]'
      }
    },
    hasError: {
      true: {
        anchor: '!ring-red-500 !ring-2 !border-transparent'
      }
    },
    disabled: {
      true: {
        anchor: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800',
        chevronGradient: '!bg-gradient-to-r !from-transparent !to-neutral-200 dark:!to-neutral-800',
        chevronContainer: '!bg-neutral-200 dark:!bg-neutral-800'
      }
    },
    focused: {
      true: {
        anchor: 'focus-within:ring-2 focus-within:ring-form/100 focus-within:border-transparent'
      }
    },
    multiple: {
      true: {
        container: 'w-0 min-w-full'
      }
    },
    searchable: {
      true: {
        dropdown: 'max-h-48'
      },
      false: {
        dropdown: 'max-h-42'
      }
    }
  },
  // Ensure transparent theme uses bottom-only highlight when open
  compoundVariants: [
    {
      theme: 'transparent',
      isOpen: true,
      class: { anchor: 'ring-0 shadow-[inset_0_-2px_0_0_var(--color-form)] outline-none' }
    },
    {
      theme: 'transparent',
      focused: true,
      class: { anchor: 'focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)] outline-none' }
    }
  ],
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small',
    hasError: false,
    disabled: false,
    focused: false,
    multiple: false,
    searchable: false
  }
}