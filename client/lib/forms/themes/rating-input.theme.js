/**
 * RatingInput tailwind-variants configuration
 */
export const ratingInputTheme = {
  slots: {
    root: 'inline-flex items-center',
    icon: 'w-full h-full',
    character: 'inline-flex items-center justify-center w-full h-full leading-none select-none transition-opacity overflow-hidden',
    image: 'w-full h-full object-contain transition-all',
    star: 'cursor-pointer inline-flex items-center justify-center shrink-0 overflow-hidden text-neutral-200 dark:text-neutral-700 focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:rounded-full focus-visible:outline-none'
  },
  variants: {
    theme: {
      minimal: {
        star: 'border-2 border-transparent focus-visible:ring-0 focus-visible:border-form rounded-full'
      }
    },
    size: {
      xs: {
        star: 'w-4 h-4',
        character: 'text-sm'
      },
      sm: {
        star: 'w-6 h-6',
        character: 'text-lg'
      },
      md: {
        star: 'w-8 h-8',
        character: 'text-2xl'
      },
      lg: {
        star: 'w-10 h-10',
        character: 'text-3xl'
      }
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
  compoundVariants: [
    {
      isActive: true,
      class: {
        character: 'opacity-100',
        image: 'opacity-100 grayscale-0'
      }
    },
    {
      isActive: false,
      isHover: true,
      class: {
        character: 'opacity-60',
        image: 'opacity-60 grayscale-0'
      }
    },
    {
      isActive: false,
      isHover: false,
      class: {
        character: 'opacity-30',
        image: 'opacity-30 grayscale'
      }
    }
  ],
  defaultVariants: {
    size: 'md',
    disabled: false,
    isActive: false,
    isHover: false
  }
}
