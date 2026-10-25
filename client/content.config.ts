import { defineCollection, defineContentConfig } from '@nuxt/content'

export default defineContentConfig({
  collections: {
    integrations: defineCollection({
      source: 'integrations/*.md',
      type: 'page',
    }),
  },
})
