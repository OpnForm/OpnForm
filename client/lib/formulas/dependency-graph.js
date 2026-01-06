import { FormulaError } from './types.js'
import { Validator } from './validator.js'

/**
 * Dependency graph for computed variables
 * Handles dependency resolution and cycle detection
 */
export class DependencyGraph {
  constructor() {
    this.nodes = new Map() // id -> { id, formula, dependencies: Set }
    this.dependents = new Map() // id -> Set of ids that depend on this
  }

  /**
   * Add a computed variable to the graph
   */
  addVariable(variable) {
    const dependencies = this.extractDependencies(variable.formula)
    
    this.nodes.set(variable.id, {
      id: variable.id,
      name: variable.name,
      formula: variable.formula,
      dependencies: new Set(dependencies)
    })

    // Update reverse dependency map
    for (const depId of dependencies) {
      if (!this.dependents.has(depId)) {
        this.dependents.set(depId, new Set())
      }
      this.dependents.get(depId).add(variable.id)
    }
  }

  /**
   * Remove a variable from the graph
   */
  removeVariable(variableId) {
    const node = this.nodes.get(variableId)
    if (!node) return

    // Remove from dependents map
    for (const depId of node.dependencies) {
      const deps = this.dependents.get(depId)
      if (deps) {
        deps.delete(variableId)
      }
    }

    this.nodes.delete(variableId)
    this.dependents.delete(variableId)
  }

  /**
   * Extract field/variable references from a formula
   */
  extractDependencies(formula) {
    return Validator.extractFieldReferences(formula)
  }

  /**
   * Check for circular dependencies
   * Returns array of cycles found, or empty array if none
   */
  detectCycles() {
    const cycles = []
    const visited = new Set()
    const recursionStack = new Set()
    const path = []

    const dfs = (nodeId) => {
      if (recursionStack.has(nodeId)) {
        // Found a cycle - extract the cycle path
        const cycleStart = path.indexOf(nodeId)
        const cycle = path.slice(cycleStart)
        cycle.push(nodeId)
        cycles.push(cycle)
        return true
      }

      if (visited.has(nodeId)) {
        return false
      }

      visited.add(nodeId)
      recursionStack.add(nodeId)
      path.push(nodeId)

      const node = this.nodes.get(nodeId)
      if (node) {
        for (const depId of node.dependencies) {
          // Only check dependencies that are computed variables
          if (this.nodes.has(depId)) {
            dfs(depId)
          }
        }
      }

      path.pop()
      recursionStack.delete(nodeId)
      return false
    }

    for (const nodeId of this.nodes.keys()) {
      if (!visited.has(nodeId)) {
        dfs(nodeId)
      }
    }

    return cycles
  }

  /**
   * Get topologically sorted order for evaluation
   * Throws if there are cycles
   */
  getEvaluationOrder() {
    const cycles = this.detectCycles()
    if (cycles.length > 0) {
      const cycleStr = cycles[0].join(' → ')
      throw new FormulaError(`Circular dependency detected: ${cycleStr}`)
    }

    const sorted = []
    const visited = new Set()
    const temp = new Set()

    const visit = (nodeId) => {
      if (visited.has(nodeId)) return
      if (temp.has(nodeId)) return // Already being processed

      temp.add(nodeId)

      const node = this.nodes.get(nodeId)
      if (node) {
        for (const depId of node.dependencies) {
          if (this.nodes.has(depId)) {
            visit(depId)
          }
        }
      }

      temp.delete(nodeId)
      visited.add(nodeId)
      sorted.push(nodeId)
    }

    for (const nodeId of this.nodes.keys()) {
      visit(nodeId)
    }

    return sorted
  }

  /**
   * Get variables that depend on a given field or variable
   */
  getDependents(id) {
    const result = new Set()
    const queue = [id]

    while (queue.length > 0) {
      const current = queue.shift()
      const deps = this.dependents.get(current)
      
      if (deps) {
        for (const depId of deps) {
          if (!result.has(depId)) {
            result.add(depId)
            queue.push(depId)
          }
        }
      }
    }

    return Array.from(result)
  }

  /**
   * Get all dependencies for a variable (including transitive)
   */
  getAllDependencies(variableId) {
    const result = new Set()
    const queue = [variableId]
    const visited = new Set()

    while (queue.length > 0) {
      const current = queue.shift()
      if (visited.has(current)) continue
      visited.add(current)

      const node = this.nodes.get(current)
      if (node) {
        for (const depId of node.dependencies) {
          result.add(depId)
          if (this.nodes.has(depId)) {
            queue.push(depId)
          }
        }
      }
    }

    return Array.from(result)
  }

  /**
   * Build graph from array of computed variables
   */
  static fromVariables(variables) {
    const graph = new DependencyGraph()
    
    for (const variable of variables) {
      graph.addVariable(variable)
    }

    return graph
  }

  /**
   * Validate that adding/updating a variable won't create cycles
   */
  wouldCreateCycle(variable) {
    // Create a temporary graph with the new/updated variable
    const tempGraph = new DependencyGraph()
    
    // Add all existing nodes except the one being updated
    for (const [id, node] of this.nodes) {
      if (id !== variable.id) {
        tempGraph.addVariable(node)
      }
    }
    
    // Add the new/updated variable
    tempGraph.addVariable(variable)
    
    return tempGraph.detectCycles().length > 0
  }
}

/**
 * Build dependency graph from computed variables
 */
export function buildDependencyGraph(variables) {
  return DependencyGraph.fromVariables(variables)
}
