export const formControlTransition = [
  'transition-[color,background-color,border-color,box-shadow]',
  'duration-200',
  'ease-out'
]

export const formControlFocus = [
  'focus:ring-0',
  'focus:border-[var(--form-focus-color)]',
  'focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--form-focus-color)_24%,transparent)]'
]

export const formControlFocusVisible = [
  'focus-visible:ring-0',
  'focus-visible:border-[var(--form-focus-color)]',
  'focus-visible:shadow-[0_0_0_3px_color-mix(in_srgb,var(--form-focus-color)_24%,transparent)]'
]

export const formControlFocusVisibleHalo = [
  'focus-visible:ring-0',
  'focus-visible:outline-none',
  'focus-visible:shadow-[0_0_0_3px_color-mix(in_srgb,var(--form-focus-color)_24%,transparent)]'
]

export const formControlFocusWithin = [
  'focus-within:ring-0',
  'focus-within:border-[var(--form-focus-color)]',
  'focus-within:shadow-[0_0_0_3px_color-mix(in_srgb,var(--form-focus-color)_24%,transparent)]'
]

export const formControlFocusVisibleInset = [
  'focus-visible:ring-0',
  'focus-visible:border-[var(--form-focus-color)]',
  'focus-visible:shadow-[inset_0_0_0_2px_color-mix(in_srgb,var(--form-focus-color)_44%,transparent)]'
]

export const formControlActive = [
  'ring-0',
  'border-[var(--form-focus-color)]',
  'shadow-[0_0_0_3px_color-mix(in_srgb,var(--form-focus-color)_24%,transparent)]',
  'outline-none'
]
