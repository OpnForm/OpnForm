import { describe, expect, it } from 'vitest'
import { tv } from 'tailwind-variants'
import { imageInputTheme } from '../../lib/forms/themes/image-input.theme.js'
import { ratingInputTheme } from '../../lib/forms/themes/rating-input.theme.js'
import { signatureInputTheme } from '../../lib/forms/themes/signature-input.theme.js'
import { sliderInputTheme } from '../../lib/forms/themes/slider-input.theme.js'
import { textInputTheme } from '../../lib/forms/themes/text-input.theme.js'
import { codeInputTheme } from '../../lib/forms/themes/code-input.theme.js'
import { mentionInputTheme } from '../../lib/forms/themes/mention-input.theme.js'
import { focusedSelectorInputTheme } from '../../lib/forms/themes/focused-selector-input.theme.js'
import { optionSelectorInputTheme } from '../../lib/forms/themes/option-selector-input.theme.js'
import { vCheckboxTheme } from '../../lib/forms/themes/v-checkbox.theme.js'

describe('form focus themes', () => {
  it.each([
    ['image', tv(imageInputTheme)().button()],
    ['rating', tv(ratingInputTheme)().star()],
    ['signature', tv(signatureInputTheme)().container()]
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

  it.each([
    ['code', tv(codeInputTheme)({ theme: 'simple' }).container()],
    ['mention', tv(mentionInputTheme)({ theme: 'simple' }).input()],
  ])('keeps a visible focus indicator on the simple %s editor', (_name, classes) => {
    expect(classes).toContain('var(--form-focus-color)')
    expect(classes).toMatch(/focus(?:-within)?:shadow-/)
  })

  it.each([
    ['focused selector', tv(focusedSelectorInputTheme)().container()],
    ['option selector', tv(optionSelectorInputTheme)().container()],
  ])('shows keyboard focus on the %s before an option is focused', (_name, classes) => {
    expect(classes).toContain('focus-visible:shadow-')
  })

  it.each(['default', 'simple', 'notion', 'minimal', 'transparent'])('respects reduced motion in the %s theme', theme => {
    expect(tv(textInputTheme)({ theme }).input()).toContain('motion-reduce:transition-none')
  })

  it.each([
    ['checkbox', tv(vCheckboxTheme)().control()],
    ['slider', tv(sliderInputTheme)().control()],
  ])('draws native %s keyboard focus around its wrapper for WebKit', (_name, classes) => {
    expect(classes).toContain('duration-200')
    expect(classes).toContain('motion-reduce:transition-none')
    expect(classes).toContain('has-[:focus-visible]:shadow-')
    expect(classes).toContain('var(--form-focus-color)')
  })
})
