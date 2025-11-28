<template>
  <div 
  class="flex flex-col gap-2"
   :class="[
    depth >= 1 ? 'p-2 rounded-lg border border-gray-400/30 bg-gray-100/20' : ''
  ]">
    <!-- Rules and Groups -->
    <div v-if="children.length > 0" class="flex flex-col gap-1">
      <template v-for="(child, index) in children" :key="index">
        <!-- Rule -->
        <div v-if="child.hasOwnProperty('identifier')" class="flex items-center gap-2">
          <div class="flex-1">
            <QueryBuilderRule
              :config="config"
              :query="child"
              :index="index"
              :selected-operator="props.query.operatorIdentifier"
              :operator-options="operatorOptions"
              :where-text="whereText"
              @query-update="updateRule(index, $event)"
              @operator-update="updateOperator"
            >
              <template v-for="(_, slotName) in $slots" #[slotName]="props">
                <slot :name="slotName" v-bind="props" />
              </template>
            </QueryBuilderRule>
          </div>
          
          <UDropdownMenu
            :items="getRuleMenuItems(index)"
            :content="{align: 'start', side: 'bottom'}"
            size="sm"
          >
            <UButton
              icon="i-heroicons-ellipsis-horizontal"
              size="sm"
              color="neutral"
              variant="ghost"
              class="flex-shrink-0"
            />
          </UDropdownMenu>
        </div>

        <!-- Nested Group -->
        <div v-else-if="child.operatorIdentifier" class="flex items-start gap-2">
          <!-- Group prefix - same logic as rules -->
          <div v-if="index === 0" class="flex-shrink-0 text-sm font-medium text-gray-600 w-17 text-right pt-3">
            {{ whereText }}
          </div>
          
          <div v-else-if="index === 1" class="flex-shrink-0 w-17 text-right pt-3">
            <USelectMenu
              v-model="selectedOperator"
              :items="operatorOptions"
              value-key="value"
              class="w-full"
              size="sm"
              variant="outline"
              :search-input="false"
            />
          </div>
          
          <div v-else class="flex-shrink-0 text-sm font-medium text-gray-600 w-17 text-right pt-3">
            {{ operatorOptions.find(op => op.value === selectedOperator)?.label || 'And' }}
          </div>

          <!-- Group content -->
          <div class="flex-1">
            <QueryBuilderGroup
              :config="config"
              :query="child"
              :depth="depth + 1"
              :where-text="whereText"
              @query-update="updateGroup(index, $event)"
            >
              <template v-for="(_, slotName) in $slots" #[slotName]="props">
                <slot :name="slotName" v-bind="props" />
              </template>
            </QueryBuilderGroup>
          </div>
          
          <UDropdownMenu
            :items="getGroupMenuItems(index)"
            :content="{align: 'start', side: 'bottom'}"
            size="sm"
          >
            <UButton
              icon="i-heroicons-ellipsis-horizontal"
              size="sm"
              color="neutral"
              variant="ghost"
              class="flex-shrink-0 mt-3"
            />
          </UDropdownMenu>
        </div>
      </template>
    </div>

    <!-- Add Controls - Always visible -->
    <div class="w-full">
      <UDropdownMenu
        :content="{align: 'start'}"
        :items="addMenuItems"
      >
        <UButton
          label="Add rule"
          icon="i-heroicons-plus"
          size="sm"
          color="neutral"
          variant="ghost"
          class="w-full text-gray-700 justify-start"
          trailing-icon="i-heroicons-chevron-down"
        />
      </UDropdownMenu>
    </div>
  </div>
</template>

<script setup>
import QueryBuilderRule from './QueryBuilderRule.vue'

const props = defineProps({
  config: {
    type: Object,
    required: true
  },
  query: {
    type: Object,
    required: true
  },
  depth: {
    type: Number,
    default: 0
  },
  whereText: {
    type: String,
    default: 'Where'
  }
})

const emit = defineEmits(['query-update'])

const addMenuItems = [
  [
    {
      label: 'Add Rule',
      onClick: () => handleAddRule()
    },
    {
      label: 'Add Rule Group',
      onClick: () => addGroup()
    }
  ]
]

const selectedOperator = computed({
  get: () => props.query.operatorIdentifier,
  set: (operatorIdentifier) => {
    emit('query-update', {
      ...props.query,
      operatorIdentifier
    })
  }
})

const children = computed(() => {
  return props.query.children || []
})

const operatorOptions = computed(() => {
  return props.config.operators.map(op => ({
    label: op.name,
    value: op.identifier
  }))
})

function addRule(ruleIdentifier = null) {
  if (!props.config.rules || props.config.rules.length === 0) {
    console.warn('No rules configured for query builder')
    return
  }

  // Default to first available field if no identifier provided
  const defaultIdentifier = ruleIdentifier || (props.config.rules.length > 0 ? props.config.rules[0].identifier : '')
  const ruleDefinition = props.config.rules.find(r => r.identifier === defaultIdentifier)
  
  const newRule = {
    identifier: defaultIdentifier,
    value: ruleDefinition?.initialValue || null
  }

  const newChildren = [...children.value, newRule]
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function updateRule(index, newRuleData) {
  const newChildren = [...children.value]
  newChildren[index] = newRuleData
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function removeRule(index) {
  const newChildren = [...children.value]
  newChildren.splice(index, 1)
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function addGroup() {
  // Create an initial rule for the new group (same as addRule logic)
  const defaultIdentifier = props.config.rules.length > 0 ? props.config.rules[0].identifier : ''
  const ruleDefinition = props.config.rules.find(r => r.identifier === defaultIdentifier)
  
  const initialRule = {
    identifier: defaultIdentifier,
    value: ruleDefinition?.initialValue || null
  }

  const newGroup = {
    operatorIdentifier: props.config.operators[0]?.identifier || 'and',
    children: [initialRule] // Start with one empty rule
  }

  const newChildren = [...children.value, newGroup]
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function updateGroup(index, newGroupData) {
  const newChildren = [...children.value]
  newChildren[index] = newGroupData
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function handleAddRule() {
  addRule() // Add empty rule that allows field selection
}

function updateOperator(newOperator) {
  emit('query-update', {
    ...props.query,
    operatorIdentifier: newOperator
  })
}

function getRuleMenuItems(index) {
  return [
    [
      {
        label: 'Remove',
        icon: 'i-heroicons-trash',
        color: 'error',
        onClick: () => removeRule(index)
      },
      {
        label: 'Duplicate',
        icon: 'i-heroicons-document-duplicate',
        onClick: () => duplicateRule(index)
      },
      {
        label: 'Turn into group',
        icon: 'i-heroicons-arrow-path',
        onClick: () => turnIntoGroup(index)
      }
    ]
  ]
}

function getGroupMenuItems(index) {
  const group = children.value[index]
  const canTurnIntoRule = group.children && group.children.length === 1
  
  const items = [
    {
      label: 'Remove',
      icon: 'i-heroicons-trash',
      color: 'error',
      onClick: () => removeRule(index)
    },
    {
      label: 'Duplicate',
      icon: 'i-heroicons-document-duplicate',
      onClick: () => duplicateGroup(index)
    }
  ]

  if (canTurnIntoRule) {
    items.push({
      label: 'Turn into rule',
      icon: 'i-heroicons-arrow-path',
      onClick: () => turnIntoRule(index)
    })
  }

  return [items]
}

function duplicateRule(index) {
  const ruleToDuplicate = children.value[index]
  const newChildren = [...children.value]
  newChildren.splice(index + 1, 0, { ...ruleToDuplicate })
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function duplicateGroup(index) {
  const groupToDuplicate = children.value[index]
  const newChildren = [...children.value]
  // Deep clone the group to avoid reference issues
  const duplicatedGroup = JSON.parse(JSON.stringify(groupToDuplicate))
  newChildren.splice(index + 1, 0, duplicatedGroup)
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function turnIntoGroup(index) {
  const rule = children.value[index]
  const newGroup = {
    operatorIdentifier: props.config.operators[0]?.identifier || 'and',
    children: [{ ...rule }]
  }
  
  const newChildren = [...children.value]
  newChildren[index] = newGroup
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}

function turnIntoRule(index) {
  const group = children.value[index]
  const rule = group.children[0] // We know there's exactly one rule
  
  const newChildren = [...children.value]
  newChildren[index] = { ...rule }
  
  emit('query-update', {
    ...props.query,
    children: newChildren
  })
}
</script>