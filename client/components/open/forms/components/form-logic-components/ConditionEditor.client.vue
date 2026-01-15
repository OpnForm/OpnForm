<template>
  <ErrorBoundary
    ref="error_boundary"
    @on-error="handleError"
  >
    <QueryBuilder
      v-model="query"
      :config="config"
      where-text="When"
      v-bind="$attrs"
      @update:model-value="onChange"
    >
      <template #rule="ruleCtrl">
        <component
          :is="ruleCtrl.ruleComponent"
          :model-value="ruleCtrl.ruleData"
          @update:model-value="ruleCtrl.updateRuleData"
        />
      </template>
    </QueryBuilder>
  </ErrorBoundary> 
</template>

<script>
import ErrorBoundary from '~/components/app/ErrorBoundary.vue'
import { defineComponent } from "vue"
import QueryBuilder from "~/components/tools/query-builder/QueryBuilder.vue"
import ColumnCondition from "./ColumnCondition.vue"

export default {
  components: {
    QueryBuilder,
    ColumnCondition,
    ErrorBoundary,
  },

  props: {
    form: { type: Object, required: true },
    modelValue: { type: Object, required: false },
    customValidation: { type: Boolean, default: false },
  },
  emits:  ['update:modelValue'],

  data() {
    return {
      query: this.modelValue,
    }
  },

  computed: {
    rules() {
      const workspaceId = this.form.workspace_id
      const formSlug = this.form.slug
      const customValidation = this.customValidation
      
      // Form field rules
      const fieldRules = this.form.properties
        .filter((property) => {
          return property.type && typeof property.type === 'string' && !property.type.startsWith("nf-")
        })
        .map((property) => {
          return {
            identifier: property.id,
            name: property.name,
            component: (function () {
              return defineComponent({
                extends: ColumnCondition,
                props: {
                  customValidation: {
                    type: Boolean,
                    default: customValidation
                  }
                },
                computed: {
                  property() {
                    return property
                  },
                  viewContext() {
                    return {
                      form_slug: formSlug,
                      workspace_id: workspaceId,
                    }
                  },
                },
              })
            })(),
          }
        })
      
      // Computed variable rules
      const computedVariables = this.form.computed_variables || []
      const variableRules = computedVariables.map((variable) => {
        // Create a pseudo-property for computed variables
        // We'll treat them as text by default, but number conditions will also work
        const pseudoProperty = {
          id: variable.id,
          name: variable.name,
          type: 'computed', // Special type for computed variables
          result_type: variable.result_type || 'auto'
        }
        
        return {
          identifier: variable.id,
          name: `📊 ${variable.name}`,
          component: (function () {
            return defineComponent({
              extends: ColumnCondition,
              props: {
                customValidation: {
                  type: Boolean,
                  default: customValidation
                }
              },
              computed: {
                property() {
                  return pseudoProperty
                },
                viewContext() {
                  return {
                    form_slug: formSlug,
                    workspace_id: workspaceId,
                  }
                },
              },
            })
          })(),
        }
      })
      
      return [...fieldRules, ...variableRules]
    },

    config() {
      return {
        operators: [
          {
            name: "And",
            identifier: "and",
          },
          {
            name: "Or",
            identifier: "or",
          },
        ],
        rules: this.rules,
        colors: [
          "#ef4444",
          "#22c55e",
          "#f97316",
          "#0ea5e9",
          "#8b5cf6",
          "#ec4899",
        ],
      }
    },
  },

  watch: {
    modelValue() {
      this.query = this.modelValue
    },
  },

  methods: {
    onChange() {
      this.$emit("update:modelValue", this.query)
    },
    // If there was some changes to the structure, causing an issue, we clear the condition
    handleError (error) {
      this.$nextTick(() => {
        console.error('Error with condition - clearing previous value', error)
        this.query = null
        this.onChange()
        this.$refs['error_boundary'].clearError()
      })
    }
  }
}
</script>

