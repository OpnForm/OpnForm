class MemoryStorage {
  #store = new Map()

  get length() {
    return this.#store.size
  }

  clear() {
    this.#store.clear()
  }

  getItem(key) {
    const value = this.#store.get(String(key))
    return value === undefined ? null : value
  }

  key(index) {
    return Array.from(this.#store.keys())[index] ?? null
  }

  removeItem(key) {
    this.#store.delete(String(key))
  }

  setItem(key, value) {
    this.#store.set(String(key), String(value))
  }
}

const localStorageDescriptor = Object.getOwnPropertyDescriptor(globalThis, "localStorage")
const hasUsableLocalStorage =
  localStorageDescriptor &&
  "value" in localStorageDescriptor &&
  typeof localStorageDescriptor.value?.getItem === "function"

if (!hasUsableLocalStorage) {
  Object.defineProperty(globalThis, "localStorage", {
    configurable: true,
    enumerable: true,
    value: new MemoryStorage(),
    writable: true,
  })
}
