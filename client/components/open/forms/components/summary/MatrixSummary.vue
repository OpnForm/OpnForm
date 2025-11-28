<template>
  <div>
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <!-- Matrix rows -->
    <div v-else class="space-y-4">
      <div
        v-for="(rowData, rowName) in rows"
        :key="rowName"
        class="border-b border-neutral-100 pb-3 last:border-0"
      >
        <div class="text-sm font-medium text-neutral-700 mb-2">{{ rowName }}</div>
        <div class="space-y-1">
          <div
            v-for="item in rowData.distribution"
            :key="item.value"
            class="flex items-center gap-2"
          >
            <div class="w-20 text-xs text-neutral-500 truncate flex-shrink-0" :title="item.value">
              {{ item.value }}
            </div>
            <div class="flex-1 bg-neutral-100 rounded-full h-4 overflow-hidden">
              <div
                class="h-full bg-neutral-300 rounded-full transition-all duration-300"
                :style="{ width: item.percentage + '%' }"
              />
            </div>
            <div class="w-10 text-right text-xs text-neutral-500 flex-shrink-0">
              {{ item.percentage }}%
            </div>
            <div class="w-8 text-right text-xs text-neutral-400 flex-shrink-0">
              {{ item.count }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const rows = computed(() => props.field.data?.rows || {})
const hasData = computed(() => Object.keys(rows.value).length > 0)
</script>

