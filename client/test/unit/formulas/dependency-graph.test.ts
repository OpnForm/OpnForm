import { describe, it, expect } from 'vitest'
import { DependencyGraph, buildDependencyGraph } from '../../../lib/formulas/dependency-graph.js'

describe('Dependency Graph', () => {
  describe('addVariable', () => {
    it('adds variables to the graph', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_1', name: 'Var 1', formula: '{field1} + 1' })
      
      const order = graph.getEvaluationOrder()
      expect(order).toContain('cv_1')
    })

    it('extracts dependencies from formula', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_1', name: 'Var 1', formula: '{field1} + {field2}' })
      
      const deps = graph.getAllDependencies('cv_1')
      expect(deps).toContain('field1')
      expect(deps).toContain('field2')
    })
  })

  describe('removeVariable', () => {
    it('removes variables from the graph', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_1', name: 'Var 1', formula: '1' })
      graph.addVariable({ id: 'cv_2', name: 'Var 2', formula: '2' })
      
      graph.removeVariable('cv_1')
      
      const order = graph.getEvaluationOrder()
      expect(order).not.toContain('cv_1')
      expect(order).toContain('cv_2')
    })
  })

  describe('cycle detection', () => {
    it('detects direct cycles', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{cv_b}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{cv_a}' })
      
      const cycles = graph.detectCycles()
      expect(cycles.length).toBeGreaterThan(0)
    })

    it('detects indirect cycles', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{cv_b}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{cv_c}' })
      graph.addVariable({ id: 'cv_c', name: 'C', formula: '{cv_a}' })
      
      const cycles = graph.detectCycles()
      expect(cycles.length).toBeGreaterThan(0)
    })

    it('returns empty array when no cycles', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{field1}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{cv_a} + {field2}' })
      
      const cycles = graph.detectCycles()
      expect(cycles).toHaveLength(0)
    })
  })

  describe('evaluation order', () => {
    it('returns topologically sorted order', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_total', name: 'Total', formula: '{cv_subtotal} + {cv_tax}' })
      graph.addVariable({ id: 'cv_subtotal', name: 'Subtotal', formula: '{price} * {qty}' })
      graph.addVariable({ id: 'cv_tax', name: 'Tax', formula: '{cv_subtotal} * 0.1' })
      
      const order = graph.getEvaluationOrder()
      
      // cv_subtotal should come before cv_tax and cv_total
      expect(order.indexOf('cv_subtotal')).toBeLessThan(order.indexOf('cv_tax'))
      expect(order.indexOf('cv_subtotal')).toBeLessThan(order.indexOf('cv_total'))
      expect(order.indexOf('cv_tax')).toBeLessThan(order.indexOf('cv_total'))
    })

    it('throws error when there are cycles', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{cv_b}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{cv_a}' })
      
      expect(() => graph.getEvaluationOrder()).toThrow('Circular dependency')
    })
  })

  describe('getDependents', () => {
    it('returns variables that depend on a field', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{field1}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{field1} + {field2}' })
      graph.addVariable({ id: 'cv_c', name: 'C', formula: '{cv_a}' })
      
      const dependents = graph.getDependents('field1')
      
      expect(dependents).toContain('cv_a')
      expect(dependents).toContain('cv_b')
      expect(dependents).toContain('cv_c') // Transitively depends on field1 through cv_a
    })
  })

  describe('wouldCreateCycle', () => {
    it('returns true if adding variable would create cycle', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{cv_b}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{field1}' })
      
      const wouldCycle = graph.wouldCreateCycle({ 
        id: 'cv_b', 
        name: 'B', 
        formula: '{cv_a}' 
      })
      
      expect(wouldCycle).toBe(true)
    })

    it('returns false if change is safe', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{field1}' })
      
      const wouldCycle = graph.wouldCreateCycle({ 
        id: 'cv_b', 
        name: 'B', 
        formula: '{cv_a}' 
      })
      
      expect(wouldCycle).toBe(false)
    })
  })

  describe('buildDependencyGraph', () => {
    it('creates graph from array of variables', () => {
      const variables = [
        { id: 'cv_1', name: 'Var 1', formula: '{field1}' },
        { id: 'cv_2', name: 'Var 2', formula: '{cv_1} + {field2}' }
      ]
      
      const graph = buildDependencyGraph(variables)
      const order = graph.getEvaluationOrder()
      
      expect(order).toHaveLength(2)
      expect(order.indexOf('cv_1')).toBeLessThan(order.indexOf('cv_2'))
    })
  })

  describe('getMaxChainDepth', () => {
    it('returns 0 for empty graph', () => {
      const graph = new DependencyGraph()
      expect(graph.getMaxChainDepth()).toBe(0)
    })

    it('returns 1 for single variable without computed dependencies', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{field1}' })
      
      expect(graph.getMaxChainDepth()).toBe(1)
    })

    it('returns correct depth for chain of dependencies', () => {
      const graph = new DependencyGraph()
      graph.addVariable({ id: 'cv_a', name: 'A', formula: '{field1}' })
      graph.addVariable({ id: 'cv_b', name: 'B', formula: '{cv_a}' })
      graph.addVariable({ id: 'cv_c', name: 'C', formula: '{cv_b}' })
      graph.addVariable({ id: 'cv_d', name: 'D', formula: '{cv_c}' })
      
      // Chain: cv_d -> cv_c -> cv_b -> cv_a (depth 4)
      expect(graph.getMaxChainDepth()).toBe(4)
    })

    it('returns max depth when there are multiple chains', () => {
      const graph = new DependencyGraph()
      // Short chain
      graph.addVariable({ id: 'cv_short', name: 'Short', formula: '{field1}' })
      
      // Long chain
      graph.addVariable({ id: 'cv_1', name: '1', formula: '{field2}' })
      graph.addVariable({ id: 'cv_2', name: '2', formula: '{cv_1}' })
      graph.addVariable({ id: 'cv_3', name: '3', formula: '{cv_2}' })
      
      // Max depth is 3 (the long chain)
      expect(graph.getMaxChainDepth()).toBe(3)
    })
  })
})
