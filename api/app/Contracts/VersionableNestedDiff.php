<?php

namespace App\Contracts;

interface VersionableNestedDiff
{
    /**
     * List of attribute names that should receive nested (deep) diffing.
     *
     * @return array<int, string>
     */
    public function getVersionNestedDiffFields(): array;
}
