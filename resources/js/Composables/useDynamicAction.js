
export function useDynamicAction(methods = {}) {
    const handleDynamicAction = ({ action, id, nameFuncion }) => {
        if (typeof methods[nameFuncion] === 'function') {
            methods[nameFuncion](id)
        } else {
            console.warn(`Función no encontrada: ${nameFuncion}`)
        }
    }

    return {
        handleDynamicAction
    }
}
