import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import opnformConfig from "~/opnform.config.js"

function notionApiFetch (entityId, type = 'table') {
  const apiUrl = opnformConfig.notion.worker
  return useFetch(`${apiUrl}/${type}/${entityId}`)
}

function fetchNotionDatabasePages (databaseId) {
  return notionApiFetch(databaseId)
}

function fetchNotionPageContent (pageId) {
  return notionApiFetch(pageId, 'page')
}

function normalizeNotionRecordMap (recordMap) {
  if (!recordMap || typeof recordMap !== 'object') return recordMap

  return Object.fromEntries(
    Object.entries(recordMap).map(([id, record]) => {
      if (!record || typeof record !== 'object') {
        return [id, record]
      }

      if (record.value?.value) {
        return [id, { ...record, value: record.value.value, role: record.value.role ?? record.role }]
      }

      return [id, record]
    })
  )
}

export const useNotionCmsStore = defineStore('notion_cms', () => {

  // State
  const databases = ref({})
  const pages = ref({})
  const pageContents = ref({})
  const slugToIdMap = ref({})

  const loading = ref(false)

  // Actions
  const loadDatabase = (databaseId) => {
    return new Promise((resolve, reject) => {
      if (databases.value[databaseId]) return resolve()

      loading.value = true
      return fetchNotionDatabasePages(databaseId).then((response) => {
        databases.value[databaseId] = response.data.value.map(page => formatId(page.id))
        response.data.value.forEach(page => {
          pages.value[formatId(page.id)] = {
            ...page,
            id: formatId(page.id)
          }
          const slug = page.Slug ?? page.slug ?? null
          if (slug) {
            setSlugToIdMap(slug, page.id)
          }
        })
        loading.value = false
        resolve()
      }).catch((error) => {
        loading.value = false
        console.error(error)
        reject(error)
      })
    })
  }
  const loadPage = async (pageId) => {
    return new Promise((resolve, reject) => {
      if (pageContents.value[pageId]) return resolve('in already here')
      loading.value = true
      return fetchNotionPageContent(pageId).then((response) => {
        pageContents.value[formatId(pageId)] = normalizeNotionRecordMap(response.data.value)
        loading.value = false
        return resolve('in finishg')
      }).catch((error) => {
        console.error(error)
        loading.value = false
        return reject(error)
      })
    })
  }

  const loadPageBySlug = (slug) => {
    if (!slugToIdMap.value[slug.toLowerCase()]) return
    return loadPage(slugToIdMap.value[slug.toLowerCase()])
  }

  const formatId = (id) => id.replaceAll('-', '')

  const getPage = (pageId) => {
    return {
      ...pages.value[pageId],
      blocks: getPageBody(pageId)
    }
  }

  const getPageBody = (pageId) => {
    return pageContents.value[pageId]
  }

  const setSlugToIdMap = (slug, pageId) => {
    if (!slug) return
    slugToIdMap.value[slug.toLowerCase()] = formatId(pageId)
  }

  const getPageBySlug = (slug) => {
    if (!slug) return
    const pageId = slugToIdMap.value[slug.toLowerCase()]
    return getPage(pageId)
  }

// Getters
  const databasePages = (databaseId) => computed(() => databases.value[databaseId]?.map(id => pages.value[id]) || [])
  const pageContent = (pageId) => computed(() => pageContents.value[pageId])
  const pageBySlug = (slug) => computed(() => getPageBySlug(slug))

  return {
    // state
    loading,
    databases,
    pages,
    pageContents,
    slugToIdMap,

    // actions
    loadDatabase,
    loadPage,
    loadPageBySlug,
    getPage,
    getPageBody,
    setSlugToIdMap,
    getPageBySlug,

    // getters
    databasePages,
    pageContent,
    pageBySlug
  }
})
