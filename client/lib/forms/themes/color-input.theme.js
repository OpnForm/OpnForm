import { formControlFocus, formControlTransition } from './focus-ring.theme.js'

/**
 * ColorInput tailwind-variants configuration
 */
export const colorInputTheme = {
  slots: {
    input: [
      'h-8 w-10 cursor-pointer rounded-md border border-neutral-300 bg-white p-0.5',
      'dark:border-neutral-600 dark:bg-notion-dark-light',
      'focus:outline-hidden',
      formControlTransition,
      formControlFocus
    ],
    label: '',
    help: 'text-neutral-500'
  },
  variants: {
    size: {
      xs: { label: 'text-xs' },
      sm: { label: 'text-sm' },
      md: { label: 'text-base' },
      lg: { label: 'text-lg' }
    }
  },
  defaultVariants: {
    size: 'md'
  }
}
