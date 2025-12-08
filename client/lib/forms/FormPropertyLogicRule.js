import OpenFilters from "../../data/open_filters.json"
class FormPropertyLogicRule {
  property = null
  logic = null
  isConditionCorrect = true
  isActionCorrect = true
  ACTIONS_VALUES = [
    "show-block",
    "hide-block",
    "make-it-optional",
    "require-answer",
    "enable-block",
    "disable-block",
  ]
  CONDITION_MAPPING = OpenFilters

  constructor(property) {
    this.property = property
    this.logic =
      property.logic !== undefined && property.logic ? property.logic : null
  }

  isValid() {
    if (this.logic && this.logic["conditions"]) {
      this.checkConditions(this.logic["conditions"])
      this.checkActions(
        this.logic && this.logic["actions"] ? this.logic["actions"] : null,
      )
    }

    return this.isConditionCorrect && this.isActionCorrect
  }

  checkConditions(conditions) {
    if (conditions && conditions["operatorIdentifier"]) {
      if (
        conditions["operatorIdentifier"] !== "and" &&
        conditions["operatorIdentifier"] !== "or"
      ) {
        this.isConditionCorrect = false
        return
      }

      if (
        conditions["operatorIdentifier"]["children"] !== undefined ||
        !Array.isArray(conditions["children"])
      ) {
        this.isConditionCorrect = false
        return
      }

      conditions["children"].forEach((childrenCondition) => {
        this.checkConditions(childrenCondition)
      })
    } else if (conditions && conditions["identifier"]) {
      this.checkBaseCondition(conditions)
    }
  }

  checkBaseCondition(condition) {
    if (
      condition["value"] === undefined ||
      condition["value"]["property_meta"] === undefined ||
      condition["value"]["property_meta"]["type"] === undefined ||
      condition["value"]["operator"] === undefined
    ) {
      this.isConditionCorrect = false
      return
    }

    const typeField = condition["value"]["property_meta"]["type"]
    const operator = condition["value"]["operator"]
    
    if (
      this.CONDITION_MAPPING[typeField] === undefined ||
      this.CONDITION_MAPPING[typeField]["comparators"][operator] === undefined
    ) {
      this.isConditionCorrect = false
      return
    }

    // Check if operator needs a value based on comparator definition
    const comparatorDef = this.CONDITION_MAPPING[typeField]["comparators"][operator]
    const needsValue = Object.keys(comparatorDef).length > 0
    
    if (needsValue && condition["value"]["value"] === undefined) {
      this.isConditionCorrect = false
      return
    }

    // Only check value type if comparator expects one
    if (needsValue) {
      const type = comparatorDef["expected_type"]
      const value = condition["value"]["value"]
      
      if (Array.isArray(type)) {
        let foundCorrectType = false
        type.forEach((subtype) => {
          if (this.valueHasCorrectType(subtype, value)) {
            foundCorrectType = true
          }
        })
        if (!foundCorrectType) {
          this.isConditionCorrect = false
        }
      } else {
        if (!this.valueHasCorrectType(type, value)) {
          this.isConditionCorrect = false
        }
      }
    }
  }

  valueHasCorrectType(type, value) {
    if (
      (type === "string" && typeof value !== "string") ||
      (type === "boolean" && typeof value !== "boolean") ||
      (type === "number" && typeof value !== "number") ||
      (type === "object" && !(Array.isArray(value) || typeof value === 'object'))
    ) {
      return false
    }
    return true
  }

  checkActions(conditions) {
    if (Array.isArray(conditions) && conditions.length > 0) {
      conditions.forEach((val) => {
        if (
          this.ACTIONS_VALUES.indexOf(val) === -1 ||
          ([
            "nf-text",
            "nf-code",
            "nf-page-break",
            "nf-divider",
            "nf-image",
            "nf-video"
          ].indexOf(this.property["type"]) > -1 &&
            ["hide-block", "show-block"].indexOf(val) === -1) ||
          (this.property["hidden"] !== undefined &&
            this.property["hidden"] &&
            ["show-block", "require-answer"].indexOf(val) === -1) ||
          (this.property["required"] !== undefined &&
            this.property["required"] &&
            ["make-it-optional", "hide-block", "disable-block"].indexOf(val) ===
              -1) ||
          (this.property["disabled"] !== undefined &&
            this.property["disabled"] &&
            ["enable-block", "require-answer", "make-it-optional"].indexOf(
              val,
            ) === -1)
        ) {
          this.isActionCorrect = false
          return
        }
      })
    } else {
      this.isActionCorrect = false
    }
  }
}

export default FormPropertyLogicRule

