let mentionParserModule = null
let mentionParserModulePromise = null

export function loadMentionParser() {
  if (mentionParserModule) return Promise.resolve(mentionParserModule.useParseMention)
  if (mentionParserModulePromise) return mentionParserModulePromise

  mentionParserModulePromise = import('~/composables/components/useParseMention.js')
    .then((module) => {
      mentionParserModule = module
      return module.useParseMention
    })
    .catch((error) => {
      mentionParserModulePromise = null
      throw error
    })

  return mentionParserModulePromise
}

export function clearLoadedMentionCache() {
  mentionParserModule?.clearMentionCache()
}
