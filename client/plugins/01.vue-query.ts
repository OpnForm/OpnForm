import type {
  DehydratedState,
  VueQueryPluginOptions,
} from '@tanstack/vue-query'
import {
  VueQueryPlugin,
  QueryClient,
  hydrate,
  dehydrate,
} from '@tanstack/vue-query'
// Nuxt 3 app aliases
import { useState } from '#app'

export default defineNuxtPlugin((nuxt) => {
  const vueQueryState = useState<DehydratedState | null>('vue-query')
  const shouldEnableVueQueryDevtools =
    process.env.NODE_ENV === 'development' && process.env.E2E !== '1'

  // Modify your Vue Query global settings here
  const queryClient = new QueryClient({
    defaultOptions: { 
      queries: { 
        staleTime: 5 * 60 * 1000, // 5 minutes
        gcTime: 10 * 60 * 1000, // 10 minutes (replaces cacheTime)
        retry: (failureCount, error: any) => {
          // Don't retry on 4xx errors except 408, 429
          if (error?.status >= 400 && error?.status < 500 && ![408, 429].includes(error?.status)) {
            return false
          }
          // Retry up to 3 times for other errors
          return failureCount < 3
        },
        refetchOnWindowFocus: false,
        refetchOnReconnect: 'always'
      },
      mutations: {
        retry: (failureCount, error: any) => {
          // Don't retry mutations on 4xx errors
          if (error?.status >= 400 && error?.status < 500) {
            return false
          }
          return failureCount < 1
        }
      }
    },
  })
  const options: VueQueryPluginOptions = {
    queryClient,
    enableDevtoolsV6Plugin: shouldEnableVueQueryDevtools,
  }

  nuxt.vueApp.use(VueQueryPlugin, options)

  if (import.meta.server) {
    nuxt.hooks.hook('app:rendered', () => {
      vueQueryState.value = dehydrate(queryClient)
    })
  }

  if (import.meta.client) {
    // Hydrate immediately so that route middleware can access cached data
    hydrate(queryClient, vueQueryState.value)

    // Still keep the hook for safety in case Nuxt re-creates app (e.g., HMR)
    nuxt.hooks.hook('app:created', () => {
      hydrate(queryClient, vueQueryState.value)
    })
  }
}) 
