/**
 * SelectInput tailwind-variants configuration
 * Handles text sizing for selected values and options plus help text styling
 */
export const selectInputTheme = {
  slots: {
    selected: '',
    option: '',
    help: 'text-neutral-500'
  },
  variants: {
    size: {
      xs: { selected: 'text-xs', option: 'text-xs' },
      sm: { selected: 'text-sm', option: 'text-sm' },
      md: { selected: 'text-base', option: 'text-base' },
      lg: { selected: 'text-lg', option: 'text-lg' }
    }
  },
  defaultVariants: {
    size: 'md'
  }
}
