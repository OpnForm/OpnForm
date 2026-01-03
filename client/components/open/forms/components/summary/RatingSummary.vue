<template>
  <div class="p-4">
    <!-- Empty state -->
    <div
      v-if="!hasData"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <template v-else>
      <!-- Average with stars -->
      <div class="flex items-center gap-2 mb-4">
        <span class="text-3xl font-bold text-neutral-900">{{ formattedAverage }}</span>
        <div class="flex">
          <UIcon
            v-for="i in maxRating"
            :key="i"
            :name="i <= Math.round(data.average || 0) ? 'i-heroicons-star-solid' : 'i-heroicons-star'"
            class="w-5 h-5 text-amber-400"
          />
        </div>
      </div>

      <!-- Distribution bars -->
      <div class="space-y-1">
        <div
          v-for="rating in ratingRange"
          :key="rating"
          class="flex items-center gap-2"
        >
          <span class="w-4 text-sm text-neutral-500 text-right">{{ rating }}</span>
          <div class="flex-1 bg-neutral-100 rounded-full h-2">
            <div
              class="bg-amber-400 h-2 rounded-full transition-all"
              :style="{ width: getPercentage(rating) + '%' }"
            />
          </div>
          <span class="w-8 text-xs text-neutral-500 text-right">
            {{ distribution[rating] || 0 }}
          </span>
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

