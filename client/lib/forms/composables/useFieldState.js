import { computed, toValue } from 'vue'
import FormLogicPropertyResolver from '~/lib/forms/FormLogicPropertyResolver.js'

/**
 * Centralized field state resolver for a form instance.
 * Required inputs: formDataRef, formRef (with properties), strategyRef.
 * Provides a big computed map to minimize recomputation and a getState(field) accessor.
 *
 * Unified state shape:
 * { hidden, required, disabled, effectiveDisabled, hiddenIndicator }
 */
export function useFieldState(formDataRef, formRef, strategyRef) {
  function resolveRawState(field) {
    try {
      const currentData = toValue(formDataRef) || {}
      const resolver = new FormLogicPropertyResolver(field, currentData)
      return {
        hidden: !!resolver.isHidden(),
        required: !!resolver.isRequired(),
        disabled: !!resolver.isDisabled()
      }
    } catch {
      return {
        hidden: !!field?.hidden,
        required: !!field?.required,
        disabled: !!field?.disabled
      }
    }
  }

  function withDerived(raw, strategy) {
    const effectiveDisabled = strategy?.display?.disableFields === true
      ? true
      : (strategy?.display?.enableDisabledFields === true ? false : !!raw.disabled)
    const hiddenIndicator = !!(strategy?.display?.showHiddenFields === true && raw.hidden)
    return {
      hidden: raw.hidden,
      required: raw.required,
      disabled: raw.disabled,
      effectiveDisabled,
      hiddenIndicator
    }
  }

  // Big computed map keyed by field.id over formRef.properties
  const statesById = computed(() => {
    const form = toValue(formRef) || {}
    const strategy = toValue(strategyRef) || {}
    const properties = form?.properties || []
    const map = new Map()
    for (const field of properties) {
      if (!field || !field.id) continue
      const raw = resolveRawState(field)
      map.set(field.id, withDerived(raw, strategy))
    }
    return map
  })

  /**
   * Get the unified state for a given field; prefers precomputed map entry.
   */
  function getState(field) {
    const strategy = toValue(strategyRef) || {}
    if (field?.id && statesById.value.has(field.id)) return statesById.value.get(field.id)
    const raw = resolveRawState(field)
    return withDerived(raw, strategy)
  }

  return {
    getState,
    statesById
  }
}
