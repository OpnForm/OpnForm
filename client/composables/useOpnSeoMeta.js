import { useSubdomainRedirect } from '~/composables/useSubdomainRedirect'

export const useOpnSeoMeta = (meta, alwaysEnabled = false) => {
  const { shouldRedirect } = useSubdomainRedirect()

  if (!alwaysEnabled && shouldRedirect()) {
    return
  }

  return useSeoMeta({
    ...(meta.title
      ? {
          ogTitle: meta.title,
          twitterTitle: meta.title,
        }
      : {}),
    ...(meta.description
      ? {
          ogDescription: meta.description,
          twitterDescription: meta.description,
        }
      : {}),
    ...(meta.ogImage
      ? {
          twitterImage: meta.ogImage,
        }
      : {}),
    ...meta,
  })
}
