/**
 * OptionSelectorInput tailwind-variants configuration
 */
export const optionSelectorInputTheme = {
  slots: {
    option: [
      'w-full border transition-colors shadow-xs',
      'relative'
    ],
    button: [
      'flex flex-col items-center justify-center transition-colors',
      'text-neutral-500 focus:outline-hidden w-full h-full'
    ],
    label: ''
  },
  variants: {
    theme: {
      default: {
        option: ['border-neutral-300', 'hover:bg-neutral-100']
      },
      minimal: {
        option: ['border-2 border-transparent', 'bg-neutral-100', 'hover:bg-neutral-200/50']
      },
      notion: {
        option: ['border-notion-input-border dark:border-notion-input-borderDark', 'hover:backdrop-brightness-95']
      }
    },
    size: {
      xs: { button: 'p-1', label: 'text-[0.7rem]' },
      sm: { button: 'p-1.5', label: 'text-xs' },
      md: { button: 'p-2', label: 'text-sm' },
      lg: { button: 'p-3', label: 'text-base' }
    },
    seamless: {
      true: { option: ['first:rounded-l-lg last:rounded-r-lg not-first:not-last:rounded-none', 'relative focus-within:z-10'] },
      false: { option: 'rounded-lg' }
    },
    selected: {
      true: { option: ['bg-form/10', 'hover:bg-form/10', 'border-form', 'relative z-10'] }
    },
    disabled: {
      true: { button: 'opacity-50 pointer-events-none' }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'sm',
    seamless: false,
    selected: false,
    disabled: false
  }
}

