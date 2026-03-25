<template>
  <div
    class="w-full mx-auto max-w-266 flex items-center justify-center relative z-10"
  >
    <div
      class="rounded-[12px] bg-[#D5E2FF] border border-gray-200 shadow-xl overflow-visible relative p-2.5 w-full"
    >
      <!-- <iframe
        class="min-h-150 sm:min-h-137.5 lg:min-h-175 bg-white rounded-[12px]"
        style="border: none; width: 100%"
        :src="url"
      /> -->
      <div class="min-h-[90vh] w-full overflow-hidden bg-white rounded-[6px]">
        <div
          class="flex items-center gap-3 justify-between border-b-[0.5px] border-gray-100 px-6 py-1"
        >
          <div class="flex items-center gap-3.5">
            <UIcon
              name="i-heroicons-view-columns-20-solid"
              class="h-4 w-4 text-gray-600"
            />
            <UIcon
              name="i-heroicons-arrow-left-20-solid"
              class="h-4 w-4 text-gray-600"
            />
            <UIcon
              name="i-heroicons-arrow-right-20-solid"
              class="h-4 w-4 text-gray-600"
            />
            <UIcon
              name="i-heroicons-arrow-path-20-solid"
              class="h-4 w-4 text-gray-600"
            />
          </div>
          <div class="flex items-center gap-1">
            <UIcon
              name="i-heroicons-link-20-solid"
              class="h-4 w-4 text-gray-600"
            />
            <p
              class="text-[12px] leading-4 tracking-[-1%] font-medium text-gray-600"
            >
              opnform.com/
            </p>
          </div>
          <div class="flex items-center gap-3.5">
            <UIcon
              name="i-heroicons-adjustments-horizontal-20-solid"
              class="h-4 w-4 text-gray-600"
            />
            <UIcon
              name="i-heroicons-view-columns-20-solid"
              class="h-4 w-4 text-gray-600"
            />
          </div>
        </div>
      </div>

        <NuxtLink
          :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
          class="absolute bottom-3 left-1/2 -translate-x-1/2 inline-flex items-center rounded-full overflow-hidden bg-gray-900 text-white text-sm"
        >
          <span class="px-4 py-2">
            Like a Demo?
          </span>
          <span class="px-4 py-2 bg-blue-600 hover:bg-blue-700">
            Try a Form
          </span>
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  url: {
    type: String,
    required: false,
    default: 'https://opnform.com/forms/opnform-contact',
  },
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

</script>
