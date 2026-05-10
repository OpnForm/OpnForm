<template>
  <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
    <button
      v-for="(source, sourceIndex) in sources"
      :key="source.id"
      type="button"
      class="group flex min-h-30 w-full flex-col justify-between rounded-lg border border-neutral-200 bg-white p-4 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-[0_18px_42px_-30px_rgba(37,99,235,0.38)] focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/30"
      :class="sources.length % 2 === 1 && sourceIndex === sources.length - 1 ? 'sm:col-span-2' : ''"
      @click="$emit('select', source.id)"
    >
      <div class="flex items-start justify-between gap-3">
        <span
          class="flex h-10 w-10 items-center justify-center rounded-lg border bg-white shadow-sm"
          :class="source.iconWrapClass"
        >
          <UIcon
            :name="source.icon"
            :class="source.iconClass"
          />
        </span>
        <Icon
          name="i-heroicons-arrow-right"
          class="mt-1 h-4 w-4 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-blue-500"
        />
      </div>
      <div class="mt-4">
        <p class="font-semibold text-neutral-950">{{ source.label }}</p>
        <p class="mt-1 text-xs leading-5 text-neutral-500">{{ source.description }}</p>
      </div>
    </button>
  </div>
</template>

<script setup>
defineEmits(['select'])

const props = defineProps({
  allowGoogleForms: { type: Boolean, default: true },
})

const allSources = [
  {
    id: 'typeform',
    label: 'Typeform',
    description: 'Import public Typeform forms from a share URL.',
    icon: 'i-simple-icons-typeform',
    iconClass: 'w-5 h-5 text-[#262627]',
    iconWrapClass: 'border-neutral-200',
  },
  {
    id: 'tally',
    label: 'Tally',
    description: 'Bring over a Tally form structure quickly.',
    icon: 'opnform:tally',
    iconClass: 'w-5 h-5 text-[#725BFF]',
    iconWrapClass: 'border-violet-100 bg-violet-50',
  },
  {
    id: 'fillout',
    label: 'Fillout',
    description: 'Start from an existing Fillout form URL.',
    icon: 'i-simple-icons-fillout',
    iconClass: 'w-5 h-5 text-[#FFC738]',
    iconWrapClass: 'border-amber-100 bg-amber-50',
  },
  {
    id: 'google_forms',
    label: 'Google Forms',
    description: 'Connect Google and import from an edit URL.',
    icon: 'i-simple-icons-googleforms',
    iconClass: 'w-5 h-5 text-[#7248B9]',
    iconWrapClass: 'border-purple-100 bg-purple-50',
  },
]

const sources = computed(() => allSources.filter((source) => {
  return props.allowGoogleForms || source.id !== 'google_forms'
}))
</script>
