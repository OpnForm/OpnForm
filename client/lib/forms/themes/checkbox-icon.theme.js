/**
 * CheckboxIcon tailwind-variants configuration
 * Used for checkbox icons in FlatSelectInput and similar components
 * Colors match text input border colors for consistency
 */
export const checkboxIconTheme = {
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
        checkedIcon: 'size-3',
        uncheckedIcon: 'size-3'
      },
      sm: {
        checkedIcon: 'size-4',
        uncheckedIcon: 'size-4'
      },
      md: {
        checkedIcon: 'size-5',
        uncheckedIcon: 'size-5'
      },
      lg: {
        checkedIcon: 'size-6 mx-1',
        uncheckedIcon: 'size-6 mx-1'
      }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'md'
  }
}