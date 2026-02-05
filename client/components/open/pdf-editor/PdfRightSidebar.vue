<template>
  <div class="w-72 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
    <!-- Add Zone -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
      <div class="relative">
        <UButton
          color="primary"
          variant="soft"
          icon="i-heroicons-plus"
          block
          @click="pdfStore.setShowAddZonePopover(!showAddZonePopover)"
        >
          Add Zone
        </UButton>
        
        <!-- Field Selection Popover -->
        <div
          v-if="showAddZonePopover"
          class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-10"
        >
          <div class="p-2 border-b border-gray-100 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Select field to map
            </p>
          </div>
          <div class="max-h-64 overflow-y-auto p-1">
            <!-- Form fields -->
            <template v-if="formFields.length">
              <p class="text-xs text-gray-400 dark:text-gray-500 px-2 py-1.5 font-medium">
                Form Fields
              </p>
              <button
                v-for="field in formFields"
                :key="field.id"
                class="w-full text-left px-3 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                @click="addZoneWithField(field)"
              >
                {{ field.name }}
              </button>
            </template>
            
            <!-- Special fields -->
            <p class="text-xs text-gray-400 dark:text-gray-500 px-2 py-1.5 font-medium mt-1">
              Special Fields
            </p>
            <button
              v-for="field in specialFields"
              :key="field.id"
              class="w-full text-left px-3 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-400"
              @click="addZoneWithField(field)"
            >
              {{ field.name }}
            </button>
            
            <!-- Static text -->
            <div class="border-t border-gray-100 dark:border-gray-700 mt-1 pt-1">
              <button
                class="w-full text-left px-3 py-2 text-sm rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors text-blue-600 dark:text-blue-400 flex items-center gap-2"
                @click="addZoneWithField()"
              >
                <UIcon name="i-heroicons-pencil" class="w-4 h-4" />
                Static Text
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Zones List -->
    <div class="flex-1 overflow-y-auto">
      <!-- Zone Properties (when selected) -->
      <div
        v-if="selectedZone"
        class="p-4 space-y-4"
      >
        <div class="flex items-center justify-between">
          <h3 class="font-medium text-gray-900 dark:text-white text-sm">
            Zone Properties
          </h3>
          <UButton
            color="error"
            variant="ghost"
            icon="i-heroicons-trash"
            size="xs"
            @click="deleteSelectedZone"
          />
        </div>

        <!-- Field/Static Text -->
        <TextAreaInput
          v-if="selectedZone.static_text !== undefined"
          v-model="selectedZone.static_text"
          name="static_text"
          label="Static Text"
          placeholder="Enter text..."
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

        <!-- Font Size -->
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
      </div>

      <!-- No Zone Selected / Zones List -->
      <div v-else class="p-4">
        <div
          v-if="!pdfTemplate?.zone_mappings?.length"
          class="text-center py-8"
        >
          <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
            <UIcon name="i-heroicons-cursor-arrow-ripple" class="w-6 h-6 text-gray-400" />
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            No zones yet
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
const pdfStore = useWorkingPdfStore()

const { 
  content: pdfTemplate,
  showAddZonePopover,
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
</script>
