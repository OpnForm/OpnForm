import { default as _isEqual } from "lodash/isEqual"

export function conditionsMet(conditions, formData) {
  if (conditions === undefined || conditions === null) {
    return false
  }

  // If it's not a group, just a single condition
  if (conditions.operatorIdentifier === undefined) {
    return propertyConditionMet(
      conditions.value,
      conditions.value ? formData[conditions.value.property_meta.id] : null,
    )
  }

  if (conditions.operatorIdentifier === "and") {
    let isvalid = true
    conditions.children.forEach((childrenCondition) => {
      if (!conditionsMet(childrenCondition, formData)) {
        isvalid = false
      }
    })
    return isvalid
  } else if (conditions.operatorIdentifier === "or") {
    let isvalid = false
    conditions.children.forEach((childrenCondition) => {
      if (conditionsMet(childrenCondition, formData)) {
        isvalid = true
      }
    })
    return isvalid
  }

  throw new Error(
    "Unexcepted operatorIdentifier:" + conditions.operatorIdentifier,
  )
}

function propertyConditionMet(propertyCondition, value) {
  if (!propertyCondition) {
    return false
  }
  switch (propertyCondition.property_meta.type) {
    case "text":
    case "url":
    case "email":
    case "phone_number":
      return textConditionMet(propertyCondition, value)
    case "number":
    case "rating":
    case "scale":
    case "slider":
      return numberConditionMet(propertyCondition, value)
    case "checkbox":
      return checkboxConditionMet(propertyCondition, value)
    case "select":
      return selectConditionMet(propertyCondition, value)
    case "date":
      return dateConditionMet(propertyCondition, value)
    case "multi_select":
      return multiSelectConditionMet(propertyCondition, value)
    case "files":
      return filesConditionMet(propertyCondition, value)
    case "matrix":
      return matrixConditionMet(propertyCondition, value)
    case "payment":
      return paymentConditionMet(propertyCondition, value)
  }
  return false
}

// Helper function to safely parse numeric values
function safeParseFloat(value) {
  if (value === undefined || value === null) return null
  const parsed = parseFloat(value)
  return isNaN(parsed) ? null : parsed
}

// Helper function to check if values are valid for numeric comparison
function areValidNumbers(condition, fieldValue) {
  const conditionValue = safeParseFloat(condition.value)
  const parsedFieldValue = safeParseFloat(fieldValue)
  return conditionValue !== null && parsedFieldValue !== null
}

function checkEquals(condition, fieldValue) {
  const conditionValue = condition.value
  // For numeric values, convert to numbers before comparison
  if (areValidNumbers(condition, fieldValue) && typeof conditionValue === 'number' && typeof fieldValue === 'number') {
    return parseFloat(conditionValue) === parseFloat(fieldValue)
  }
  return conditionValue === fieldValue
}

function checkObjectEquals(condition, fieldValue) {
  return _isEqual(condition.value, fieldValue)
}

function checkMatrixContains(condition, fieldValue)
{
  if (typeof fieldValue === "undefined" || typeof fieldValue !== "object") {
    return false
  }
  const conditionValue = condition.value
  for (const key in conditionValue) {
    if(conditionValue[key] == fieldValue[key]){
      return true
    }
  }
  return false
}

function checkContains(condition, fieldValue) {
  return fieldValue ? fieldValue.includes(condition.value) : false
}

function checkListContains(condition, fieldValue) {
  if (!fieldValue) return false

  if (Array.isArray(condition.value)) {
    return condition.value.every((r) => fieldValue.includes(r))
  } else {
    return fieldValue.includes(condition.value)
  }
}

function checkStartsWith(condition, fieldValue) {
  return fieldValue?.startsWith(condition.value)
}

function checkendsWith(condition, fieldValue) {
  return fieldValue?.endsWith(condition.value)
}

function checkIsEmpty(condition, fieldValue) {
  return !fieldValue || fieldValue.length === 0
}

function checkGreaterThan(condition, fieldValue) {
  if (!areValidNumbers(condition, fieldValue)) return false
  return parseFloat(fieldValue) > parseFloat(condition.value)
}

function checkGreaterThanEqual(condition, fieldValue) {
  if (!areValidNumbers(condition, fieldValue)) return false
  return parseFloat(fieldValue) >= parseFloat(condition.value)
}

function checkLessThan(condition, fieldValue) {
  if (!areValidNumbers(condition, fieldValue)) return false
  return parseFloat(fieldValue) < parseFloat(condition.value)
}

function checkLessThanEqual(condition, fieldValue) {
  if (!areValidNumbers(condition, fieldValue)) return false
  return parseFloat(fieldValue) <= parseFloat(condition.value)
}

function checkBefore(condition, fieldValue) {
  return condition.value && fieldValue && fieldValue < condition.value
}

function checkAfter(condition, fieldValue) {
  return condition.value && fieldValue && fieldValue > condition.value
}

function checkOnOrBefore(condition, fieldValue) {
  return condition.value && fieldValue && fieldValue <= condition.value
}

function checkOnOrAfter(condition, fieldValue) {
  return condition.value && fieldValue && fieldValue >= condition.value
}

function checkPastWeek(condition, fieldValue) {
  if (!fieldValue) return false
  const fieldDate = new Date(fieldValue)
  const today = new Date()
  return (
    fieldDate <= today &&
    fieldDate >=
    new Date(today.getFullYear(), today.getMonth(), today.getDate() - 7)
  )
}

function checkPastMonth(condition, fieldValue) {
  if (!fieldValue) return false
  const fieldDate = new Date(fieldValue)
  const today = new Date()
  return (
    fieldDate <= today &&
    fieldDate >=
    new Date(today.getFullYear(), today.getMonth() - 1, today.getDate())
  )
}

function checkPastYear(condition, fieldValue) {
  if (!fieldValue) return false
  const fieldDate = new Date(fieldValue)
  const today = new Date()
  return (
    fieldDate <= today &&
    fieldDate >=
    new Date(today.getFullYear() - 1, today.getMonth(), today.getDate())
  )
}

function checkNextWeek(condition, fieldValue) {
  if (!fieldValue) return false
  const fieldDate = new Date(fieldValue)
  const today = new Date()
  return (
    fieldDate >= today &&
    fieldDate <=
    new Date(today.getFullYear(), today.getMonth(), today.getDate() + 7)
  )
}

function checkNextMonth(condition, fieldValue) {
  if (!fieldValue) return false
  const fieldDate = new Date(fieldValue)
  const today = new Date()
  return (
    fieldDate >= today &&
    fieldDate <=
    new Date(today.getFullYear(), today.getMonth() + 1, today.getDate())
  )
}

function checkNextYear(condition, fieldValue) {
  if (!fieldValue) return false
  const fieldDate = new Date(fieldValue)
  const today = new Date()
  return (
    fieldDate >= today &&
    fieldDate <=
    new Date(today.getFullYear() + 1, today.getMonth(), today.getDate())
  )
}

function checkAtLeastXDaysAgo(condition, fieldValue) {
  if (!fieldValue || !condition.value) return false
  const fieldDate = new Date(fieldValue)
  if (Number.isNaN(fieldDate.getTime())) return false
  const today = new Date()
  const daysBefore = parseInt(condition.value, 10)
  if (isNaN(daysBefore) || daysBefore < 0) return false
  // Create target date by setting the date properly to avoid timezone issues
  const targetDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - daysBefore)
  // Return true if fieldDate is on or before the target date (X days before today)
  return fieldDate <= targetDate
}

function checkAtLeastXDaysFromNow(condition, fieldValue) {
  if (!fieldValue || !condition.value) return false
  const fieldDate = new Date(fieldValue)
  if (Number.isNaN(fieldDate.getTime())) return false
  const today = new Date()
  const daysAfter = parseInt(condition.value, 10)
  if (isNaN(daysAfter) || daysAfter < 0) return false
  // Create target date by setting the date properly to avoid timezone issues
  const targetDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + daysAfter)
  // Return true if fieldDate is on or after the target date (X days after today)
  return fieldDate >= targetDate
}

function checkWithinPastXDays(condition, fieldValue) {
  if (!fieldValue || !condition.value) return false
  const fieldDate = new Date(fieldValue)
  if (Number.isNaN(fieldDate.getTime())) return false
  const today = new Date()
  const daysBefore = parseInt(condition.value, 10)
  if (isNaN(daysBefore) || daysBefore < 0) return false
  // Create target date by setting the date properly to avoid timezone issues
  const targetDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - daysBefore)
  const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  // Return true if fieldDate is between target date (X days ago) and today (inclusive)
  return fieldDate >= targetDate && fieldDate <= todayDate
}

function checkWithinNextXDays(condition, fieldValue) {
  if (!fieldValue || !condition.value) return false
  const fieldDate = new Date(fieldValue)
  if (Number.isNaN(fieldDate.getTime())) return false
  const today = new Date()
  const daysAfter = parseInt(condition.value, 10)
  if (isNaN(daysAfter) || daysAfter < 0) return false
  // Create target date by setting the date properly to avoid timezone issues
  const targetDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + daysAfter)
  const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  // Return true if fieldDate is between today and target date (X days from now) (inclusive)
  return fieldDate >= todayDate && fieldDate <= targetDate
}

function checkLength(condition, fieldValue, operator = "===") {
  if (!fieldValue || fieldValue.length === 0) return false
  switch (operator) {
    case "===":
      return fieldValue.length === parseInt(condition.value)
    case "!==":
      return fieldValue.length !== parseInt(condition.value)
    case ">":
      return fieldValue.length > parseInt(condition.value)
    case ">=":
      return fieldValue.length >= parseInt(condition.value)
    case "<":
      return fieldValue.length < parseInt(condition.value)
    case "<=":
      return fieldValue.length <= parseInt(condition.value)
  }
  return false
}

function textConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "equals":
      return checkEquals(propertyCondition, value)
    case "does_not_equal":
      return !checkEquals(propertyCondition, value)
    case "contains":
      return checkContains(propertyCondition, value)
    case "does_not_contain":
      return !checkContains(propertyCondition, value)
    case "starts_with":
      return checkStartsWith(propertyCondition, value)
    case "ends_with":
      return checkendsWith(propertyCondition, value)
    case "is_empty":
      return checkIsEmpty(propertyCondition, value)
    case "is_not_empty":
      return !checkIsEmpty(propertyCondition, value)
    case "content_length_equals":
      return checkLength(propertyCondition, value, "===")
    case "content_length_does_not_equal":
      return checkLength(propertyCondition, value, "!==")
    case "content_length_greater_than":
      return checkLength(propertyCondition, value, ">")
    case "content_length_greater_than_or_equal_to":
      return checkLength(propertyCondition, value, ">=")
    case "content_length_less_than":
      return checkLength(propertyCondition, value, "<")
    case "content_length_less_than_or_equal_to":
      return checkLength(propertyCondition, value, "<=")
    case 'matches_regex':
      try {
        const regex = new RegExp(propertyCondition.value)
        return regex.test(value)
      } catch {
        return false
      }
    case 'does_not_match_regex':
      try {
        const regex = new RegExp(propertyCondition.value)
        return !regex.test(value)
      } catch {
        return true
      }
  }
  return false
}

function numberConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "equals":
      return checkEquals(propertyCondition, value)
    case "does_not_equal":
      return !checkEquals(propertyCondition, value)
    case "greater_than":
      return checkGreaterThan(propertyCondition, value)
    case "less_than":
      return checkLessThan(propertyCondition, value)
    case "greater_than_or_equal_to":
      return checkGreaterThanEqual(propertyCondition, value)
    case "less_than_or_equal_to":
      return checkLessThanEqual(propertyCondition, value)
    case "is_empty":
      return checkIsEmpty(propertyCondition, value)
    case "is_not_empty":
      return !checkIsEmpty(propertyCondition, value)
    case "content_length_equals":
      return checkLength(propertyCondition, value, "===")
    case "content_length_does_not_equal":
      return checkLength(propertyCondition, value, "!==")
    case "content_length_greater_than":
      return checkLength(propertyCondition, value, ">")
    case "content_length_greater_than_or_equal_to":
      return checkLength(propertyCondition, value, ">=")
    case "content_length_less_than":
      return checkLength(propertyCondition, value, "<")
    case "content_length_less_than_or_equal_to":
      return checkLength(propertyCondition, value, "<=")
  }
  return false
}

function checkboxConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "is_checked":
      return value === true
    case "is_not_checked":
      return value === false
    // Legacy operators
    case "equals":
      return value === true
    case "does_not_equal":
      return value === false
  }
  return false
}

function selectConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "equals":
      return checkEquals(propertyCondition, value)
    case "does_not_equal":
      return !checkEquals(propertyCondition, value)
    case "is_empty":
      return checkIsEmpty(propertyCondition, value)
    case "is_not_empty":
      return !checkIsEmpty(propertyCondition, value)
  }
  return false
}

function dateConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "equals":
      return checkEquals(propertyCondition, value)
    case "before":
      return checkBefore(propertyCondition, value)
    case "after":
      return checkAfter(propertyCondition, value)
    case "on_or_before":
      return checkOnOrBefore(propertyCondition, value)
    case "on_or_after":
      return checkOnOrAfter(propertyCondition, value)
    case 'at_least_x_days_ago':
      return checkAtLeastXDaysAgo(propertyCondition, value)
    case 'at_least_x_days_from_now':
      return checkAtLeastXDaysFromNow(propertyCondition, value)
    case 'within_past_x_days':
      return checkWithinPastXDays(propertyCondition, value)
    case 'within_next_x_days':
      return checkWithinNextXDays(propertyCondition, value)
    case "is_empty":
      return checkIsEmpty(propertyCondition, value)
    case "past_week":
      return checkPastWeek(propertyCondition, value)
    case "past_month":
      return checkPastMonth(propertyCondition, value)
    case "past_year":
      return checkPastYear(propertyCondition, value)
    case "next_week":
      return checkNextWeek(propertyCondition, value)
    case "next_month":
      return checkNextMonth(propertyCondition, value)
    case "next_year":
      return checkNextYear(propertyCondition, value)
  }
  return false
}

function multiSelectConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "contains":
      return checkListContains(propertyCondition, value)
    case "does_not_contain":
      return !checkListContains(propertyCondition, value)
    case "is_empty":
      return checkIsEmpty(propertyCondition, value)
    case "is_not_empty":
      return !checkIsEmpty(propertyCondition, value)
  }
  return false
}

function filesConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "is_empty":
      return checkIsEmpty(propertyCondition, value)
    case "is_not_empty":
      return !checkIsEmpty(propertyCondition, value)
  }
  return false
}

function matrixConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "equals":
      return checkObjectEquals(propertyCondition, value)
    case "does_not_equal":
      return !checkObjectEquals(propertyCondition, value)
    case "contains":
     return checkMatrixContains(propertyCondition, value)
    case "does_not_contain":
     return !checkMatrixContains(propertyCondition, value)
  }
  return false
}

function paymentConditionMet(propertyCondition, value) {
  switch (propertyCondition.operator) {
    case "paid":
      return checkPaid(propertyCondition, value)
    case "not_paid":
      return !checkPaid(propertyCondition, value)
  }
  return false
}

function checkPaid(propertyCondition, value) {
  return (value) ? value.startsWith('pi_') : false
}
