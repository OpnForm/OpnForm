import { defineCollection, defineContentConfig } from '@nuxt/content'

export default defineContentConfig({
  collections: {
    features: defineCollection({
      source: 'features/*.md',
      type: 'page',
    }),
  },
})
