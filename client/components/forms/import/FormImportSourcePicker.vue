<template>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
    <div
      v-for="source in sources"
      role="button"
      class="rounded-md border p-4 flex h-full flex-col items-center cursor-pointer hover:bg-neutral-50"
      @click="$emit('select', source.id)"
    >
      <div class="flex flex-1 items-center p-2">
        <UIcon
          :name="source.icon"
          :class="source.iconClass"
        />
      </div>
      <p class="mt-auto font-medium text-center">
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
    iconClass: 'w-14 h-14 text-[#262627]',
  },
  {
    id: 'tally',
    label: 'Tally',
    icon: 'opnform:tally',
    iconClass: 'w-8 h-8 text-[#725BFF]',
  },
  {
    id: 'fillout',
    label: 'Fillout',
    icon: 'i-simple-icons-fillout',
    iconClass: 'w-12 h-12 text-[#FFC738]',
  },
  {
    id: 'google_forms',
    label: 'Google Forms',
    icon: 'i-simple-icons-googleforms',
    iconClass: 'w-8 h-8 text-[#7248B9]',
  },
]
</script>
