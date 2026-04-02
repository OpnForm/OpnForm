import { computed } from 'vue'

export default function useNotionBlock (props) {

  const block = computed(() => {
    const id = props.contentId || Object.keys(props.blockMap)[0]
    return props.blockMap[id]
  })

  const value = computed(() => {
    return block.value?.value?.value || block.value?.value || block.value
  })

  const format = computed(() => {
    return value.value?.format
  })

  const icon = computed(() => {
    return format.value?.page_icon || ''
  })

  const width = computed(() => {
    return format.value?.block_width
  })

  const properties = computed(() => {
    return value.value?.properties
  })

  const caption = computed(() => {
    return properties.value?.caption
  })

  const description = computed(() => {
    return properties.value?.description
  })

  const src = computed(() => {
    return mapImageUrl(properties.value?.source[0][0], block.value)
  })

  const title = computed(() => {
    return properties.value?.title
  })

  const alt = computed(() => {
    return caption.value?.[0][0]
  })

  const type = computed(() => {
    return value.value?.type
  })

  const visible = computed(() => {
    return !props.hideList.includes(type.value)
  })

  const hasPageLinkOptions = computed(() => {
    return props.pageLinkOptions?.component && props.pageLinkOptions?.href
  })

  const parent = computed(() => {
    return props.blockMap[value.value?.parent_id]
  })

  const innerJson = computed(() => {
    if (type.value !== 'code') return
    if (properties.value.language.flat('Infinity').join('') !== 'JSON') {
      return
    }
    try {
      return JSON.parse(
        title.value.flat(Infinity).join('').replace(/\n/g, '').replace(/\t/g, '').trim()
      )
    } catch (error) {
      console.error('Failed to parse JSON',
        error,
        title.value.flat(Infinity).join('').replace(/\n/g, '').replace(/\t/g, '').trim()
      )
      return
    }
  })

  function mapImageUrl (source) {
    // Implement your mapImageUrl logic here
    return source
  }

  return {
    icon,
    width,
    properties,
    caption,
    description,
    src,
    title,
    alt,
    block,
    value,
    format,
    type,
    visible,
    hasPageLinkOptions,
    parent,
    innerJson
  }
}
