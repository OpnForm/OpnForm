<?php

use App\Rules\PropertyValidators\LogicPropertyValidator;
use Tests\TestCase;

uses(TestCase::class);

describe('LogicPropertyValidator action validation', function () {
    it('passes with empty logic', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'title',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => null,
                'actions' => [],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('fails when hidden block has hide-block action', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'title',
            'hidden' => true,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic');
        expect($errors['logic'])->toContain('The logic actions for Name are not valid.');
    });

    it('fails when nf-text block has require-answer action', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'text',
            'name' => 'Custom Test',
            'type' => 'nf-text',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'actions' => ['require-answer'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic');
        expect($errors['logic'])->toContain('The logic actions for Custom Test are not valid.');
    });
});

describe('LogicPropertyValidator condition validation', function () {
    it('passes with valid conditions', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('fails when condition value is missing', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'starts_with',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic');
        expect($errors['logic'])->toBe('The logic conditions for Name are not complete. Error detail(s): missing condition value');
    });

    it('fails when operator is missing', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => null,
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'starts_with',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic');
        expect($errors['logic'])->toBe('The logic conditions for Name are not complete. Error detail(s): missing operator');
    });
});

describe('LogicPropertyValidator operators without values', function () {
    it('passes for checkbox is_checked without value', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'is_checked',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ]
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('passes for checkbox is_checked with value for backward compatibility', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'is_checked',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ],
                                'value' => true
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('passes for checkbox is_not_checked without value', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'is_not_checked',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ]
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('fails for invalid operator', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'invalid_operator',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ]
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic');
        expect($errors['logic'])->toBe('The logic conditions for Checkbox Field are not complete. Error detail(s): configuration not found for condition operator');
    });
});
