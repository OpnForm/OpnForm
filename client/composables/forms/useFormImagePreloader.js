export const useFormImagePreloader = (formRef, stateRef) => {
  const form = computed(() => formRef?.value || {})
  const state = computed(() => stateRef?.value || {})

  const allBlockImages = computed(() => {
    const properties = form.value?.properties || []
    return properties
      .map(f => f?.image?.url)
      .filter((u) => typeof u === 'string' && u.length > 0)
  })

  const staticImages = computed(() => {
    const imgs = []
    if (form.value?.cover_picture) imgs.push(form.value.cover_picture)
    if (form.value?.logo_picture) imgs.push(form.value.logo_picture)
    return imgs
  })

  const allImageUrls = computed(() => {
    const set = new Set([...(staticImages.value || []), ...(allBlockImages.value || [])])
    return Array.from(set)
  })

  const criticalImageUrls = computed(() => {
    const urls = []
    if (form.value?.cover_picture) urls.push(form.value.cover_picture)
    if (form.value?.logo_picture) urls.push(form.value.logo_picture)
    const properties = form.value?.properties || []
    const start = state.value?.currentPage ?? 0
    for (let i = start; i < Math.min(properties.length, start + 3); i++) {
      const url = properties[i]?.image?.url
      if (url) urls.push(url)
    }
    return Array.from(new Set(urls))
  })

  useHead(() => ({
    link: (criticalImageUrls.value || []).map((href) => ({ rel: 'preload', as: 'image', href }))
  }))

  function warmImageCache(urls) {
    if (!urls || urls.length === 0) return
    urls.forEach((u) => {
      try {
        const img = new Image()
        img.decoding = 'async'
        img.src = u
      } catch { /* no-op */ }
    })
  }

  onMounted(() => {
    if (import.meta.client) {
      warmImageCache(allImageUrls.value)
    }
  })

  watch(allImageUrls, (urls) => {
    if (import.meta.client) warmImageCache(urls)
  })

  return {
    allImageUrls,
    criticalImageUrls,
  }
}


