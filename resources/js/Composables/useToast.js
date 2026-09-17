import { reactive } from 'vue'

const toasts = reactive([])
let toastId = 0

export function useToast() {
    function addToast(message, type = 'success') {
        const id = ++toastId
        toasts.push({ id, message, type })

        setTimeout(() => removeToast(id), 5000)
    }

    function removeToast(id) {
        const index = toasts.findIndex(t => t.id === id)
        if (index !== -1) toasts.splice(index, 1)
    }

    function success(message) { addToast(message, 'success') }
    function error(message) { addToast(message, 'error') }
    function warning(message) { addToast(message, 'warning') }
    function info(message) { addToast(message, 'info') }

    return { toasts, addToast, removeToast, success, error, warning, info }
}

export { toasts }
