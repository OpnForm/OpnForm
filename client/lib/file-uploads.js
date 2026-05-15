import { uploadsApi } from "~/api"

async function storeLocalFile(file) {
  let formData = new FormData()
  formData.append("file", file)
  const response = await uploadsApi.uploadFile(formData)
  response.extension = file.name.split(".").pop()
  return response
}

function normalizeSignedStorageHeaders(headers = {}) {
  return Object.entries(headers).reduce((normalizedHeaders, [name, value]) => {
    if (name.toLowerCase() === 'host' || value === null || typeof value === 'undefined') {
      return normalizedHeaders
    }

    normalizedHeaders[name] = Array.isArray(value) ? value.join(', ') : value

    return normalizedHeaders
  }, {})
}

export const storeFile = async (file, options = {}) => {
  if (useFeatureFlag('storage.local'))
    return storeLocalFile(file, options)

  const response = await uploadsApi.getSignedStorageUrl({
    ...options.data,
    bucket: options.bucket || "",
    content_type: options.contentType || file.type,
    expires: options.expires || "",
    visibility: options.visibility || "",
    baseURL: options.baseURL || null,
    headers: options.headers || {},
    ...options.options,
  })

  // Upload to S3
  await useFetch(response.url, {
    method: "PUT",
    body: file,
    headers: normalizeSignedStorageHeaders(response.headers),
  })

  response.extension = file.name.split(".").pop()

  return response
}
