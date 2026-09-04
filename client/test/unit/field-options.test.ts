import { describe, expect, it } from 'vitest'
import { getFieldOptions } from '../../lib/forms/field-options.js'

describe('getFieldOptions', () => {
  it('reads public API options from the field root', () => {
    expect(getFieldOptions({
      type: 'select',
      options: [{ name: '1-10', id: 'team-size-small' }],
    })).toEqual([{ name: '1-10', value: '1-10', image: null }])
  })

  it('keeps supporting nested editor options', () => {
    expect(getFieldOptions({
      type: 'multi_select',
      multi_select: { options: [{ name: 'Product', image: 'product.png' }] },
    })).toEqual([{ name: 'Product', value: 'Product', image: 'product.png' }])
  })
})
