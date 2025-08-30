// resources/js/composables/useDateLima.js
export const useDate = () => {
  const tz = "America/Lima"

  const getNow = () => {
    return new Date(
      new Intl.DateTimeFormat("en-US", {
        timeZone: tz,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false,
      }).format(new Date())
    )
  }

  const getToday = () => {
    const now = getNow()
    return now.toISOString().substring(0, 10) // YYYY-MM-DD
  }

  const getMonth = () => {
    const now = getNow()
    return now.toISOString().substring(0, 7) // YYYY-MM
  }

  const getYear = () => {
    const now = getNow()
    return String(now.getFullYear()) // 🔹 retorna string
  }

  const getDateTime = () => {
    const now = getNow()
    return now.toISOString().replace("T", " ").substring(0, 19) // YYYY-MM-DD HH:mm:ss
  }

  return {
    getNow,
    getToday,
    getMonth,
    getYear,
    getDateTime,
  }
}
