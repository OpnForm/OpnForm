/**
 * PhoneInput tailwind-variants configuration
 */
export const phoneInputTheme = {
  slots: {
    countrySelectWidth: '',
    selected: '',
    option: '',
    selectedMaxHeight: '',
    flag: '',
    input: [
      'grow',
      'border',
      'bg-white dark:bg-notion-dark-light',
      'text-neutral-700 dark:text-neutral-300',
      'focus:outline-hidden',
      'placeholder-neutral-400 dark:placeholder-neutral-500'
    ]
    ,
    separator: ''
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
        ],
        separator: 'ltr:border-l rtl:border-r border-neutral-200 dark:border-neutral-700'
      },
      transparent: {
        input: [
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
      xs: { 
        input: 'px-2.5 py-1.5 text-xs',
        selected: 'text-xs',
        option: 'text-xs',
        countrySelectWidth: 'w-[80px]',
        selectedMaxHeight: 'max-h-[16px]',
        flag: '-mt-[16px]!'
      },
      sm: { 
        input: 'px-2 py-1.5 text-sm',
        selected: 'text-sm',
        option: 'text-sm',
        countrySelectWidth: 'w-[100px]',
        selectedMaxHeight: 'max-h-[20px]',
        flag: '-mt-[14px]!'
      },
      md: { 
        input: 'px-4 py-2 text-base',
        selected: 'text-base',
        option: 'text-base',
        countrySelectWidth: 'w-[120px]',
        selectedMaxHeight: 'max-h-[24px]',
        flag: '-mt-[9px]! rounded-xs'
      },
      lg: { 
        input: 'px-5 py-3 text-lg',
        selected: 'text-lg',
        option: 'text-lg',
        countrySelectWidth: 'w-[120px]',
        selectedMaxHeight: 'max-h-[28px]',
        flag: '-mt-[9px]! rounded-xs'
      }
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
