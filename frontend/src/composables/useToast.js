import { useToastStore } from '@/stores/toast'

export function useToast() {
  const store = useToastStore()

  return {
    success: (message) => store.add({ type: 'success', message }),
    error: (message) => store.add({ type: 'error', message }),
    info: (message) => store.add({ type: 'info', message }),
    warning: (message) => store.add({ type: 'warning', message }),
  }
}
