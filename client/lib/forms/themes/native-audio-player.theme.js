/**
 * NativeAudioPlayer tailwind-variants configuration
 */
export const nativeAudioPlayerTheme = {
  slots: {
    container: [
      'flex items-center w-full border',
      'transition-colors duration-200'
    ],
    button: [
      'shrink-0 flex items-center justify-center',
      'text-current hover:opacity-80 transition-opacity'
    ],
    icon: '',
    time: 'tabular-nums shrink-0',
    progress: 'native-audio-player__progress flex-1 min-w-0 cursor-pointer'
  },
  variants: {
    theme: {
      default: {
        container: [
          'border-neutral-300 dark:border-neutral-600',
          'bg-white text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300',
          'shadow-xs'
        ]
      },
      minimal: {
        container: [
          'border-2 border-transparent',
          'bg-neutral-100 text-neutral-700 dark:bg-notion-dark-light dark:text-neutral-300'
        ]
      },
      notion: {
        container: [
          'border-notion-input-border dark:border-notion-input-borderDark',
          'bg-notion-input-background dark:bg-notion-dark-light',
          'text-neutral-900 dark:text-neutral-100'
        ]
      },
      transparent: {
        container: [
          'border-0',
          'bg-transparent dark:bg-transparent',
          'text-neutral-700 dark:text-neutral-300',
          'shadow-[inset_0_-1px_0_0_rgb(212_212_212)] dark:shadow-[inset_0_-1px_0_0_rgb(82_82_82)]',
          '!rounded-none'
        ]
      }
    },
    size: {
      xs: {
        container: 'px-2 py-1 gap-1',
        time: 'text-xs min-w-[2rem]',
        icon: 'h-4 w-4',
        progress: 'h-0.5'
      },
      sm: {
        container: 'px-2.5 py-1.5 gap-1.5',
        time: 'text-xs min-w-[2.25rem]',
        icon: 'h-4 w-4',
        progress: 'h-0.5'
      },
      md: {
        container: 'px-3 py-2 gap-2',
        time: 'text-sm min-w-[2.25rem]',
        icon: 'h-5 w-5',
        progress: 'h-1'
      },
      lg: {
        container: 'px-4 py-3 gap-2',
        time: 'text-base min-w-[2.5rem]',
        icon: 'h-6 w-6',
        progress: 'h-1'
      }
    },
    borderRadius: {
      none: { container: 'rounded-none' },
      small: { container: 'rounded-lg' },
      full: { container: 'rounded-[20px]' }
    }
  },
  defaultVariants: {
    theme: 'default',
    size: 'md',
    borderRadius: 'small'
  }
}
