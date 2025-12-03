/**
 * MatrixInput tailwind-variants configuration
 */
export const matrixInputTheme = {
  slots: {
    container: 'border overflow-x-auto',
    cell: '',
    cellHover: '',
    option: 'relative cursor-pointer',
    iconWrapper: 'flex items-center justify-center h-full w-full',
    headerCell: 'w-full flex items-center justify-center text-neutral-900 dark:text-neutral-100',
    rowCell: 'w-full text-neutral-900 dark:text-neutral-100'
  },
  variants: {
    theme: {
      default: {
        container: [
          'border-neutral-300 dark:border-neutral-600',
          'bg-white dark:bg-notion-dark-light',
          'shadow-xs'
        ],
        cell: 'border-neutral-300 dark:border-neutral-600',
        cellHover: 'hover:bg-neutral-50 dark:hover:bg-neutral-900',
        option: 'hover:bg-neutral-50 dark:hover:bg-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:ring-inset'
      },
      simple: {
        container: [
          'border-neutral-300 dark:border-neutral-600',
          'bg-white dark:bg-notion-dark-light'
        ],
        cell: 'border-neutral-300 dark:border-neutral-600',
        cellHover: 'hover:bg-neutral-50 dark:hover:bg-neutral-900',
        option: 'hover:bg-neutral-50 dark:hover:bg-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:ring-inset'
      },
      notion: {
        container: [
          'border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light'
        ],
        cell: 'border-notion-input-border dark:border-notion-input-borderDark',
        cellHover: 'hover:backdrop-brightness-95',
        option: 'hover:backdrop-brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-form/40 focus-visible:ring-inset'
      },
      minimal: {
        container: [
          'border-2 border-transparent',
          'bg-neutral-100 dark:bg-notion-dark-light'
        ],
        cell: 'border-transparent',
        cellHover: 'hover:bg-neutral-200/50 dark:hover:bg-neutral-900',
        option: 'border-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-form focus-visible:outline-offset-[-2px] focus-visible:ring-0'
      },
      transparent: {
        container: [
          'border-0',
          'bg-transparent',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]',
          '!rounded-none',
          'transition-shadow duration-200',
          'focus-within:ring-0 focus-within:shadow-[inset_0_-2px_0_0_var(--color-form)]'
        ],
        cell: 'border-transparent',
        cellHover: 'hover:backdrop-brightness-[0.97] dark:hover:backdrop-brightness-90',
        option: 'focus-visible:outline-none focus-visible:ring-0',
        rowCell: '!px-0'
      }
    },
    size: {
      xs: {
        headerCell: 'p-1 text-xs text-neutral-900 dark:text-neutral-100',
        rowCell: 'p-1 text-xs text-neutral-900 dark:text-neutral-100',
        option: 'p-1'
      },
      sm: {
        headerCell: 'p-1.5 text-sm text-neutral-900 dark:text-neutral-100',
        rowCell: 'p-1.5 text-sm text-neutral-900 dark:text-neutral-100',
        option: 'p-1.5'
      },
      md: {
        headerCell: 'p-2 text-base text-neutral-900 dark:text-neutral-100',
        rowCell: 'p-2 text-base text-neutral-900 dark:text-neutral-100',
        option: 'p-2'
      },
      lg: {
        headerCell: 'p-3 text-lg text-neutral-900 dark:text-neutral-100',
        rowCell: 'p-3 text-lg text-neutral-900 dark:text-neutral-100',
        option: 'p-3'
      }
    },
    borderRadius: {
      none: { container: 'rounded-none' },
      small: { container: 'rounded-lg' },
      full: { container: 'rounded-[20px]' }
    },
    hasError: {
      true: { 
        container: '!ring-red-500 !ring-2 !border-transparent',
        cell: '!border-red-500'
      }
    },
    disabled: {
      true: { 
        container: '!cursor-not-allowed !bg-neutral-200 dark:!bg-neutral-800',
        cellHover: '!hover:bg-neutral-200 dark:!hover:bg-neutral-800',
        option: '!cursor-not-allowed !hover:bg-neutral-200 dark:!hover:bg-neutral-800 !focus-visible:ring-0'
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

