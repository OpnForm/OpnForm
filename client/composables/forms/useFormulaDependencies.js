import { buildDependencyGraph } from '~/lib/formulas/index.js'

/**
 * Composable for managing formula dependencies
 * Used for validation and understanding variable relationships
 */
export function useFormulaDependencies(computedVariables) {
  // Build dependency graph
  const graph = computed(() => {
    const variables = computedVariables.value || []
    if (!variables.length) return null
    
    try {
      return buildDependencyGraph(variables)
    } catch (error) {
      console.warn('Error building dependency graph:', error)
      return null
    }
  })
  
  // Check for circular dependencies
  const cycles = computed(() => {
    if (!graph.value) return []
    return graph.value.detectCycles()
  })
  
  // Check if there are any cycles
  const hasCycles = computed(() => cycles.value.length > 0)
  
  // Get evaluation order
  const evaluationOrder = computed(() => {
    if (!graph.value || hasCycles.value) return []
    
    try {
      return graph.value.getEvaluationOrder()
    } catch {
      return []
    }
  })
  
  // Get variables that depend on a specific field or variable
  const getDependents = (id) => {
    if (!graph.value) return []
    return graph.value.getDependents(id)
  }
  
  // Get all dependencies for a variable (including transitive)
  const getAllDependencies = (variableId) => {
    if (!graph.value) return []
    return graph.value.getAllDependencies(variableId)
  }
  
  // Check if adding/updating a variable would create a cycle
  const wouldCreateCycle = (variable) => {
    if (!graph.value) {
      // Build temporary graph with just this variable
      const tempGraph = buildDependencyGraph([variable])
      return tempGraph.detectCycles().length > 0
    }
    return graph.value.wouldCreateCycle(variable)
  }
  
  // Get variables that reference a specific field
  const getVariablesUsingField = (fieldId) => {
    const variables = computedVariables.value || []
    return variables.filter(v => {
      const deps = getAllDependencies(v.id)
      return deps.includes(fieldId)
    })
  }
  
  return {
    graph,
    cycles,
    hasCycles,
    evaluationOrder,
    getDependents,
    getAllDependencies,
    wouldCreateCycle,
    getVariablesUsingField
  }
}
