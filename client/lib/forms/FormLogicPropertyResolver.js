import { conditionsMet } from "./FormLogicConditionChecker"
class FormLogicPropertyResolver {
  conditionsMet = conditionsMet
  property = null
  formData = null
  logic = false

  constructor(property, formData, properties = []) {
    this.properties = properties
    this.property = property
    this.formData = formData
    this.logic = property.logic !== undefined ? property.logic : false
  }

  isHidden() {
    if (!this.logic) {
      return this.property.hidden
    }

    const conditionsMet = this.conditionsMet(
      this.logic.conditions,
      this.formData,
      this.properties,
    )
    if (
      conditionsMet &&
      this.property.hidden &&
      this.logic.actions.length > 0 &&
      this.logic.actions.includes("show-block")
    ) {
      return false
    } else if (
      conditionsMet &&
      !this.property.hidden &&
      this.logic.actions.length > 0 &&
      this.logic.actions.includes("hide-block")
    ) {
      return true
    } else {
      return this.property.hidden
    }
  }

  isRequired() {
    if (!this.logic) {
      return this.property.required
    }

    const conditionsMet = this.conditionsMet(
      this.logic.conditions,
      this.formData,
      this.properties,
    )
    if (
      conditionsMet &&
      this.property.required &&
      this.logic.actions.length > 0 &&
      this.logic.actions.includes("make-it-optional")
    ) {
      return false
    } else if (
      conditionsMet &&
      !this.property.required &&
      this.logic.actions.length > 0 &&
      this.logic.actions.includes("require-answer")
    ) {
      return true
    } else {
      return this.property.required
    }
  }

  isDisabled() {
    if (!this.logic) {
      return this.property.disabled
    }

    const conditionsMet = this.conditionsMet(
      this.logic.conditions,
      this.formData,
      this.properties,
    )
    if (
      conditionsMet &&
      this.property.disabled &&
      this.logic.actions.length > 0 &&
      this.logic.actions.includes("enable-block")
    ) {
      return false
    } else if (
      conditionsMet &&
      !this.property.disabled &&
      this.logic.actions.length > 0 &&
      this.logic.actions.includes("disable-block")
    ) {
      return true
    } else {
      return this.property.disabled
    }
  }
}

export default FormLogicPropertyResolver
