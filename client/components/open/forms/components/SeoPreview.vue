<template>
  <div class="flex-1 lg:max-w-md lg:order-last py-1">
    <h4 class="mb-1 text-sm text-neutral-400 text-center">
      Social Media Preview
    </h4>
    <div class="border border-neutral-200 rounded-lg p-2 bg-white shadow-sm">
      <!-- Preview Image -->
      <div 
        v-if="previewImage" 
        class="w-full aspect-[1.91/1] bg-neutral-100 rounded-lg overflow-hidden flex items-center justify-center mb-3"
      >
        <img 
          :src="previewImage" 
          alt="Preview" 
          class="w-full h-full object-cover"
        />
      </div>
      <div 
        v-else 
        class="w-full aspect-[1.91/1] bg-neutral-100 rounded-lg flex items-center justify-center mb-3"
      >
        <div class="text-neutral-400 text-center">
          <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
          </svg>
          <p class="text-xs">No preview image</p>
        </div>
      </div>
      
      <!-- Preview Text -->
      <div class="space-y-1">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">
          {{ previewDomain }}
        </p>
        <h3 class="font-semibold text-neutral-900 text-sm leading-tight" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-clamp: 2;">
          {{ previewTitle }}
        </h3>
        <p class="text-xs text-neutral-600 leading-tight" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-clamp: 2;">
          {{ previewDescription }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: {
    type: Object,
    required: true
  }
})

// Preview computed properties - mirrors logic from pages/forms/[slug]/index.vue
const previewImage = computed(() => {
  if (props.form.seo_meta?.page_thumbnail) {
    return props.form.seo_meta.page_thumbnail
  }
  if (props.form?.cover_picture) {
    return props.form.cover_picture
  }
  return '/img/social-preview.jpg'
})

const previewTitle = computed(() => {
  if (props.form.seo_meta?.page_title) {
    return props.form.seo_meta.page_title
  }
  return props.form?.title ? `${props.form.title} - OpnForm` : 'OpnForm'
})

const previewDescription = computed(() => {
  if (props.form.seo_meta?.page_description) {
    return props.form.seo_meta.page_description
  }
  return 'Build beautiful, powerful forms for free with OpnForm. Unlimited submissions, rich features, and seamless integrations — fully open-source and easy to use.'
})

const previewDomain = computed(() => {
  if (props.form?.custom_domain) {
    return props.form.custom_domain.toUpperCase()
  }
  return 'OPNFORM.COM'
})
</script>
