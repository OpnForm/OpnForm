<?php

use App\Service\Formulas\DependencyResolver;
use App\Service\Formulas\FormulaException;

describe('Dependency Resolver', function () {
    describe('addVariable', function () {
        it('adds variables to the graph', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_1', 'name' => 'Var 1', 'formula' => '{field1} + 1']);

            $order = $resolver->getEvaluationOrder();
            expect($order)->toContain('cv_1');
        });

        it('extracts dependencies from formula', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_1', 'name' => 'Var 1', 'formula' => '{field1} + {field2}']);

            $deps = $resolver->getAllDependencies('cv_1');
            expect($deps)->toContain('field1');
            expect($deps)->toContain('field2');
        });
    });

    describe('removeVariable', function () {
        it('removes variables from the graph', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_1', 'name' => 'Var 1', 'formula' => '1']);
            $resolver->addVariable(['id' => 'cv_2', 'name' => 'Var 2', 'formula' => '2']);

            $resolver->removeVariable('cv_1');

            $order = $resolver->getEvaluationOrder();
            expect($order)->not->toContain('cv_1');
            expect($order)->toContain('cv_2');
        });
    });

    describe('cycle detection', function () {
        it('detects direct cycles', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{cv_b}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{cv_a}']);

            $cycles = $resolver->detectCycles();
            expect(count($cycles))->toBeGreaterThan(0);
        });

        it('detects indirect cycles', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{cv_b}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{cv_c}']);
            $resolver->addVariable(['id' => 'cv_c', 'name' => 'C', 'formula' => '{cv_a}']);

            $cycles = $resolver->detectCycles();
            expect(count($cycles))->toBeGreaterThan(0);
        });

        it('returns empty array when no cycles', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{field1}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{cv_a} + {field2}']);

            $cycles = $resolver->detectCycles();
            expect($cycles)->toHaveCount(0);
        });
    });

    describe('evaluation order', function () {
        it('returns topologically sorted order', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_total', 'name' => 'Total', 'formula' => '{cv_subtotal} + {cv_tax}']);
            $resolver->addVariable(['id' => 'cv_subtotal', 'name' => 'Subtotal', 'formula' => '{price} * {qty}']);
            $resolver->addVariable(['id' => 'cv_tax', 'name' => 'Tax', 'formula' => '{cv_subtotal} * 0.1']);

            $order = $resolver->getEvaluationOrder();

            // cv_subtotal should come before cv_tax and cv_total
            $subtotalIdx = array_search('cv_subtotal', $order);
            $taxIdx = array_search('cv_tax', $order);
            $totalIdx = array_search('cv_total', $order);

            expect($subtotalIdx)->toBeLessThan($taxIdx);
            expect($subtotalIdx)->toBeLessThan($totalIdx);
            expect($taxIdx)->toBeLessThan($totalIdx);
        });

        it('throws error when there are cycles', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{cv_b}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{cv_a}']);

            $resolver->getEvaluationOrder();
        })->throws(FormulaException::class);
    });

    describe('getDependents', function () {
        it('returns variables that depend on a field', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{field1}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{field1} + {field2}']);
            $resolver->addVariable(['id' => 'cv_c', 'name' => 'C', 'formula' => '{cv_a}']);

            $dependents = $resolver->getDependents('field1');

            expect($dependents)->toContain('cv_a');
            expect($dependents)->toContain('cv_b');
            expect($dependents)->toContain('cv_c'); // Transitively depends on field1 through cv_a
        });
    });

    describe('wouldCreateCycle', function () {
        it('returns true if adding variable would create cycle', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{cv_b}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{field1}']);

            $wouldCycle = $resolver->wouldCreateCycle([
                'id' => 'cv_b',
                'name' => 'B',
                'formula' => '{cv_a}'
            ]);

            expect($wouldCycle)->toBe(true);
        });

        it('returns false if change is safe', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{field1}']);

            $wouldCycle = $resolver->wouldCreateCycle([
                'id' => 'cv_b',
                'name' => 'B',
                'formula' => '{cv_a}'
            ]);

            expect($wouldCycle)->toBe(false);
        });
    });

    describe('fromVariables', function () {
        it('creates resolver from array of variables', function () {
            $variables = [
                ['id' => 'cv_1', 'name' => 'Var 1', 'formula' => '{field1}'],
                ['id' => 'cv_2', 'name' => 'Var 2', 'formula' => '{cv_1} + {field2}']
            ];

            $resolver = DependencyResolver::fromVariables($variables);
            $order = $resolver->getEvaluationOrder();

            expect($order)->toHaveCount(2);
            expect(array_search('cv_1', $order))->toBeLessThan(array_search('cv_2', $order));
        });
    });

    describe('getMaxChainDepth', function () {
        it('returns 0 for empty graph', function () {
            $resolver = new DependencyResolver();
            expect($resolver->getMaxChainDepth())->toBe(0);
        });

        it('returns 1 for single variable without computed dependencies', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{field1}']);

            expect($resolver->getMaxChainDepth())->toBe(1);
        });

        it('returns correct depth for chain of dependencies', function () {
            $resolver = new DependencyResolver();
            $resolver->addVariable(['id' => 'cv_a', 'name' => 'A', 'formula' => '{field1}']);
            $resolver->addVariable(['id' => 'cv_b', 'name' => 'B', 'formula' => '{cv_a}']);
            $resolver->addVariable(['id' => 'cv_c', 'name' => 'C', 'formula' => '{cv_b}']);
            $resolver->addVariable(['id' => 'cv_d', 'name' => 'D', 'formula' => '{cv_c}']);

            // Chain: cv_d -> cv_c -> cv_b -> cv_a (depth 4)
            expect($resolver->getMaxChainDepth())->toBe(4);
        });

        it('returns max depth when there are multiple chains', function () {
            $resolver = new DependencyResolver();
            // Short chain
            $resolver->addVariable(['id' => 'cv_short', 'name' => 'Short', 'formula' => '{field1}']);

            // Long chain
            $resolver->addVariable(['id' => 'cv_1', 'name' => '1', 'formula' => '{field2}']);
            $resolver->addVariable(['id' => 'cv_2', 'name' => '2', 'formula' => '{cv_1}']);
            $resolver->addVariable(['id' => 'cv_3', 'name' => '3', 'formula' => '{cv_2}']);

            // Max depth is 3 (the long chain)
            expect($resolver->getMaxChainDepth())->toBe(3);
        });
    });
});
