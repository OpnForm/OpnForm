import { evaluateFormula, buildDependencyGraph } from '~/lib/formulas/index.js'

/**
 * Composable for managing computed variables during form fill
 * Provides reactive evaluation of computed variables based on form data
 */
export function useComputedVariables(form, formData) {
  // Store for evaluated variable values
  const values = ref({})
  
  // Get computed variables from form
  const computedVariables = computed(() => form.value?.computed_variables || [])
  
  // Get form properties
  const properties = computed(() => form.value?.properties || [])
  
  // Evaluation order (topologically sorted)
  const evaluationOrder = computed(() => {
    const variables = computedVariables.value
    if (!variables.length) return []
    
    try {
      const graph = buildDependencyGraph(variables)
      return graph.getEvaluationOrder()
    } catch (error) {
      // If there's a cycle, just return the IDs in order
      console.warn('Circular dependency in computed variables:', error)
      return variables.map(v => v.id)
    }
  })
  
  // Build context from form data
  const buildContext = () => {
    const context = {}
    
    // Add form field values
    if (formData.value) {
      for (const prop of properties.value) {
        const value = formData.value[prop.id]
        if (value !== undefined) {
          context[prop.id] = value
        }
      }
    }
    
    return context
  }
  
  // Evaluate all variables in dependency order
  const evaluateAll = () => {
    const context = buildContext()
    const newValues = {}
    
    for (const variableId of evaluationOrder.value) {
      const variable = computedVariables.value.find(v => v.id === variableId)
      if (!variable) continue
      
      // Add already evaluated variables to context
      Object.assign(context, newValues)
      
      try {
        newValues[variableId] = evaluateFormula(variable.formula, context)
      } catch (error) {
        console.warn(`Error evaluating variable ${variable.name}:`, error)
        newValues[variableId] = null
      }
    }
    
    values.value = newValues
  }
  
  // Watch for changes in form data and re-evaluate
  watch(
    () => formData.value,
    () => {
      evaluateAll()
    },
    { deep: true, immediate: true }
  )
  
  // Also re-evaluate when computed variables change
  watch(
    computedVariables,
    () => {
      evaluateAll()
    },
    { deep: true }
  )
  
  // Get a specific variable value
  const getValue = (variableId) => {
    return values.value[variableId]
  }
  
  // Get variable by ID with its current value
  const getVariableWithValue = (variableId) => {
    const variable = computedVariables.value.find(v => v.id === variableId)
    if (!variable) return null
    
    return {
      ...variable,
      value: values.value[variableId]
    }
  }
  
  // Get all variables with their current values
  const getAllVariablesWithValues = computed(() => {
    return computedVariables.value.map(v => ({
      ...v,
      value: values.value[v.id]
    }))
  })
  
  // Check if any variables exist
  const hasVariables = computed(() => computedVariables.value.length > 0)
  
  return {
    values,
    computedVariables,
    evaluationOrder,
    evaluateAll,
    getValue,
    getVariableWithValue,
    getAllVariablesWithValues,
    hasVariables
  }
}
