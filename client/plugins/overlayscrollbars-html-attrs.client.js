/**
 * Adds data-overlayscrollbars-initialize to <html> and <body> before the
 * body scrollbar plugin initializes, except inside SDK embeds where the
 * body scrollbar is intentionally not initialized.
 */
export default defineNuxtPlugin(() => {
  if (useIsIframe()) return

  useHead({
    htmlAttrs: {
      'data-overlayscrollbars-initialize': '',
    },
    bodyAttrs: {
      'data-overlayscrollbars-initialize': '',
    },
  })
})
