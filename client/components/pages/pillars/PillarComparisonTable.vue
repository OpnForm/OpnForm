<template>
  <section class="bg-white px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24">
    <div class="mx-auto max-w-7xl">
      <div class="mx-auto max-w-3xl text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
          {{ eyebrow }}
        </p>
        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl lg:text-5xl">
          {{ title }}
        </h2>
        <p class="mt-5 text-lg leading-8 text-neutral-600">
          {{ description }}
        </p>
      </div>

      <slot name="before-table" />

      <div class="mt-10 sm:mt-12 lg:mt-16">
        <div
          class="mb-3 flex items-center justify-end gap-1.5 text-xs font-semibold text-neutral-500 lg:hidden"
        >
          <span>Swipe to compare</span>
          <UIcon name="i-heroicons-arrows-right-left-20-solid" class="h-4 w-4" />
        </div>

        <ul
          class="-mx-5 grid snap-x snap-mandatory grid-flow-col auto-cols-[minmax(17rem,88%)] gap-3 overflow-x-auto px-5 pb-3 sm:-mx-8 sm:auto-cols-[minmax(19rem,46%)] sm:px-8 lg:hidden"
        >
          <li
            v-for="(column, columnIndex) in columns"
            :key="column.label"
            class="snap-start overflow-hidden rounded-2xl border bg-white shadow-sm"
            :class="column.highlight ? 'border-blue-300 ring-1 ring-blue-100' : 'border-neutral-200'"
          >
            <div
              class="flex items-center gap-3 border-b px-4 py-3.5"
              :class="column.highlight ? 'border-blue-100 bg-blue-50/70' : 'border-neutral-100 bg-neutral-50'"
            >
              <span
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white text-xs font-bold text-neutral-500 shadow-sm"
              >
                <img
                  v-if="column.logo"
                  :src="column.logo"
                  :alt="column.label"
                  class="h-5 w-5"
                />
                <UIcon
                  v-else-if="column.icon"
                  :name="column.icon"
                  class="h-4.5 w-4.5"
                  :class="column.iconClass || 'text-blue-600'"
                />
                <span v-else aria-hidden="true">{{ column.label.slice(0, 1) }}</span>
              </span>
              <span class="min-w-0">
                <span class="block text-sm font-semibold leading-5 text-neutral-950">
                  {{ column.label }}
                </span>
                <span v-if="column.detail" class="block text-xs font-medium leading-4 text-neutral-500">
                  {{ column.detail }}
                </span>
              </span>
            </div>

            <dl class="divide-y divide-neutral-100 px-4">
              <div
                v-for="row in rows"
                :key="`${column.label}-${row.label}`"
                class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-3 py-2.5 text-xs leading-5"
              >
                <dt class="font-medium text-neutral-500">
                  {{ row.label }}
                </dt>
                <dd
                  class="text-right font-semibold"
                  :class="column.highlight ? 'text-blue-700' : 'text-neutral-800'"
                >
                  {{ row.values[columnIndex] }}
                </dd>
              </div>
            </dl>
          </li>
        </ul>

        <div class="hidden overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm lg:block">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] border-collapse">
              <caption class="sr-only">
                {{ caption || title }}
              </caption>
              <thead>
                <tr class="border-b border-neutral-200 bg-neutral-50">
                  <th scope="col" class="w-52 px-5 py-5 text-left text-sm font-semibold text-neutral-500">
                    {{ labelColumnTitle }}
                  </th>
                  <th
                    v-for="column in columns"
                    :key="column.label"
                    scope="col"
                    class="px-5 py-5 text-left"
                    :class="column.highlight ? 'bg-blue-50/70' : ''"
                  >
                    <div class="flex items-center gap-2">
                      <span
                        v-if="column.logo || column.icon"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm"
                      >
                        <img v-if="column.logo" :src="column.logo" :alt="column.label" class="h-5 w-5" />
                        <UIcon
                          v-else
                          :name="column.icon"
                          class="h-4.5 w-4.5"
                          :class="column.iconClass || 'text-blue-600'"
                        />
                      </span>
                      <div>
                        <div class="text-sm font-semibold text-neutral-950">{{ column.label }}</div>
                        <div v-if="column.detail" class="mt-1 text-xs font-medium text-neutral-500">
                          {{ column.detail }}
                        </div>
                      </div>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100">
                <tr v-for="row in rows" :key="row.label">
                  <th scope="row" class="px-5 py-5 text-left text-sm font-semibold text-neutral-950">
                    {{ row.label }}
                  </th>
                  <td
                    v-for="(value, index) in row.values"
                    :key="`${row.label}-${columns[index]?.label}`"
                    class="px-5 py-5 align-top text-sm font-medium leading-6 text-neutral-600"
                    :class="columns[index]?.highlight ? 'bg-blue-50/50 text-neutral-950' : ''"
                  >
                    {{ value }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="note" class="mx-auto mt-5 max-w-4xl text-center text-sm leading-6 text-neutral-500">
          {{ note }}
        </p>

        <p
          v-if="reviewedAt || sources.length"
          class="mx-auto mt-3 max-w-4xl text-center text-xs font-medium leading-5 text-neutral-500"
        >
          <span v-if="reviewedAt">Last reviewed {{ reviewedAt }}.</span>
          <span v-if="reviewedAt && sources.length"> </span>
          <span v-if="sources.length">
            {{ sourcesLabel }}:
            <template v-for="(source, index) in sources" :key="source.href">
              <a
                :href="source.href"
                target="_blank"
                rel="noopener noreferrer"
                class="underline decoration-neutral-300 underline-offset-2 transition-colors hover:text-neutral-800 hover:decoration-neutral-500"
              >{{ source.label }}</a><span v-if="index < sources.length - 1">, </span>
            </template>.
          </span>
        </p>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  eyebrow: { type: String, required: true },
  title: { type: String, required: true },
  description: { type: String, required: true },
  labelColumnTitle: { type: String, default: "Capability" },
  caption: { type: String, default: "" },
  columns: { type: Array, required: true },
  rows: { type: Array, required: true },
  note: { type: String, default: "" },
  reviewedAt: { type: String, default: "" },
  sources: { type: Array, default: () => [] },
  sourcesLabel: { type: String, default: "Official sources" },
})
</script>
