/**
 * RadioButtonIcon tailwind-variants configuration
 * Used for radio button icons in FlatSelectInput and similar components
 * Colors match text input border colors for consistency
 */
export const radioButtonIconTheme = {
  slots: {
    checkedIcon: [
      'block',
      'text-[var(--form-color,#3B82F6)]'
    ],
    uncheckedIcon: [
      'block'
    ]
  },
  variants: {
    theme: {
      default: {
        uncheckedIcon: 'text-neutral-300 dark:text-neutral-600'
      },
      simple: {
        uncheckedIcon: 'text-neutral-300 dark:text-neutral-600'
      },
      notion: {
        uncheckedIcon: 'text-notion-input-border dark:text-notion-input-borderDark'
      },
      minimal: {
        uncheckedIcon: 'text-neutral-400 dark:text-neutral-600'
      },
      transparent: {
        uncheckedIcon: 'text-neutral-300 dark:text-neutral-600'
      }
    },
    size: {
      xs: {
        checkedIcon: 'w-3 h-3',
        uncheckedIcon: 'w-3 h-3'
      },
      sm: {
        checkedIcon: 'w-4 h-4',
        uncheckedIcon: 'w-4 h-4'
      },
      md: {
        checkedIcon: 'w-5 h-5',
        uncheckedIcon: 'w-5 h-5'
      },
      lg: {
        checkedIcon: 'w-6 h-6 mx-1',
        uncheckedIcon: 'w-6 h-6 mx-1'
      }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'md'
  }
}