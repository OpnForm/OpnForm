<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <button
      v-for="source in sources"
      :key="source.id"
      class="group rounded-lg border border-gray-200 p-4 flex items-center gap-3 cursor-pointer transition-colors hover:bg-blue-50 hover:border-blue-300 text-left"
      @click="$emit('select', source.id)"
    >
      <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 group-hover:bg-blue-100 flex items-center justify-center transition-colors">
        <Icon
          :name="source.icon"
          class="w-5 h-5 text-gray-600 group-hover:text-blue-600 transition-colors"
        />
      </div>
      <div>
        <p class="font-medium text-sm text-gray-900">
          {{ source.label }}
        </p>
        <p class="text-xs text-gray-500">
          {{ source.hint }}
        </p>
      </div>
    </button>
  </div>
</template>

<script setup>
defineEmits(['select'])

const sources = [
  {
    id: 'typeform',
    label: 'Typeform',
    hint: 'Import existing form',
    icon: 'heroicons:document-text',
  },
  {
    id: 'tally',
    label: 'Tally',
    hint: 'Import existing form',
    icon: 'heroicons:clipboard-document-list',
  },
  {
    id: 'fillout',
    label: 'Fillout',
    hint: 'Import existing form',
    icon: 'heroicons:document-duplicate',
  },
  {
    id: 'google_forms',
    label: 'Google Forms',
    hint: 'Sign in with Google',
    icon: 'heroicons:circle-stack',
  },
]
</script>
