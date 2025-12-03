<template>
  <div class="p-4">
    <div
      v-if="rowNames.length === 0"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th class="p-2" />
            <th
              v-for="col in columns"
              :key="col"
              class="p-2 text-sm text-neutral-700 text-center"
            >
              {{ col }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rowName in rowNames" :key="rowName">
            <th class="p-2 text-sm text-left text-neutral-700 whitespace-nowrap">
              {{ rowName }}
            </th>
            <td
              v-for="col in columns"
              :key="col"
              class="p-2"
            >
              <div
                class="rounded-lg px-4 py-3 text-center text-sm font-medium transition-all"
                :class="getCellClass(getPercentage(rowName, col))"
              >
                {{ formatPercentage(getPercentage(rowName, col)) }}%
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const rows = computed(() => props.field.data?.rows || {})
const rowNames = computed(() => Object.keys(rows.value))

// Extract unique column names from all row distributions
const columns = computed(() => {
  const colSet = new Set()
  Object.values(rows.value).forEach(rowData => {
    (rowData.distribution || []).forEach(item => {
      colSet.add(item.value)
    })
  })
  // Sort columns naturally (handles numeric strings like "1", "2", "3")
  return Array.from(colSet).sort((a, b) => {
    const numA = parseFloat(a)
    const numB = parseFloat(b)
    if (!isNaN(numA) && !isNaN(numB)) return numA - numB
    return String(a).localeCompare(String(b))
  })
})

const getPercentage = (rowName, col) => {
  const rowData = rows.value[rowName]
  if (!rowData?.distribution) return 0
  const item = rowData.distribution.find(d => d.value === col)
  return item?.percentage || 0
}

const formatPercentage = (value) => {
  return Number.isInteger(value) ? value.toFixed(0) : value.toFixed(2)
}

const getCellClass = (percentage) => {
  if (percentage >= 50) {
    return 'bg-neutral-600 text-white'
  } else if (percentage >= 25) {
    return 'bg-neutral-300 text-neutral-800'
  } else if (percentage > 0) {
    return 'bg-neutral-200 text-neutral-700'
  }
  return 'bg-white border border-neutral-200 text-neutral-500'
}
</script>

