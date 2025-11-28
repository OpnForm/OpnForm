/**
 * SliderInput tailwind-variants configuration
 */
export const sliderInputTheme = {
  slots: {
    stepLabel: 'text-neutral-700 dark:text-neutral-300 text-center',
    slider: 'w-full mt-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2'
  },
  variants: {
    theme: {
      default: {
        slider: 'focus-visible:ring-form/100'
      },
      simple: {
        slider: 'focus-visible:ring-form/100'
      },
      notion: {
        slider: 'focus-visible:ring-form/40'
      },
      minimal: {
        slider: 'focus-visible:ring-2 focus-visible:ring-form/60'
      }
    },
    size: {
      xs: { stepLabel: 'text-xs' },
      sm: { stepLabel: 'text-sm' },
      md: { stepLabel: 'text-base' },
      lg: { stepLabel: 'text-lg' }
    },
    disabled: {
      true: {
        slider: '!cursor-not-allowed !opacity-50 !focus-visible:ring-0'
      }
    }
  },
  // Legacy theme used text-xs; keep default aligned
  defaultVariants: {
    theme: 'default',
    size: 'xs',
    disabled: false
  }
}
