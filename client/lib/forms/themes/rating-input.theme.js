import { formControlFocusVisibleInset, formControlTransition } from './focus-ring.theme.js'

/**
 * RatingInput tailwind-variants configuration
 */
export const ratingInputTheme = {
  slots: {
    icon: '',
    star: [
      'cursor-pointer inline-block text-neutral-200 dark:text-neutral-700',
      'border-2 border-transparent rounded-full focus-visible:outline-none',
      formControlTransition,
      formControlFocusVisibleInset
    ]
  },
  variants: {
    theme: {
      minimal: {
        star: 'border-2 border-transparent rounded-full'
      }
    },
    size: {
      xs: { icon: 'w-4 h-4' },
      sm: { icon: 'w-6 h-6' },
      md: { icon: 'w-8 h-8' },
      lg: { icon: 'w-10 h-10' }
    },
    disabled: {
      true: {
        star: '!cursor-not-allowed'
      }
    },
    isActive: {
      true: {
        star: '!text-yellow-400'
      }
    },
    isHover: {
      true: {
        star: '!text-yellow-200 !dark:text-yellow-800'
      }
    }
  },
  defaultVariants: {
    size: 'md',
    disabled: false,
    isActive: false,
    isHover: false
  }
}
