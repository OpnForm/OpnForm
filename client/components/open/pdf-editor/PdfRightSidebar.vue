<template>
  <div class="w-72 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
    <!-- Add Zone -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
      <UDropdownMenu
        autofocus
        :items="addZoneMenuItems"
        :ui="{ content: 'w-(--reka-dropdown-menu-trigger-width) min-w-56 max-h-80' }"
        arrow
      >
        <template #content-top>
          <div
            v-if="formFields.length"
            class="p-2 border-b border-gray-100 dark:border-gray-700"
          >
            <UInput
              ref="searchInput"
              v-model="formFieldsSearch"
              variant="outline"
              class="w-full"
              placeholder="Search form fields..."
              icon="i-heroicons-magnifying-glass-solid"
              :ui="{ trailing: 'pe-1' }"
              @click.stop
              @keydown.stop
            >
              <template v-if="formFieldsSearch?.length" #trailing>
                <UButton
                  color="neutral"
                  variant="link"
                  size="sm"
                  icon="i-lucide-circle-x"
                  aria-label="Clear"
                  title="Clear"
                  @click="formFieldsSearch = ''"
                />
              </template>
            </UInput>
          </div>
        </template>
        <UButton
          color="primary"
          variant="soft"
          size="lg"
          icon="i-heroicons-plus"
          block
          trailing-icon="i-heroicons-chevron-down"
        >
          Add Zone
        </UButton>
      </UDropdownMenu>
    </div>

    <!-- Zones List -->
    <div class="flex-1 overflow-y-auto">
      <!-- Zone Properties (when selected) -->
      <div
        v-if="selectedZone"
        class="p-4 space-y-4"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="min-w-0">
            <div class="flex items-center gap-1 text-xs">
              <button
                class="font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 transition-colors"
                @click="goBackToZones"
              >
                Zones
              </button>
              <UIcon name="i-heroicons-chevron-right" class="w-3 h-3 text-neutral-400" />
              <span class="font-medium text-neutral-900 dark:text-white truncate">
                {{ selectedZoneLabel }}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <UButton
              color="error"
              variant="ghost"
              icon="i-heroicons-trash"
              size="xs"
              @click="deleteSelectedZone"
            />
          </div>
        </div>

        <!-- Field/Static Text/Image -->
        <RichTextAreaInput
          v-if="selectedZone.static_text !== undefined"
          v-model="selectedZone.static_text"
          name="static_text"
          label="Static Text"
          placeholder="Enter text..."
          size="sm"
          :editor-options="richTextOptions"
        />
        <ImageInput
          v-else-if="selectedZone.static_image !== undefined"
          v-model="selectedZone.static_image"
          name="static_image"
          label="Image"
          size="sm"
        />
        <SelectInput
          v-else
          v-model="selectedZone.field_id"
          name="field_id"
          label="Mapped Field"
          :options="fieldOptions"
          size="sm"
        />

        <!-- Font Size (hidden for text and image zones) -->
        <template v-if="selectedZone.static_text === undefined && selectedZone.static_image === undefined">
          <TextInput
            v-model="selectedZone.font_size"
            name="font_size"
            label="Font Size (px)"
            native-type="number"
            :min="6"
            :max="72"
            size="sm"
            class="mt-4"
          />

          <!-- Font Color -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Font Color
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model="selectedZone.font_color"
                type="color"
                class="h-9 w-9 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5"
              >
              <TextInput
                v-model="selectedZone.font_color"
                name="font_color"
                placeholder="#000000"
                size="sm"
                :hide-field-name="true"
                wrapper-class="flex-1"
              />
            </div>
          </div>
        </template>
      </div>

      <!-- No Zone Selected / Zones List -->
      <div v-else class="p-4">
        <div
          v-if="currentPageZones?.length === 0"
          class="text-center py-8"
        >
          <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
            <UIcon name="i-heroicons-cursor-arrow-ripple" class="w-6 h-6 text-gray-400" />
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            No zones yet on this page
          </p>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
            Click "Add Zone" to map form fields to PDF locations
          </p>
        </div>
        
        <!-- Zones list -->
        <div v-else class="rounded-md border border-neutral-300">
          <div
            v-for="zone in currentPageZones"
            :key="zone.id"
            class="flex items-center justify-between gap-2 p-3 transition-colors cursor-pointer border-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
            :class="{
              'border-b': zone !== currentPageZones[currentPageZones.length - 1]
            }"
            @click="pdfStore.setSelectedZone(zone.id)"
          >
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                {{ getZoneLabel(zone) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Page {{ zone.page }} • {{ zone.font_size }}px
              </p>
            </div>
            <UTooltip arrow text="Open settings">
              <button
                class="shrink-0 cursor-pointer rounded-sm p-1 transition-colors hover:bg-blue-100 text-neutral-300 hover:text-blue-500 flex items-center justify-center field-settings-button"
                @click.stop="pdfStore.setSelectedZone(zone.id)"
              >
                <Icon
                  name="heroicons:cog-8-tooth-solid"
                  class="h-5 w-5"
                />
              </button>
            </UTooltip>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import ImageInput from '~/components/forms/heavy/ImageInput.vue'

const pdfStore = useWorkingPdfStore()

const { 
  currentPageZones,
  selectedZone,
  formFields,
  specialFields,
  fieldOptions,
} = storeToRefs(pdfStore)

const { 
  addZoneWithField,
  deleteSelectedZone,
  getZoneLabel,
} = pdfStore

const formFieldsSearch = ref('')

const filteredFormFields = computed(() => {
  const q = formFieldsSearch.value.trim().toLowerCase()
  if (!q) return formFields.value
  return formFields.value.filter((f) => f.name.toLowerCase().includes(q))
})

const addZoneMenuItems = computed(() => {
  const items = []

  if (formFields.value.length) {
    const formFieldItems = [
      { type: 'label', label: 'Form Fields' },
      ...filteredFormFields.value.map((field) => ({
        label: field.name,
        onSelect: () => addZoneWithField(field),
      })),
    ]
    if (filteredFormFields.value.length) {
      items.push(formFieldItems)
    } else {
      items.push([
        { type: 'label', label: 'Form Fields' },
        { type: 'label', label: `No fields match "${formFieldsSearch.value}"` },
      ])
    }
  }

  items.push([
    { type: 'label', label: 'Special Fields' },
    ...specialFields.value.map((field) => ({
      label: field.name,
      onSelect: () => addZoneWithField(field),
    })),
  ])

  items.push([{
    label: 'Static Text',
    icon: 'i-heroicons-pencil',
    onSelect: () => addZoneWithField(null, 'static_text'),
  }, {
    label: 'Image',
    icon: 'i-heroicons-photo',
    onSelect: () => addZoneWithField(null, 'static_image'),
  }])

  return items
})

const selectedZoneLabel = computed(() => {
  if (!selectedZone.value) return ''
  return getZoneLabel(selectedZone.value)
})

const goBackToZones = () => {
  pdfStore.setSelectedZone(null)
}

const richTextOptions = {
  formats: ['bold', 'italic', 'underline', 'header', 'color'],
  modules: {
    toolbar: [
      [{ header: 1 }, { header: 2 }],
      ['bold', 'italic', 'underline'],
      [{ color: [] }],
    ],
  },
}
</script>
