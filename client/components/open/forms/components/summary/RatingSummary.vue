<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon name="i-heroicons-star" class="w-8 h-8 mb-2 opacity-50" />
      <span class="text-sm">No ratings yet</span>
    </div>

    <template v-else>
      <div class="flex flex-col sm:flex-row gap-6">
        <!-- Average Score -->
        <div class="flex flex-col items-center justify-center sm:w-1/3 bg-neutral-50 rounded-xl p-4 border border-neutral-100">
          <span class="text-4xl font-bold text-neutral-900 mb-2">{{ formattedAverage }}</span>
          <div class="flex items-center gap-1 mb-2">
            <UIcon
              v-for="i in maxRating"
              :key="i"
              :name="i <= Math.round(data.average || 0) ? 'i-heroicons-star-solid' : 'i-heroicons-star'"
              class="w-5 h-5 text-amber-400"
            />
          </div>
          <span class="text-xs text-neutral-500 uppercase tracking-wider font-medium">Average Rating</span>
        </div>

        <!-- Distribution bars -->
        <div class="flex-1 space-y-2 py-1">
          <div
            v-for="rating in ratingRange"
            :key="rating"
            class="flex items-center gap-3 group"
          >
            <div class="flex items-center gap-1 w-12 flex-shrink-0 justify-end">
              <span class="text-sm font-medium text-neutral-700">{{ rating }}</span>
              <UIcon name="i-heroicons-star-solid" class="w-3 h-3 text-neutral-400" />
            </div>
            
            <div class="flex-1 bg-neutral-100 rounded-full h-2.5 overflow-hidden">
              <div
                class="bg-amber-400 h-full rounded-full transition-all duration-500 ease-out"
                :style="{ width: getPercentage(rating) + '%' }"
              />
            </div>
            
            <div class="w-16 flex items-center justify-between text-xs text-neutral-500">
              <span class="font-medium text-neutral-900">{{ distribution[rating] || 0 }}</span>
              <span>{{ getPercentage(rating) }}%</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const data = computed(() => props.field.data || {})
const hasData = computed(() => (data.value.count || 0) > 0)
const maxRating = computed(() => data.value.max_rating || 5)
const distribution = computed(() => data.value.distribution || {})

const formattedAverage = computed(() => {
  const avg = data.value.average
  if (avg === null || avg === undefined) return '-'
  return avg.toFixed(1)
})

// Create array from maxRating down to 1 for display
const ratingRange = computed(() => {
  const range = []
  for (let i = maxRating.value; i >= 1; i--) {
    range.push(i)
  }
  return range
})

const getPercentage = (rating) => {
  const count = distribution.value[rating] || 0
  const total = data.value.count || 0
  if (total === 0) return 0
  return Math.round((count / total) * 100)
}
</script>

