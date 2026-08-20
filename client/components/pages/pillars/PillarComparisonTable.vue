<template>
  <section class="px-8 py-14 sm:px-12 sm:py-28 bg-white">
    <div class="mx-auto max-w-266">
      <div class="mx-auto max-w-2xl text-center">
        <p
          class="text-base font-medium leading-7 tracking-[-1.1%] text-blue-600"
        >
          {{ eyebrow }}
        </p>
        <h2
          class="my-4 text-4xl font-semibold tracking-[-1%] text-gray-950 sm:text-5xl sm:leading-14"
        >
          {{ title }}
        </h2>
        <p class="text-base font-normal leading-7 tracking-[-1.1%] text-gray-600">
          {{ description }}
        </p>
      </div>

      <div class="mt-12 sm:mt-16">
        <div
          class="hidden overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm lg:block"
        >
          <div class="overflow-x-auto">
            <table class="min-w-[920px] w-full border-collapse">
              <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                  <th
                    scope="col"
                    class="w-56 px-6 py-5 text-left text-sm font-semibold leading-5 tracking-[-0.6%] text-gray-500"
                  >
                    {{ labelColumnTitle }}
                  </th>
                  <th
                    v-for="column in columns"
                    :key="column.label"
                    scope="col"
                    class="px-6 py-5 text-left"
                    :class="column.highlight ? 'bg-blue-50/70' : ''"
                  >
                    <div class="flex items-center gap-2">
                      <span
                        v-if="column.logo || column.icon"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm"
                      >
                        <img
                          v-if="column.logo"
                          :src="column.logo"
                          :alt="column.label"
                          class="h-5 w-5"
                        />
                        <UIcon
                          v-else
                          :name="column.icon"
                          class="h-4.5 w-4.5"
                          :class="column.iconClass || 'text-blue-600'"
                        />
                      </span>
                      <div>
                        <div
                          class="text-sm font-semibold leading-5 tracking-[-0.6%] text-gray-950"
                        >
                          {{ column.label }}
                        </div>
                        <div
                          v-if="column.detail"
                          class="mt-1 text-xs font-medium leading-4 text-gray-500"
                        >
                          {{ column.detail }}
                        </div>
                      </div>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in rows" :key="row.label">
                  <th
                    scope="row"
                    class="px-6 py-5 text-left text-sm font-semibold leading-5 tracking-[-0.6%] text-gray-950"
                  >
                    {{ row.label }}
                  </th>
                  <td
                    v-for="(value, index) in row.values"
                    :key="`${row.label}-${columns[index]?.label}`"
                    class="px-6 py-5 align-top text-sm font-medium leading-6 tracking-[-0.6%] text-gray-600"
                    :class="columns[index]?.highlight ? 'bg-blue-50/50 text-gray-950' : ''"
                  >
                    {{ value }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="grid gap-4 lg:hidden">
          <div
            v-for="row in rows"
            :key="row.label"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
          >
            <div
              class="text-sm font-semibold leading-5 tracking-[-0.6%] text-gray-950"
            >
              {{ row.label }}
            </div>
            <div class="mt-4 divide-y divide-gray-100">
              <div
                v-for="(value, index) in row.values"
                :key="`${row.label}-mobile-${columns[index]?.label}`"
                class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
              >
                <div class="min-w-0">
                  <div
                    class="text-sm font-semibold leading-5 tracking-[-0.6%] text-gray-950"
                  >
                    <span class="inline-flex items-center gap-2">
                      <img
                        v-if="columns[index]?.logo"
                        :src="columns[index].logo"
                        :alt="columns[index].label"
                        class="h-4 w-4"
                      />
                      <span>{{ columns[index]?.label }}</span>
                    </span>
                  </div>
                  <div
                    v-if="columns[index]?.detail"
                    class="text-xs font-medium leading-4 text-gray-500"
                  >
                    {{ columns[index].detail }}
                  </div>
                </div>
                <div
                  class="max-w-[55%] text-right text-sm font-medium leading-5 tracking-[-0.6%] text-gray-600"
                  :class="columns[index]?.highlight ? 'text-blue-700' : ''"
                >
                  {{ value }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <p
          v-if="note"
          class="mt-5 text-center text-sm font-medium leading-5 tracking-[-0.6%] text-gray-500"
        >
          {{ note }}
        </p>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  eyebrow: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  labelColumnTitle: {
    type: String,
    default: "Capability",
  },
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    required: true,
  },
  note: {
    type: String,
    default: "",
  },
})
</script>
