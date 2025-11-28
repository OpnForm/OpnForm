/**
 * ColorInput tailwind-variants configuration
 */
export const colorInputTheme = {
  slots: {
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

