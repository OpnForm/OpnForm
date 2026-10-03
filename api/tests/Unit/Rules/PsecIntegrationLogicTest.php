<?php

use App\Rules\IntegrationLogicRule;
use Tests\TestCase;

uses(TestCase::class);

it('rejects unknown operators and malformed group nodes before traversal', function () {
    $rule = new IntegrationLogicRule();
    foreach ([['operatorIdentifier' => 'and'], ['operatorIdentifier' => 'and', 'children' => []], ['operatorIdentifier' => 'and', 'children' => ['invalid']], ['identifier' => 'x', 'value' => ['property_meta' => ['type' => 'not-a-type'], 'operator' => 'not-an-operator']]] as $node) {
        expect($rule->passes('logic', $node))->toBeFalse();
    }
});
