import { ref } from 'vue'

export function useConfirm() {
  const pending = ref(null)

  function confirm(options = {}) {
    return new Promise((resolve) => {
      pending.value = {
        title: options.title ?? 'Are you sure?',
        message: options.message ?? '',
        confirmLabel: options.confirmLabel ?? 'Confirm',
        cancelLabel: options.cancelLabel ?? 'Cancel',
        variant: options.variant ?? 'danger',
        resolve,
      }
    })
  }

  function accept() {
    pending.value?.resolve(true)
    pending.value = null
  }

  function cancel() {
    pending.value?.resolve(false)
    pending.value = null
  }

  return { pending, confirm, accept, cancel }
}
