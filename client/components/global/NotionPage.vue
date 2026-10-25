<template>
  <NotionRenderer
    v-if="!loading"
    :block-map="blocks"
    :block-overrides="blockOverrides"
    :content-id="contentId"
    :full-page="fullPage"
    :hide-list="hideList"
    :level="level"
    :map-image-url="mapImageUrl"
    :map-page-url="mapPageUrl"
    :page-link-options="pageLinkOptions"
    :image-options="imageOptions"
    :prism="prism"
    :todo="todo"
  />
  <div
    v-else
    class="p-6 flex items-center justify-center"
  >
    <loader class="w-6 h-6" />
  </div>
</template>

<script>
import { NotionRenderer, defaultMapPageUrl, defaultMapImageUrl } from 'vue-notion'

export default {
  name: 'NotionPage',
  components: { NotionRenderer },
  props: {
    blockMap: {
      type: Object
    },
    blockOverrides: {
      type: Object,
      default: () => ({})
    },
    loading: {
      type: Boolean
    },
    contentId: String,
    contentIndex: { type: Number, default: 0 },
    fullPage: { type: Boolean, default: false },
    hideList: { type: Array, default: () => [] },
    level: { type: Number, default: 0 },
    mapImageUrl: { type: Function, default: defaultMapImageUrl },
    mapPageUrl: { type: Function, default: defaultMapPageUrl },
    pageLinkOptions: {
      type: Object, default: () => {
        const NuxtLink = resolveComponent('NuxtLink')
        return { component: NuxtLink, href: 'to' }
      }
    },
    imageOptions: Object,
    prism: { type: Boolean, default: false },
    todo: { type: Boolean, default: false }
  },
  computed: {
    blocks () {
      if (this.blockMap && this.blockMap.data) {
        return this.blockMap.data
      }
      return this.blockMap
    }

  }
}
</script>

<style lang='scss'>
@reference '../../css/app.css';

@import "vue-notion/src/styles.css";

.notion-blue {
  @apply text-blue-500;
}

.notion-spacer {
  width: 24px !important;
}

.notion-link {
  text-decoration: none;
}

.notion {
  img, iframe {
    @apply rounded-md;
  }
}
</style>
