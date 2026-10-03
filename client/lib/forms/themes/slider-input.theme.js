import { formControlFocusVisibleWithin, formControlTransition } from './focus-ring.theme.js'

/**
 * SliderInput tailwind-variants configuration
 */
export const sliderInputTheme = {
  slots: {
    stepLabel: 'text-neutral-700 dark:text-neutral-300 text-center',
    control: ['mt-3 rounded', formControlTransition, formControlFocusVisibleWithin],
    slider: 'block w-full focus-visible:outline-none focus-visible:ring-0'
  },
  variants: {
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
