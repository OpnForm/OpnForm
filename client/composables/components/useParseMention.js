import { FormSubmissionFormatter } from '~/components/forms/components/FormSubmissionFormatter'
import { evaluateFormula, buildDependencyGraph } from '~/lib/formulas/index.js'

/**
 * Evaluate computed variables for a form
 */
function evaluateComputedVariables(form, formData) {
  const computedVariables = form?.computed_variables || []
  if (!computedVariables.length) return {}

  // Build context from form data
  const context = { ...formData }
  const results = {}

  // Get evaluation order using dependency graph
  let evaluationOrder
  try {
    const graph = buildDependencyGraph(computedVariables)
    evaluationOrder = graph.getEvaluationOrder()
  } catch {
    // If there's a cycle, just use the order as-is
    evaluationOrder = computedVariables.map(v => v.id)
  }

  // Evaluate in dependency order
  for (const variableId of evaluationOrder) {
    const variable = computedVariables.find(v => v.id === variableId)
    if (!variable) continue

    try {
      const value = evaluateFormula(variable.formula, { ...context, ...results })
      results[variableId] = value
    } catch {
      results[variableId] = null
    }
  }

  return results
}

export function useParseMention(content, mentionsAllowed, form, formData, computedValues = null) {
  if (!mentionsAllowed || !form || !formData) {
    return content
  }

  const formatter = new FormSubmissionFormatter(form, formData).setOutputStringsOnly()
  const formattedData = formatter.getFormattedData()

  // Get computed variable values
  const computedVariableValues = computedValues ?? evaluateComputedVariables(form, formData)

  // Create a new DOMParser
  const parser = new DOMParser()
  // Parse the content as HTML
  const doc = parser.parseFromString(content, 'text/html')

  // Find all elements with mention attribute
  const mentionElements = doc.querySelectorAll('[mention], [mention=""]')

  mentionElements.forEach(element => {
    const fieldId = element.getAttribute('mention-field-id')
    const fallback = element.getAttribute('mention-fallback')
    
    // First check form fields, then computed variables
    let value = formattedData[fieldId]
    if (value === undefined && computedVariableValues[fieldId] !== undefined) {
      value = computedVariableValues[fieldId]
    }

    if (value !== null && value !== undefined) {
      if (Array.isArray(value)) {
        element.textContent = value.join(', ')
      } else {
        element.textContent = String(value)
      }
    } else if (fallback) {
      element.textContent = fallback
    } else {
      element.remove()
    }
  })

  // Return the processed HTML content
  return doc.body.innerHTML
}
