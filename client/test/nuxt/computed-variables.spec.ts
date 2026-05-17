import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, nextTick } from 'vue'
import { useComputedVariables } from '~/composables/forms/useComputedVariables.js'

describe('useComputedVariables', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns empty values when no variables exist', async () => {
    const form = ref({ computed_variables: [], properties: [] })
    const formData = ref({})

    const { values, hasVariables } = useComputedVariables(form, formData)

    await nextTick()
    expect(values.value).toEqual({})
    expect(hasVariables.value).toBe(false)
  })

  it('evaluates simple computed variable', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_double', name: 'Double', formula: '{num} * 2' }
      ],
      properties: [{ id: 'num', name: 'Number' }]
    })
    const formData = ref({ num: 5 })

    const { values, hasVariables, evaluateAll } = useComputedVariables(form, formData)
    
    // Manually trigger evaluation for test
    evaluateAll()
    await nextTick()
    
    expect(hasVariables.value).toBe(true)
    expect(values.value.cv_double).toBe(10)
  })

  it('evaluates chained computed variables', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_subtotal', name: 'Subtotal', formula: '{price} * {qty}' },
        { id: 'cv_tax', name: 'Tax', formula: '{cv_subtotal} * 0.1' },
        { id: 'cv_total', name: 'Total', formula: '{cv_subtotal} + {cv_tax}' }
      ],
      properties: [
        { id: 'price', name: 'Price' },
        { id: 'qty', name: 'Quantity' }
      ]
    })
    const formData = ref({ price: 100, qty: 2 })

    const { values, evaluateAll } = useComputedVariables(form, formData)
    
    evaluateAll()
    await nextTick()
    
    expect(values.value.cv_subtotal).toBe(200)
    expect(values.value.cv_tax).toBe(20)
    expect(values.value.cv_total).toBe(220)
  })

  it('updates values when form data changes', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_sum', name: 'Sum', formula: '{a} + {b}' }
      ],
      properties: [
        { id: 'a', name: 'A' },
        { id: 'b', name: 'B' }
      ]
    })
    const formData = ref({ a: 1, b: 2 })

    const { values, evaluateAll } = useComputedVariables(form, formData)

    evaluateAll()
    await nextTick()
    expect(values.value.cv_sum).toBe(3)

    // Update form data
    formData.value = { a: 5, b: 10 }
    await nextTick()
    expect(values.value.cv_sum).toBe(15)
  })

  it('handles getValue helper', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_test', name: 'Test', formula: '42' }
      ],
      properties: []
    })
    const formData = ref({})

    const { getValue, evaluateAll } = useComputedVariables(form, formData)

    evaluateAll()
    await nextTick()
    
    expect(getValue('cv_test')).toBe(42)
    expect(getValue('cv_nonexistent')).toBeUndefined()
  })

  it('handles getVariableWithValue helper', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_test', name: 'Test Variable', formula: '100' }
      ],
      properties: []
    })
    const formData = ref({})

    const { getVariableWithValue, evaluateAll } = useComputedVariables(form, formData)

    evaluateAll()
    await nextTick()
    
    const variable = getVariableWithValue('cv_test')
    expect(variable).not.toBeNull()
    expect(variable?.id).toBe('cv_test')
    expect(variable?.name).toBe('Test Variable')
    expect(variable?.value).toBe(100)
  })

  it('provides getAllVariablesWithValues computed', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_a', name: 'A', formula: '1' },
        { id: 'cv_b', name: 'B', formula: '2' }
      ],
      properties: []
    })
    const formData = ref({})

    const { getAllVariablesWithValues, evaluateAll } = useComputedVariables(form, formData)

    evaluateAll()
    await nextTick()
    
    expect(getAllVariablesWithValues.value).toHaveLength(2)
    expect(getAllVariablesWithValues.value[0].value).toBe(1)
    expect(getAllVariablesWithValues.value[1].value).toBe(2)
  })

  it('handles formula errors gracefully', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_invalid', name: 'Invalid', formula: '{a} / {b}' }
      ],
      properties: [
        { id: 'a', name: 'A' },
        { id: 'b', name: 'B' }
      ]
    })
    // Division by zero
    const formData = ref({ a: 10, b: 0 })

    const { values, evaluateAll } = useComputedVariables(form, formData)

    evaluateAll()
    await nextTick()
    
    expect(values.value.cv_invalid).toBe(null)
  })

  it('provides evaluation order', async () => {
    const form = ref({
      computed_variables: [
        { id: 'cv_c', name: 'C', formula: '{cv_a} + {cv_b}' },
        { id: 'cv_a', name: 'A', formula: '1' },
        { id: 'cv_b', name: 'B', formula: '{cv_a} * 2' }
      ],
      properties: []
    })
    const formData = ref({})

    const { evaluationOrder } = useComputedVariables(form, formData)

    await nextTick()
    // cv_a should come before cv_b (which depends on it)
    // cv_b should come before cv_c (which depends on it)
    const aIdx = evaluationOrder.value.indexOf('cv_a')
    const bIdx = evaluationOrder.value.indexOf('cv_b')
    const cIdx = evaluationOrder.value.indexOf('cv_c')
    
    expect(aIdx).toBeLessThan(bIdx)
    expect(bIdx).toBeLessThan(cIdx)
  })
})
