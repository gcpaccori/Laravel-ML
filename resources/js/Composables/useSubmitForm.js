import { ref } from 'vue'
import axios from 'axios'
 import { ElMessage } from 'element-plus'

export default function useSubmitForm() {
  const loading = ref(false)
  const progress = ref(null)
  const errors = ref({})

  const submitForm = async ({ url, method, data, emit, onSuccess }) => {
    loading.value = true
    progress.value = null
    errors.value = {}

    try {
      const response = await axios[method](url, data, {
        onUploadProgress: (event) => {
          if (event.lengthComputable) {
            const percent = Math.round((event.loaded * 100) / event.total)
            progress.value = { percentage: percent }
          }
        },
      })

      emit && emit('saved', response.data.data)
      onSuccess && onSuccess(response)
    } catch (error) {
      if (error.response) {
        if (error.response.status === 422) {
          errors.value = error.response.data.errors
        } else {
            // ✅ Alerta de error de servidor
            ElMessage({
                message: error.response.data.message || 'Ocurrió un error en el servidor',
                type: 'error',
            });
        }
      } else {
        // ✅ Error inesperado (red, timeout, etc.)
            ElMessage({
                message: 'Error de conexión o inesperado',
                type: 'error',
            });
      }
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    progress,
    errors,
    submitForm,
  }
}
