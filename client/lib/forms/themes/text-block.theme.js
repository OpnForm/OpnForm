/**
 * TextBlock tailwind-variants configuration
 */
export const textBlockTheme = {
  slots: {
    root: 'break-words whitespace-break-spaces [&>h1]:mb-2 [&>h2]:mb-1'
  },
  variants: {
    size: {
      xs: { root: 'text-xs' },
      sm: { root: 'text-sm' },
      md: { root: 'text-base' },
      lg: { root: 'text-lg' }
    }
  },
  defaultVariants: {
    size: 'md'
  }
}

