import { serialize } from "object-to-formdata"
import Errors from "./Errors"
import cloneDeep from "clone-deep"
import { apiService } from "~/api"
function hasFiles(data) {
  return (
    data instanceof File ||
    data instanceof Blob ||
    data instanceof FileList ||
    (typeof data === "object" &&
      data !== null &&
      Object.values(data).find((value) => hasFiles(value)) !== undefined)
  )
}

class Form {
  constructor(data = {}) {
    this.originalData = {}
    this.busy = false
    this.successful = false
    this.recentlySuccessful = false
    this.recentlySuccessfulTimeoutId = undefined
    this.errors = new Errors()
    this.update(data)
  }

  static errorMessage = "Something went wrong. Please try again."
  static recentlySuccessfulTimeout = 2000
  static ignore = [
    "busy",
    "successful",
    "errors",
    "originalData",
    "recentlySuccessful",
    "recentlySuccessfulTimeoutId",
  ]

  static make(augment) {
    return new this(augment)
  }

  update(data) {
    this.originalData = Object.assign({}, this.originalData, cloneDeep(data))
    Object.assign(this, data)
  }

  fill(data = {}) {
    this.keys().forEach((key) => {
      this[key] = data[key]
    })
  }

  data() {
    return this._getSerializableData()
  }

  keys() {
    return Object.keys(this).filter((key) => !Form.ignore.includes(key))
  }

  // Internal helper to safely collect form values even if a field is named "data"
  _getSerializableData() {
    return this.keys().reduce(
      (acc, key) => ({ ...acc, [key]: this[key] }),
      {},
    )
  }

  startProcessing() {
    this.errors.clear()
    this.busy = true
    this.successful = false
    this.recentlySuccessful = false
    clearTimeout(this.recentlySuccessfulTimeoutId)
  }

  finishProcessing() {
    this.busy = false
    this.successful = true
    this.recentlySuccessful = true
    this.recentlySuccessfulTimeoutId = setTimeout(() => {
      this.recentlySuccessful = false
    }, Form.recentlySuccessfulTimeout)
  }

  clear() {
    this.errors.clear()
    this.successful = false
    this.recentlySuccessful = false
    clearTimeout(this.recentlySuccessfulTimeoutId)
  }

  resetAndFill(data = {}) {
    // Clear form state
    this.clear()

    // Remove any previously set keys that are not present in the incoming data
    // This avoids stale values when switching contexts (e.g., viewing different submissions)
    const incomingKeys = new Set(Object.keys(data || {}))
    this.keys()
      .filter((key) => !incomingKeys.has(key))
      .forEach((key) => {
        delete this[key]
      })

    // Reset and update form data using the existing update method
    this.originalData = {}
    this.update(data)
    
    return this
  }

  reset() {
    Object.keys(this)
      .filter((key) => !Form.ignore.includes(key))
      .forEach((key) => {
        this[key] = cloneDeep(this.originalData[key])
      })
  }

  get(url, config = {}) {
    return this.submit("get", url, config)
  }

  post(url, config = {}) {
    return this.submit("post", url, config)
  }

  patch(url, config = {}) {
    return this.submit("patch", url, config)
  }

  put(url, config = {}) {
    return this.submit("put", url, config)
  }

  delete(url, config = {}) {
    return this.submit("delete", url, config)
  }

  submit(method, url, config = {}) {
    this.startProcessing()

    config = {
      body: {},
      params: {},
      url: url,
      method: method,
      ...config,
    }

    if (method.toLowerCase() === "get") {
      config.params = { ...this._getSerializableData(), ...config.params }
    } else {
      config.body = { ...this._getSerializableData(), ...config.data, ...config.body }

      if (hasFiles(config.data) && !config.transformRequest) {
        config.transformRequest = [(data) => serialize(data)]
      }
    }
    return new Promise((resolve, reject) => {
      apiService.mutate(config.url, config)
        .then((data) => {
          this.finishProcessing()
          resolve(data)
        })
        .catch((error) => {
          this.handleErrors(error)
          reject(error)
        })
    })
  }

  validate(method, url, config = {}, fieldsToValidate = {}) {
    this.startProcessing()
    const headers = {
      'Precognition': true,
      'Precognition-Validate-Only': Array.from(fieldsToValidate).join(),
      ...config.headers
    }
    config = {
      body: {},
      params: {},
      url: url,
      method: method,
      headers,
      ...config,
    }
    if (method.toLowerCase() === "get") {
      config.params = { ...this._getSerializableData(), ...config.params }
    } else {
      config.body = { ...this._getSerializableData(), ...config.data }

      if (hasFiles(config.data) && !config.transformRequest) {
        config.transformRequest = [(data) => serialize(data)]
      }
    }
    return new Promise((resolve, reject) => {
      apiService.mutate(config.url, config)
        .then((data) => {
          this.finishProcessing()
          resolve(data)
        })
        .catch((error) => {
          this.handleErrors(error)
          reject(error)
        })
    })
  }

  handleErrors(error) {
    this.busy = false

    if (error) {
      this.errors.set(this.extractErrors(error.data))
    }
  }

  extractErrors(data) {
    if (!data || typeof data !== "object") {
      return { error: Form.errorMessage }
    }

    if (data.errors) {
      return { ...data.errors }
    }

    if (data.message) {
      return { error: data.message }
    }

    return { ...data }
  }

  /**
   * Submit form data using TanStack Query mutation
   * @param {Object} mutation - TanStack Query mutation object
   * @param {Object} options - Additional options
   * @returns {Promise}
   */
  mutate(mutation, options = {}) {
    return this.mutateAsync(mutation, options)
  }

  /**
   * Submit form data using TanStack Query mutation (async)
   * @param {Object} mutation - TanStack Query mutation object
   * @param {Object} options - Additional options
   * @returns {Promise}
   */
  mutateAsync(mutation, options = {}) {
    this.startProcessing()
    
    const payload = { ...this._getSerializableData(), ...options.data }
    
    // Handle file uploads if needed
    const processedPayload = hasFiles(payload) && options.transformRequest
      ? options.transformRequest.reduce((data, fn) => fn(data), payload)
      : payload
          
    return mutation.mutateAsync(processedPayload)
      .then((response) => {
        this.finishProcessing()
        return response
      })
      .catch((error) => {
        this.handleErrors(error)
        throw error
      })
  }
}

export default Form
