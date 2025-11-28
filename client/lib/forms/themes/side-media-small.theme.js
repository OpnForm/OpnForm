/**
 * SideMediaSmall tailwind-variants configuration
 */
export const sideMediaSmallTheme = {
  slots: {
    mediaContainer: '',
    mediaComponent: 'flex @3xl:justify-center align-top',
    mediaImg: 'inline-block h-auto w-auto max-w-full max-h-[236px] xl:max-h-[292px] object-contain transition-opacity duration-300'
  },
  variants: {
    borderRadius: {
      none: {
        mediaImg: 'rounded-none'
      },
      small: {
        mediaImg: 'rounded-md'
      },
      full: {
        mediaImg: 'rounded-[20px]'
      }
    }
  },
  defaultVariants: {
    borderRadius: 'small'
  }
}


