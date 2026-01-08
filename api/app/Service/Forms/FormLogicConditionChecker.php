<?php

namespace App\Service\Forms;

use App\Models\Forms\FormSubmission;

class FormLogicConditionChecker
{
    public function __construct(private ?array $conditions, private ?array $formData)
    {
    }

    public static function conditionsMet(?array $conditions, array $formData): bool
    {
        return (new self($conditions, $formData))->conditionsAreMet($conditions, $formData);
    }

    private function conditionsAreMet(?array $conditions, array $formData): bool
    {
        if (!$conditions) {
            return false;
        }

        // If it's not a group, just a single condition
        if (!isset($conditions['operatorIdentifier'])) {
            return $this->propertyConditionMet($conditions['value'], $formData[$conditions['value']['property_meta']['id']] ?? null);
        }

        if ($conditions['operatorIdentifier'] === 'and') {
            $isvalid = true;
            foreach ($conditions['children'] as $childrenCondition) {
                if (!$this->conditionsMet($childrenCondition, $formData)) {
                    $isvalid = false;
                    break;
                }
            }

            return $isvalid;
        } elseif ($conditions['operatorIdentifier'] === 'or') {
            $isvalid = false;
            foreach ($conditions['children'] as $childrenCondition) {
                if ($this->conditionsMet($childrenCondition, $formData)) {
                    $isvalid = true;
                    break;
                }
            }

            return $isvalid;
        }

        throw new \Exception('Unexcepted operatorIdentifier:' . $conditions['operatorIdentifier']);
    }

    private function propertyConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['property_meta']['type']) {
            case 'text':
            case 'url':
            case 'email':
            case 'phone_number':
                return $this->textConditionMet($propertyCondition, $value);
            case 'number':
            case 'rating':
            case 'scale':
            case 'slider':
                return $this->numberConditionMet($propertyCondition, $value);
            case 'checkbox':
                return $this->checkboxConditionMet($propertyCondition, $value);
            case 'select':
                return $this->selectConditionMet($propertyCondition, $value);
            case 'date':
                return $this->dateConditionMet($propertyCondition, $value);
            case 'multi_select':
                return $this->multiSelectConditionMet($propertyCondition, $value);
            case 'files':
                return $this->filesConditionMet($propertyCondition, $value);
            case 'matrix':
                return $this->matrixConditionMet($propertyCondition, $value);
            case 'payment':
                return $this->paymentConditionMet($propertyCondition, $value);
        }

        return false;
    }

    private function checkEquals($condition, $fieldValue): bool
    {
        if (!isset($condition['value'])) {
            return false;
        }
        // For numeric values, convert to numbers before comparison
        if (
            $this->areValidNumbers($condition, $fieldValue) &&
            is_numeric($condition['value']) &&
            is_numeric($fieldValue)
        ) {
            return (float) $condition['value'] === (float) $fieldValue;
        }

        return $condition['value'] === $fieldValue;
    }

    private function checkContains($condition, $fieldValue): bool
    {
        if (!isset($condition['value'])) {
            return false;
        }
        if (is_array($fieldValue)) {
            return in_array($condition['value'], $fieldValue);
        }
        return \Illuminate\Support\Str::contains($fieldValue, $condition['value']);
    }

    private function checkMatrixContains($condition, $fieldValue): bool
    {
        if (!isset($condition['value'])) {
            return false;
        }

        foreach ($condition['value'] as $key => $value) {
            if (!(array_key_exists($key, $condition['value']) && array_key_exists($key, $fieldValue))) {
                return false;
            }
            if ($condition['value'][$key] == $fieldValue[$key]) {
                return true;
            }
        }
        return false;
    }

    private function checkMatrixEquals($condition, $fieldValue): bool
    {
        if (!isset($condition['value'])) {
            return false;
        }
        foreach ($condition['value'] as $key => $value) {
            if ($condition['value'][$key] !== $fieldValue[$key]) {
                return false;
            }
        }
        return true;
    }

    private function checkListContains($condition, $fieldValue): bool
    {
        if (is_null($fieldValue)) {
            return false;
        }

        if (!is_array($fieldValue)) {
            return $this->checkEquals($condition, $fieldValue);
        }

        if (!isset($condition['value'])) {
            return false;
        }
        if (is_array($condition['value'])) {
            return count(array_intersect($condition['value'], $fieldValue)) === count($condition['value']);
        } else {
            return in_array($condition['value'], $fieldValue);
        }
    }

    private function checkStartsWith($condition, $fieldValue): bool
    {
        if (!isset($condition['value'])) {
            return false;
        }
        return str_starts_with($fieldValue, $condition['value']);
    }

    private function checkEndsWith($condition, $fieldValue): bool
    {
        if (!isset($condition['value'])) {
            return false;
        }
        return str_ends_with($fieldValue, $condition['value']);
    }

    private function checkIsEmpty($condition, $fieldValue): bool
    {
        if (is_array($fieldValue)) {
            return count($fieldValue) === 0;
        }

        return $fieldValue == '' || $fieldValue == null || !$fieldValue;
    }

    /**
     * Helper function to check if values are valid for numeric comparison
     */
    private function areValidNumbers($condition, $fieldValue): bool
    {
        return isset($condition['value']) && $fieldValue !== null && $fieldValue !== '';
    }

    private function checkGreaterThan($condition, $fieldValue): bool
    {
        if (!$this->areValidNumbers($condition, $fieldValue)) {
            return false;
        }
        return (float) $fieldValue > (float) $condition['value'];
    }

    private function checkGreaterThanEqual($condition, $fieldValue): bool
    {
        if (!$this->areValidNumbers($condition, $fieldValue)) {
            return false;
        }
        return (float) $fieldValue >= (float) $condition['value'];
    }

    private function checkLessThan($condition, $fieldValue): bool
    {
        if (!$this->areValidNumbers($condition, $fieldValue)) {
            return false;
        }
        return (float) $fieldValue < (float) $condition['value'];
    }

    private function checkLessThanEqual($condition, $fieldValue): bool
    {
        if (!$this->areValidNumbers($condition, $fieldValue)) {
            return false;
        }
        return (float) $fieldValue <= (float) $condition['value'];
    }

    private function checkBefore($condition, $fieldValue): bool
    {
        return $condition['value'] && $fieldValue && $fieldValue < $condition['value'];
    }

    private function checkAfter($condition, $fieldValue): bool
    {
        return $condition['value'] && $fieldValue && $fieldValue > $condition['value'];
    }

    private function checkOnOrBefore($condition, $fieldValue): bool
    {
        return $condition['value'] && $fieldValue && $fieldValue <= $condition['value'];
    }

    private function checkOnOrAfter($condition, $fieldValue): bool
    {
        return $condition['value'] && $fieldValue && $fieldValue >= $condition['value'];
    }

    private function checkPastWeek($condition, $fieldValue): bool
    {
        if (!$fieldValue) {
            return false;
        }
        $fieldDate = date('Y-m-d', strtotime($fieldValue));

        return $fieldDate <= now()->toDateString() && $fieldDate >= now()->subDays(7)->toDateString();
    }

    private function checkPastMonth($condition, $fieldValue): bool
    {
        if (!$fieldValue) {
            return false;
        }
        $fieldDate = date('Y-m-d', strtotime($fieldValue));

        return $fieldDate <= now()->toDateString() && $fieldDate >= now()->subMonths(1)->toDateString();
    }

    private function checkPastYear($condition, $fieldValue): bool
    {
        if (!$fieldValue) {
            return false;
        }
        $fieldDate = date('Y-m-d', strtotime($fieldValue));

        return $fieldDate <= now()->toDateString() && $fieldDate >= now()->subYears(1)->toDateString();
    }

    private function checkNextWeek($condition, $fieldValue): bool
    {
        if (!$fieldValue) {
            return false;
        }
        $fieldDate = date('Y-m-d', strtotime($fieldValue));

        return $fieldDate >= now()->toDateString() && $fieldDate <= now()->addDays(7)->toDateString();
    }

    private function checkNextMonth($condition, $fieldValue): bool
    {
        if (!$fieldValue) {
            return false;
        }
        $fieldDate = date('Y-m-d', strtotime($fieldValue));

        return $fieldDate >= now()->toDateString() && $fieldDate <= now()->addMonths(1)->toDateString();
    }

    private function checkNextYear($condition, $fieldValue): bool
    {
        if (!$fieldValue) {
            return false;
        }
        $fieldDate = date('Y-m-d', strtotime($fieldValue));

        return $fieldDate >= now()->toDateString() && $fieldDate <= now()->addYears(1)->toDateString();
    }

    private function checkAtLeastXDaysAgo($condition, $fieldValue): bool
    {
        if (!$fieldValue || !$condition['value']) {
            return false;
        }

        // Validate date is valid
        $timestamp = strtotime($fieldValue);
        if ($timestamp === false) {
            return false;
        }
        $fieldDate = date('Y-m-d', $timestamp);

        // Validate days is a valid positive number
        if (!is_numeric($condition['value'])) {
            return false;
        }
        $daysBefore = (int) $condition['value'];
        if ($daysBefore < 0) {
            return false;
        }

        // Create target date by subtracting days from today
        $targetDate = now()->subDays($daysBefore)->toDateString();

        // Return true if fieldDate is on or before the target date (X days before today)
        return $fieldDate <= $targetDate;
    }

    private function checkAtLeastXDaysFromNow($condition, $fieldValue): bool
    {
        if (!$fieldValue || !$condition['value']) {
            return false;
        }

        // Validate date is valid
        $timestamp = strtotime($fieldValue);
        if ($timestamp === false) {
            return false;
        }
        $fieldDate = date('Y-m-d', $timestamp);

        // Validate days is a valid positive number
        if (!is_numeric($condition['value'])) {
            return false;
        }
        $daysAfter = (int) $condition['value'];
        if ($daysAfter < 0) {
            return false;
        }

        // Create target date by adding days to today
        $targetDate = now()->addDays($daysAfter)->toDateString();

        // Return true if fieldDate is on or after the target date (X days after today)
        return $fieldDate >= $targetDate;
    }

    private function checkWithinPastXDays($condition, $fieldValue): bool
    {
        if (!$fieldValue || !$condition['value']) {
            return false;
        }

        // Validate date is valid
        $timestamp = strtotime($fieldValue);
        if ($timestamp === false) {
            return false;
        }
        $fieldDate = date('Y-m-d', $timestamp);

        // Validate days is a valid positive number
        if (!is_numeric($condition['value'])) {
            return false;
        }
        $daysBefore = (int) $condition['value'];
        if ($daysBefore < 0) {
            return false;
        }

        // Create target date by subtracting days from today
        $targetDate = now()->subDays($daysBefore)->toDateString();
        $today = now()->toDateString();

        // Return true if fieldDate is between target date (X days ago) and today (inclusive)
        return $fieldDate >= $targetDate && $fieldDate <= $today;
    }

    private function checkWithinNextXDays($condition, $fieldValue): bool
    {
        if (!$fieldValue || !$condition['value']) {
            return false;
        }

        // Validate date is valid
        $timestamp = strtotime($fieldValue);
        if ($timestamp === false) {
            return false;
        }
        $fieldDate = date('Y-m-d', $timestamp);

        // Validate days is a valid positive number
        if (!is_numeric($condition['value'])) {
            return false;
        }
        $daysAfter = (int) $condition['value'];
        if ($daysAfter < 0) {
            return false;
        }

        // Create target date by adding days to today
        $targetDate = now()->addDays($daysAfter)->toDateString();
        $today = now()->toDateString();

        // Return true if fieldDate is between today and target date (X days from now) (inclusive)
        return $fieldDate >= $today && $fieldDate <= $targetDate;
    }

    private function checkLength($condition, $fieldValue, $operator = '==='): bool
    {
        if (!$fieldValue || strlen($fieldValue) === 0) {
            return false;
        }
        if (!isset($condition['value'])) {
            return false;
        }
        switch ($operator) {
            case '===':
                return strlen($fieldValue) === (int) $condition['value'];
            case '!==':
                return strlen($fieldValue) !== (int) $condition['value'];
            case '>':
                return strlen($fieldValue) > (int) $condition['value'];
            case '>=':
                return strlen($fieldValue) >= (int) $condition['value'];
            case '<':
                return strlen($fieldValue) < (int) $condition['value'];
            case '<=':
                return strlen($fieldValue) <= (int) $condition['value'];
        }

        return false;
    }

    private function checkExistsInSubmissions($condition, $fieldValue): bool
    {
        if (!$fieldValue || !isset($condition['property_meta']['id'])) {
            return false;
        }

        $formId = $this->formData['form']['id'] ?? null;
        if (!$formId) {
            return false;
        }

        $fieldId = $condition['property_meta']['id'];

        // Validate field ID format to prevent SQL injection
        // Field IDs should only contain alphanumeric characters, underscores, and hyphens
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fieldId)) {
            return false;
        }

        $dbConnection = config('database.default');

        // Use lockForUpdate to prevent race conditions when checking for uniqueness
        $query = FormSubmission::where('form_id', $formId)
            ->where('status', '!=', FormSubmission::STATUS_PARTIAL)
            ->lockForUpdate();

        if ($dbConnection === 'mysql') {
            // MySQL: Use fully parameterized JSON path query
            // JSON_EXTRACT with CONCAT for safe field ID handling
            if (is_array($fieldValue)) {
                // For array values (multi_select, matrix)
                $query->whereRaw(
                    "JSON_CONTAINS(JSON_EXTRACT(data, CONCAT('\$.\"', ?, '\"')), ?)",
                    [$fieldId, json_encode($fieldValue)]
                );
            } else {
                // For scalar values
                $query->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(data, CONCAT('\$.\"', ?, '\"'))) = ?",
                    [$fieldId, $fieldValue]
                );
            }
        } else {
            // PostgreSQL: Already uses parameterized queries with -> operator
            if (is_array($fieldValue)) {
                // For array values (multi_select, matrix)
                $query->whereRaw("data->? @> ?::jsonb", [
                    $fieldId,
                    json_encode($fieldValue)
                ]);
            } else {
                // For scalar values
                $query->whereRaw("data->? = ?::jsonb", [$fieldId, json_encode($fieldValue)]);
            }
        }

        return $query->exists();
    }

    private function textConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'equals':
                return $this->checkEquals($propertyCondition, $value);
            case 'does_not_equal':
                return !$this->checkEquals($propertyCondition, $value);
            case 'contains':
                return $this->checkContains($propertyCondition, $value);
            case 'does_not_contain':
                return !$this->checkContains($propertyCondition, $value);
            case 'starts_with':
                return $this->checkStartsWith($propertyCondition, $value);
            case 'ends_with':
                return $this->checkEndsWith($propertyCondition, $value);
            case 'is_empty':
                return $this->checkIsEmpty($propertyCondition, $value);
            case 'is_not_empty':
                return !$this->checkIsEmpty($propertyCondition, $value);
            case 'content_length_equals':
                return $this->checkLength($propertyCondition, $value, '===');
            case 'content_length_does_not_equal':
                return $this->checkLength($propertyCondition, $value, '!==');
            case 'content_length_greater_than':
                return $this->checkLength($propertyCondition, $value, '>');
            case 'content_length_greater_than_or_equal_to':
                return $this->checkLength($propertyCondition, $value, '>=');
            case 'content_length_less_than':
                return $this->checkLength($propertyCondition, $value, '<');
            case 'content_length_less_than_or_equal_to':
                return $this->checkLength($propertyCondition, $value, '<=');
            case 'matches_regex':
                try {
                    return (bool) preg_match('/' . $propertyCondition['value'] . '/', $value);
                } catch (\Exception $e) {
                    return false;
                }
            case 'does_not_match_regex':
                try {
                    return !(bool) preg_match('/' . $propertyCondition['value'] . '/', $value);
                } catch (\Exception $e) {
                    return true;
                }
            case 'exists_in_submissions':
                return $this->checkExistsInSubmissions($propertyCondition, $value);
            case 'does_not_exist_in_submissions':
                return !$this->checkExistsInSubmissions($propertyCondition, $value);
        }

        return false;
    }

    private function numberConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'equals':
                return $this->checkEquals($propertyCondition, $value);
            case 'does_not_equal':
                return !$this->checkEquals($propertyCondition, $value);
            case 'greater_than':
                return $this->checkGreaterThan($propertyCondition, $value);
            case 'less_than':
                return $this->checkLessThan($propertyCondition, $value);
            case 'greater_than_or_equal_to':
                return $this->checkGreaterThanEqual($propertyCondition, $value);
            case 'less_than_or_equal_to':
                return $this->checkLessThanEqual($propertyCondition, $value);
            case 'is_empty':
                return $this->checkIsEmpty($propertyCondition, $value);
            case 'is_not_empty':
                return !$this->checkIsEmpty($propertyCondition, $value);
            case 'content_length_equals':
                return $this->checkLength($propertyCondition, $value, '===');
            case 'content_length_does_not_equal':
                return $this->checkLength($propertyCondition, $value, '!==');
            case 'content_length_greater_than':
                return $this->checkLength($propertyCondition, $value, '>');
            case 'content_length_greater_than_or_equal_to':
                return $this->checkLength($propertyCondition, $value, '>=');
            case 'content_length_less_than':
                return $this->checkLength($propertyCondition, $value, '<');
            case 'content_length_less_than_or_equal_to':
                return $this->checkLength($propertyCondition, $value, '<=');
            case 'exists_in_submissions':
                return $this->checkExistsInSubmissions($propertyCondition, $value);
            case 'does_not_exist_in_submissions':
                return !$this->checkExistsInSubmissions($propertyCondition, $value);
        }

        return false;
    }

    private function checkboxConditionMet(array $propertyCondition, $value): bool
    {
        // Treat null or missing values as false
        if ($value === null || !isset($value)) {
            $value = false;
        }

        switch ($propertyCondition['operator']) {
            case 'is_checked':
                return $value === true;
            case 'is_not_checked':
                return $value === false;
                // Legacy operators
            case 'equals':
                return $value === true;
            case 'does_not_equal':
                return $value === false;
        }

        return false;
    }

    private function selectConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'equals':
                return $this->checkEquals($propertyCondition, $value);
            case 'does_not_equal':
                return !$this->checkEquals($propertyCondition, $value);
            case 'is_empty':
                return $this->checkIsEmpty($propertyCondition, $value);
            case 'is_not_empty':
                return !$this->checkIsEmpty($propertyCondition, $value);
            case 'exists_in_submissions':
                return $this->checkExistsInSubmissions($propertyCondition, $value);
            case 'does_not_exist_in_submissions':
                return !$this->checkExistsInSubmissions($propertyCondition, $value);
        }

        return false;
    }

    private function dateConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'equals':
                return $this->checkEquals($propertyCondition, $value);
            case 'before':
                return $this->checkBefore($propertyCondition, $value);
            case 'after':
                return $this->checkAfter($propertyCondition, $value);
            case 'on_or_before':
                return $this->checkOnOrBefore($propertyCondition, $value);
            case 'on_or_after':
                return $this->checkOnOrAfter($propertyCondition, $value);
            case 'at_least_x_days_ago':
                return $this->checkAtLeastXDaysAgo($propertyCondition, $value);
            case 'at_least_x_days_from_now':
                return $this->checkAtLeastXDaysFromNow($propertyCondition, $value);
            case 'within_past_x_days':
                return $this->checkWithinPastXDays($propertyCondition, $value);
            case 'within_next_x_days':
                return $this->checkWithinNextXDays($propertyCondition, $value);
            case 'is_empty':
                return $this->checkIsEmpty($propertyCondition, $value);
            case 'past_week':
                return $this->checkPastWeek($propertyCondition, $value);
            case 'past_month':
                return $this->checkPastMonth($propertyCondition, $value);
            case 'past_year':
                return $this->checkPastYear($propertyCondition, $value);
            case 'next_week':
                return $this->checkNextWeek($propertyCondition, $value);
            case 'next_month':
                return $this->checkNextMonth($propertyCondition, $value);
            case 'next_year':
                return $this->checkNextYear($propertyCondition, $value);
            case 'exists_in_submissions':
                return $this->checkExistsInSubmissions($propertyCondition, $value);
            case 'does_not_exist_in_submissions':
                return !$this->checkExistsInSubmissions($propertyCondition, $value);
        }

        return false;
    }

    private function multiSelectConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'contains':
                return $this->checkListContains($propertyCondition, $value);
            case 'does_not_contain':
                return !$this->checkListContains($propertyCondition, $value);
            case 'is_empty':
                return $this->checkIsEmpty($propertyCondition, $value);
            case 'is_not_empty':
                return !$this->checkIsEmpty($propertyCondition, $value);
            case 'exists_in_submissions':
                return $this->checkExistsInSubmissions($propertyCondition, $value);
            case 'does_not_exist_in_submissions':
                return !$this->checkExistsInSubmissions($propertyCondition, $value);
        }

        return false;
    }

    private function filesConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'is_empty':
                return $this->checkIsEmpty($propertyCondition, $value);
            case 'is_not_empty':
                return !$this->checkIsEmpty($propertyCondition, $value);
        }

        return false;
    }

    private function matrixConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'equals':
                return $this->checkMatrixEquals($propertyCondition, $value);
            case 'does_not_equal':
                return !$this->checkMatrixEquals($propertyCondition, $value);
            case 'contains':
                return $this->checkMatrixContains($propertyCondition, $value);
            case 'does_not_contain':
                return !$this->checkMatrixContains($propertyCondition, $value);
            case 'exists_in_submissions':
                return $this->checkExistsInSubmissions($propertyCondition, $value);
            case 'does_not_exist_in_submissions':
                return !$this->checkExistsInSubmissions($propertyCondition, $value);
        }

        return false;
    }

    private function paymentConditionMet(array $propertyCondition, $value): bool
    {
        switch ($propertyCondition['operator']) {
            case 'paid':
                return $this->checkPaid($propertyCondition, $value);
            case 'not_paid':
                return !$this->checkPaid($propertyCondition, $value);
        }

        return false;
    }

    private function checkPaid($propertyCondition, $value): bool
    {
        return ($value) ? str_starts_with($value, 'pi_') : false;
    }
}
