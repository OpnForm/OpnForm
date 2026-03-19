<template>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
    <div
      v-for="source in sources"
      role="button"
      class="rounded-md border p-4 flex flex-col items-center cursor-pointer hover:bg-neutral-50"
      @click="$emit('select', source.id)"
    >
      <div class="p-2">
        <UIcon
          :name="source.icon"
          class="w-10 h-10 text-blue-500"
        />
      </div>
      <p class="font-medium text-center">
        {{ source.label }}
      </p>
    </div>
  </div>
</template>

<script setup>
defineEmits(['select'])

const sources = [
  {
    id: 'typeform',
    label: 'Typeform',
    icon: 'i-simple-icons-typeform',
  },
  {
    id: 'tally',
    label: 'Tally',
    icon: 'i-heroicons-link',
  },
  {
    id: 'fillout',
    label: 'Fillout',
    icon: 'i-simple-icons-fillout',
  },
  {
    id: 'google_forms',
    label: 'Google Forms',
    icon: 'i-simple-icons-googleforms',
  },
]
</script>
