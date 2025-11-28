/**
 * InputLabel tailwind-variants configuration
 * Used for form input labels
 */
export const inputLabelTheme = {
  slots: {
    label: [
      'input-label inline align-baseline',
      'text-neutral-700 dark:text-neutral-300'
    ],
    requiredDot: 'relative size-[12px] text-red-500 dark:text-red-400 rounded-full bg-red-500/20 dark:bg-red-500/20 required-dot inline-flex items-center justify-center ml-1 align-middle',
    star: 'absolute size-4 left-1/2 top-1/2 -translate-y-1/2 -translate-x-1/2'
  },
  variants: {
    presentation: {
      classic: {},
      focused: {
        label: 'leading-none'
      }
    },
    theme: {
      default: {
        label: 'font-semibold'
      },
      simple: {
        label: 'font-semibold'
      },
      notion: {
        label: 'font-semibold'
      },
      minimal: {
        label: 'font-medium text-neutral-600 dark:text-neutral-400'
      },
      transparent: {
        label: 'font-semibold'
      }
    },
    size: {
      xs: {
        label: 'text-xs'
      },
      sm: {
        label: 'text-xs',
        requiredDot: 'mb-[2px]!'
      },
      md: {
        label: 'text-sm',
        requiredDot: 'mt-[-2px]!'
      },
      lg: {
        label: 'text-base',
        requiredDot: 'mt-[-3px]!'
      }
    },
    uppercaseLabels: {
      true: {
        label: 'uppercase'
      },
      false: {
        label: 'leading-none'
      }
    }
  },
  compoundVariants: [
    // Label font size adjustments for focused presentation (increase by 1 step)
    { presentation: 'focused', size: 'xs', class: { label: 'text-sm leading-none' } },
    { presentation: 'focused', size: 'sm', class: { label: 'text-sm leading-none' } },
    { presentation: 'focused', size: 'md', class: { label: 'text-base leading-none' } },
    { presentation: 'focused', size: 'lg', class: { label: 'text-xl leading-none' } }
  ],
  defaultVariants: {
    theme: 'default',
    size: 'md',
    uppercaseLabels: false,
    presentation: 'classic'
  }
}