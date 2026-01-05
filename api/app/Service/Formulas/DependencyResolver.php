<?php

namespace App\Service\Formulas;

class DependencyResolver
{
    private array $nodes = [];
    private array $dependents = [];

    public function addVariable(array $variable): void
    {
        $dependencies = Validator::extractFieldReferences($variable['formula'] ?? '');

        $this->nodes[$variable['id']] = [
            'id' => $variable['id'],
            'name' => $variable['name'] ?? '',
            'formula' => $variable['formula'] ?? '',
            'dependencies' => $dependencies,
        ];

        // Update reverse dependency map
        foreach ($dependencies as $depId) {
            if (!isset($this->dependents[$depId])) {
                $this->dependents[$depId] = [];
            }
            $this->dependents[$depId][] = $variable['id'];
        }
    }

    public function removeVariable(string $variableId): void
    {
        $node = $this->nodes[$variableId] ?? null;
        if (!$node) {
            return;
        }

        // Remove from dependents map
        foreach ($node['dependencies'] as $depId) {
            if (isset($this->dependents[$depId])) {
                $this->dependents[$depId] = array_filter(
                    $this->dependents[$depId],
                    fn ($id) => $id !== $variableId
                );
            }
        }

        unset($this->nodes[$variableId]);
        unset($this->dependents[$variableId]);
    }

    public function detectCycles(): array
    {
        $cycles = [];
        $visited = [];
        $recursionStack = [];
        $path = [];

        $dfs = function ($nodeId) use (&$dfs, &$cycles, &$visited, &$recursionStack, &$path) {
            if (isset($recursionStack[$nodeId])) {
                // Found a cycle
                $cycleStart = array_search($nodeId, $path);
                $cycle = array_slice($path, $cycleStart);
                $cycle[] = $nodeId;
                $cycles[] = $cycle;
                return true;
            }

            if (isset($visited[$nodeId])) {
                return false;
            }

            $visited[$nodeId] = true;
            $recursionStack[$nodeId] = true;
            $path[] = $nodeId;

            $node = $this->nodes[$nodeId] ?? null;
            if ($node) {
                foreach ($node['dependencies'] as $depId) {
                    // Only check dependencies that are computed variables
                    if (isset($this->nodes[$depId])) {
                        $dfs($depId);
                    }
                }
            }

            array_pop($path);
            unset($recursionStack[$nodeId]);
            return false;
        };

        foreach (array_keys($this->nodes) as $nodeId) {
            if (!isset($visited[$nodeId])) {
                $dfs($nodeId);
            }
        }

        return $cycles;
    }

    public function getEvaluationOrder(): array
    {
        $cycles = $this->detectCycles();
        if (!empty($cycles)) {
            $cycleStr = implode(' → ', $cycles[0]);
            throw new FormulaException("Circular dependency detected: {$cycleStr}");
        }

        $sorted = [];
        $visited = [];
        $temp = [];

        $visit = function ($nodeId) use (&$visit, &$sorted, &$visited, &$temp) {
            if (isset($visited[$nodeId])) {
                return;
            }
            if (isset($temp[$nodeId])) {
                return; // Already being processed
            }

            $temp[$nodeId] = true;

            $node = $this->nodes[$nodeId] ?? null;
            if ($node) {
                foreach ($node['dependencies'] as $depId) {
                    if (isset($this->nodes[$depId])) {
                        $visit($depId);
                    }
                }
            }

            unset($temp[$nodeId]);
            $visited[$nodeId] = true;
            $sorted[] = $nodeId;
        };

        foreach (array_keys($this->nodes) as $nodeId) {
            $visit($nodeId);
        }

        return $sorted;
    }

    public function getDependents(string $id): array
    {
        $result = [];
        $queue = [$id];
        $seen = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $deps = $this->dependents[$current] ?? [];

            foreach ($deps as $depId) {
                if (!isset($seen[$depId])) {
                    $seen[$depId] = true;
                    $result[] = $depId;
                    $queue[] = $depId;
                }
            }
        }

        return $result;
    }

    public function getAllDependencies(string $variableId): array
    {
        $result = [];
        $queue = [$variableId];
        $visited = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $node = $this->nodes[$current] ?? null;
            if ($node) {
                foreach ($node['dependencies'] as $depId) {
                    $result[] = $depId;
                    if (isset($this->nodes[$depId])) {
                        $queue[] = $depId;
                    }
                }
            }
        }

        return array_unique($result);
    }

    public static function fromVariables(array $variables): self
    {
        $resolver = new self();

        foreach ($variables as $variable) {
            $resolver->addVariable($variable);
        }

        return $resolver;
    }

    public function wouldCreateCycle(array $variable): bool
    {
        // Create a temporary resolver with the new/updated variable
        $tempResolver = new self();

        // Add all existing nodes except the one being updated
        foreach ($this->nodes as $id => $node) {
            if ($id !== $variable['id']) {
                $tempResolver->addVariable($node);
            }
        }

        // Add the new/updated variable
        $tempResolver->addVariable($variable);

        return !empty($tempResolver->detectCycles());
    }
}
