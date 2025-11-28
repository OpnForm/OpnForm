import { describe, it, expect, beforeEach, vi } from 'vitest'
import { useFormInput } from '../../components/forms/useFormInput.js'

/**
 * Test suite for useFormInput composable
 * Validates form data binding, nested property handling, validation, and model value emission
 */

describe('useFormInput', () => {
  let mockForm
  let mockContext

  beforeEach(() => {
    // Create a mock form object with validation support
    mockForm = {
      simple_field: 'initial_value',
      nested: {
        field: 'nested_value'
      },
      errors: {
        has: vi.fn((name) => false),
        clear: vi.fn()
      }
    }

    // Create mock context for emitting events
    mockContext = {
      emit: vi.fn()
    }
  })

  describe('Simple Property Access', () => {
    it('should read simple form properties', () => {
      const props = {
        name: 'simple_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      expect(compVal.value).toBe('initial_value')
    })

    it('should write simple form properties', () => {
      const props = {
        name: 'simple_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'new_value'
      expect(mockForm.simple_field).toBe('new_value')
    })

    it('should emit update:modelValue when setting simple property', () => {
      const props = {
        name: 'simple_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'new_value'
      expect(mockContext.emit).toHaveBeenCalledWith('update:modelValue', 'new_value')
    })

    it('should use modelValue when form is not provided', () => {
      const props = {
        name: 'field',
        form: undefined,
        modelValue: 'local_value'
      }

      const { compVal } = useFormInput(props, mockContext)
      expect(compVal.value).toBe('local_value')
    })

    it('should update local content when form is not provided', () => {
      const props = {
        name: 'field',
        form: undefined,
        modelValue: 'initial'
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'updated'
      expect(mockContext.emit).toHaveBeenCalledWith('update:modelValue', 'updated')
    })
  })

  describe('Nested Property Access', () => {
    it('should read existing nested properties', () => {
      const props = {
        name: 'nested.field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      expect(compVal.value).toBe('nested_value')
    })

    it('should write to existing nested properties', () => {
      const props = {
        name: 'nested.field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'updated_nested'
      expect(mockForm.nested.field).toBe('updated_nested')
    })

    it('should create missing intermediate objects in nested paths', () => {
      const props = {
        name: 'address.street.number',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = '42'
      
      expect(mockForm.address).toBeDefined()
      expect(mockForm.address.street).toBeDefined()
      expect(mockForm.address.street.number).toBe('42')
    })

    it('should handle multiple levels of nested paths', () => {
      const props = {
        name: 'level1.level2.level3.level4.field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'deep_value'
      
      expect(mockForm.level1.level2.level3.level4.field).toBe('deep_value')
    })

    it('should replace non-object values in nested paths', () => {
      mockForm.path = 'string_value' // Non-object value
      
      const props = {
        name: 'path.to.field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'new_value'
      
      expect(mockForm.path.to.field).toBe('new_value')
    })

    it('should handle null values in path and replace with objects', () => {
      mockForm.nullable = null

      const props = {
        name: 'nullable.nested.field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'value'
      
      expect(mockForm.nullable.nested.field).toBe('value')
    })
  })

  describe('Form Prefix Key', () => {
    it('should prepend formPrefixKey to property names', () => {
      const props = {
        name: 'field',
        form: mockForm,
        modelValue: undefined
      }

      const options = {
        formPrefixKey: 'prefix.'
      }

      mockForm.prefix = { field: 'prefixed_value' }
      const { compVal } = useFormInput(props, mockContext, options)
      
      expect(compVal.value).toBe('prefixed_value')
    })

    it('should combine formPrefixKey with nested paths', () => {
      const props = {
        name: 'nested.field',
        form: mockForm,
        modelValue: undefined
      }

      const options = {
        formPrefixKey: 'meta.'
      }

      const { compVal } = useFormInput(props, mockContext, options)
      compVal.value = 'value'
      
      expect(mockForm.meta.nested.field).toBe('value')
    })
  })

  describe('Validation Error Handling', () => {
    it('should clear validation errors when value is set', () => {
      mockForm.errors.has = vi.fn(() => true)

      const props = {
        name: 'simple_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'new_value'
      
      expect(mockForm.errors.clear).toHaveBeenCalledWith('simple_field')
    })

    it('should not attempt to clear errors when form has no errors object', () => {
      const formWithoutErrors = { simple_field: 'value' }
      
      const props = {
        name: 'simple_field',
        form: formWithoutErrors,
        modelValue: undefined
      }

      // Should not throw
      const { compVal } = useFormInput(props, mockContext)
      expect(() => {
        compVal.value = 'new_value'
      }).not.toThrow()
    })

    it('should detect form has validation when errors object exists', () => {
      const props = {
        name: 'field',
        form: mockForm,
        modelValue: undefined
      }

      const { hasValidation } = useFormInput(props, mockContext)
      expect(hasValidation.value).toBe(true)
    })

    it('should detect no validation when errors object does not exist', () => {
      const formWithoutErrors = { simple_field: 'value' }
      
      const props = {
        name: 'simple_field',
        form: formWithoutErrors,
        modelValue: undefined
      }

      const { hasValidation } = useFormInput(props, mockContext)
      expect(hasValidation.value).toBe(false)
    })

    it('should detect error state correctly', () => {
      mockForm.errors.has = vi.fn((name) => name === 'simple_field')

      const props = {
        name: 'simple_field',
        form: mockForm,
        modelValue: undefined
      }

      const { hasError } = useFormInput(props, mockContext)
      expect(hasError.value).toBe(true)
    })
  })

  describe('Edge Cases', () => {
    it('should handle empty path strings', () => {
      const props = {
        name: '',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      // Empty path will try to get the root form object, which lodash _get handles gracefully
      // The value will be undefined since the root form object is never set as a value
      expect(compVal.value).toBeUndefined()
    })

    it('should handle paths with trailing dots', () => {
      const props = {
        name: 'nested.field.',
        form: mockForm,
        modelValue: undefined
      }

      // Should handle gracefully without error
      const { compVal } = useFormInput(props, mockContext)
      expect(() => {
        compVal.value = 'value'
      }).not.toThrow()
    })

    it('should handle undefined form gracefully', () => {
      const props = {
        name: 'field',
        form: undefined,
        modelValue: 'default'
      }

      const { compVal } = useFormInput(props, mockContext)
      expect(compVal.value).toBe('default')
    })

    it('should handle null form gracefully', () => {
      const props = {
        name: 'field',
        form: null,
        modelValue: 'default'
      }

      const { compVal } = useFormInput(props, mockContext)
      expect(compVal.value).toBe('default')
    })

    it('should handle reading from non-existent nested path', () => {
      const props = {
        name: 'does.not.exist',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      expect(compVal.value).toBeUndefined()
    })

    it('should handle setting boolean values', () => {
      const props = {
        name: 'toggle_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = true
      expect(mockForm.toggle_field).toBe(true)
    })

    it('should handle setting zero and empty string values', () => {
      const props = {
        name: 'numeric_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      
      compVal.value = 0
      expect(mockForm.numeric_field).toBe(0)
      
      compVal.value = ''
      expect(mockForm.numeric_field).toBe('')
    })

    it('should handle array values', () => {
      const props = {
        name: 'array_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      const testArray = [1, 2, 3]
      compVal.value = testArray
      
      expect(mockForm.array_field).toEqual(testArray)
    })

    it('should handle object values', () => {
      const props = {
        name: 'object_field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      const testObject = { a: 1, b: 2 }
      compVal.value = testObject
      
      expect(mockForm.object_field).toEqual(testObject)
    })
  })

  describe('Integration Scenarios', () => {
    it('should handle rapid consecutive updates', () => {
      const props = {
        name: 'field',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      
      compVal.value = 'value1'
      compVal.value = 'value2'
      compVal.value = 'value3'
      
      expect(mockForm.field).toBe('value3')
      expect(mockContext.emit).toHaveBeenCalledTimes(3)
    })

    it('should maintain form reference across multiple field updates', () => {
      const props1 = {
        name: 'field1',
        form: mockForm,
        modelValue: undefined
      }

      const props2 = {
        name: 'field2',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal: compVal1 } = useFormInput(props1, mockContext)
      const { compVal: compVal2 } = useFormInput(props2, mockContext)
      
      compVal1.value = 'value1'
      compVal2.value = 'value2'
      
      expect(mockForm.field1).toBe('value1')
      expect(mockForm.field2).toBe('value2')
    })

    it('should handle complex nested update scenario', () => {
      const props = {
        name: 'user.address.details.apartment.number',
        form: mockForm,
        modelValue: undefined
      }

      const { compVal } = useFormInput(props, mockContext)
      compVal.value = 'Apt 5B'
      
      expect(mockForm.user.address.details.apartment.number).toBe('Apt 5B')
      expect(mockContext.emit).toHaveBeenCalledWith('update:modelValue', 'Apt 5B')
    })
  })
})

