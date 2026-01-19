<template>
  <div class="p-4">
    <div
      v-if="rowNames.length === 0"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-table-cells" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No matrix data collected yet</span>
    </div>

    <div v-else class="overflow-x-auto custom-scrollbar">
      <table class="w-full border-separate border-spacing-0">
        <thead>
          <tr>
            <th class="sticky left-0 bg-white z-10 p-3 border-b border-r border-neutral-100 w-1/4 min-w-[150px]">
              <span class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Rows / Columns</span>
            </th>
            <th
              v-for="col in columns"
              :key="col"
              class="p-3 text-sm font-semibold text-neutral-700 text-center bg-neutral-50 border-b border-neutral-100 min-w-[100px]"
            >
              {{ col }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(rowName, rowIndex) in rowNames" :key="rowName" class="group">
            <th 
              class="sticky left-0 z-10 p-3 text-sm font-medium text-left text-neutral-700 border-r border-b border-neutral-100 bg-neutral-50/50 group-hover:bg-neutral-50 transition-colors"
            >
              {{ rowName }}
            </th>
            <td
              v-for="col in columns"
              :key="col"
              class="p-2 border-b border-neutral-50"
            >
              <div
                class="rounded-lg px-3 py-2 text-center text-sm transition-all flex flex-col items-center justify-center min-h-[50px]"
                :class="getCellClass(getPercentage(rowName, col))"
              >
                <span class="font-bold">{{ formatPercentage(getPercentage(rowName, col)) }}%</span>
                <span 
                  class="text-[10px] opacity-75 mt-0.5"
                  v-if="getPercentage(rowName, col) > 0"
                >
                  {{ getCount(rowName, col) }}
                </span>
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

const getRowData = (rowName, col) => {
  const rowData = rows.value[rowName]
  if (!rowData?.distribution) return null
  return rowData.distribution.find(d => d.value === col)
}

const getPercentage = (rowName, col) => {
  return getRowData(rowName, col)?.percentage || 0
}

const getCount = (rowName, col) => {
  return getRowData(rowName, col)?.count || 0
}

const formatPercentage = (value) => {
  return Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1)
}

const getCellClass = (percentage) => {
  if (percentage >= 60) {
    return 'bg-neutral-900 text-white shadow-sm'
  } else if (percentage >= 40) {
    return 'bg-neutral-700 text-white shadow-sm'
  } else if (percentage >= 20) {
    return 'bg-neutral-400 text-white'
  } else if (percentage > 0) {
    return 'bg-neutral-100 text-neutral-700'
  }
  return 'bg-transparent text-neutral-300'
}
</script>

