<template>
    <div class="w-full">
      <VTransition name="fade">
      <OpenTable
        v-if="form"
        ref="table"
        class="border-b"
        :data="submissions"
        :loading="isLoading || isFetching"
        :form="form"
        :pagination="pagination"
        @search="handleSearch"
        @filter="handleFilter"
        @page-change="handlePageChange"
        @refresh="handleRefresh"
      />

      <!-- Submissions Table Skeleton -->
      <div v-else class="overflow-hidden mt-4">
        <!-- Table Header Skeleton -->
        <div class="bg-neutral-50 border-y border-neutral-200 px-4 py-3">
          <div class="flex gap-4">
            <USkeleton class="h-4 w-32" />
            <USkeleton class="h-4 w-24" />
            <USkeleton class="h-4 w-28" />
            <USkeleton class="h-4 w-20" />
          </div>
        </div>
        
        <!-- Table Rows Skeleton -->
        <div v-for="i in ['opacity-100', 'opacity-90', 'opacity-80', 'opacity-70', 'opacity-60', 'opacity-50', 'opacity-40', 'opacity-30', 'opacity-20', 'opacity-10']" :key="i" class="border-b border-neutral-200 px-4 py-3" :class="i">
          <div class="flex gap-4 items-center">
            <USkeleton class="h-4 w-32" />
            <USkeleton class="h-4 w-24" />
            <USkeleton class="h-4 w-28" />
            <USkeleton class="h-4 w-20" />
          </div>
        </div>
      </div>
      </VTransition>
    </div>
</template>

<script setup>
import OpenTable from '~/components/open/tables/OpenTable.vue'
import { useFormSubmissions } from '~/composables/query/forms/useFormSubmissions'

const props = defineProps({
  form: { type: Object, required: true },
})

const table = ref(null)

// Replace all the recordStore logic with this:
const { paginatedList } = useFormSubmissions()
const {
  submissions,
  pagination,
  isLoading,
  isFetching,
  setSearch,
  setStatus,
  setPage,
  refetch
} = paginatedList(computed(() => props.form?.id))

// Replace existing event handlers:
const handleSearch = (searchTerm) => {
  setSearch(searchTerm)
}

const handleFilter = (filters) => {
  if (filters.status) {
    setStatus(filters.status)
  }
}

const handlePageChange = (page) => {
  setPage(page)
}

const handleRefresh = () => {
  // Refetch the data with current parameters (page, search, status)
  refetch()
}


</script>
