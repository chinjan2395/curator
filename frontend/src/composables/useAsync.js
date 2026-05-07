import { ref } from 'vue'

export function useAsync(handler) {
  const loading = ref(false)
  const error = ref(null)

  async function execute(...args) {
    loading.value = true
    error.value = null
    try {
      return await handler(...args)
    } catch (err) {
      error.value = err?.response?.data?.message ?? err?.message ?? 'Something went wrong.'
      throw err
    } finally {
      loading.value = false
    }
  }

  return { loading, error, execute }
}
