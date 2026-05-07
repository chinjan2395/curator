import { reactive, ref } from 'vue'

export function useForm(initialValues = {}) {
  const form = reactive({ ...initialValues })
  const errors = reactive({})
  const loading = ref(false)

  function setErrors(serverErrors) {
    Object.keys(errors).forEach((k) => delete errors[k])
    Object.assign(errors, serverErrors)
  }

  function clearErrors() {
    Object.keys(errors).forEach((k) => delete errors[k])
  }

  function reset() {
    Object.assign(form, initialValues)
    clearErrors()
  }

  async function submit(handler) {
    loading.value = true
    clearErrors()
    try {
      return await handler(form)
    } catch (err) {
      const serverErrors = err?.response?.data?.errors
      if (serverErrors) setErrors(serverErrors)
      throw err
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, submit, reset, clearErrors, setErrors }
}
