import { describe, expect, it } from 'vitest'
import { tv } from 'tailwind-variants'
import { imageInputTheme } from '../../lib/forms/themes/image-input.theme.js'
import { ratingInputTheme } from '../../lib/forms/themes/rating-input.theme.js'
import { signatureInputTheme } from '../../lib/forms/themes/signature-input.theme.js'
import { sliderInputTheme } from '../../lib/forms/themes/slider-input.theme.js'
import { textInputTheme } from '../../lib/forms/themes/text-input.theme.js'

describe('form focus themes', () => {
  it.each([
    ['image', tv(imageInputTheme)().button()],
    ['rating', tv(ratingInputTheme)().star()],
    ['signature', tv(signatureInputTheme)().container()],
    ['slider', tv(sliderInputTheme)().slider()]
  ])('adds the shared animated focus treatment to %s controls', (_name, classes) => {
    expect(classes).toContain('duration-200')
    expect(classes).toContain('var(--form-focus-color)')
    expect(classes).toMatch(/focus(?:-visible)?:shadow-/)
  })

  it('uses the validation-aware focus color for the transparent underline', () => {
    const classes = tv(textInputTheme)({ theme: 'transparent' }).input()

    expect(classes).toContain('var(--form-focus-color)')
    expect(classes).not.toContain('var(--color-form)')
  })
})
