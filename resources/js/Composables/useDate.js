// resources/js/composables/useDateLima.js
export const useDate = () => {
  const tz = "America/Lima"

  const getNow = () => {
    return new Date().toLocaleString("sv-SE", { timeZone: tz })
    // sv-SE → formato ISO-like: "2025-09-29 14:05:33"
  }

  const getToday = () => {
    const now = new Date().toLocaleDateString("sv-SE", { timeZone: tz })
    return now // YYYY-MM-DD
  }

  const getMonth = () => {
    const now = new Date().toLocaleDateString("sv-SE", { timeZone: tz })
    return now.substring(0, 7) // YYYY-MM
  }

  const getYear = () => {
    const now = new Date().toLocaleDateString("sv-SE", { timeZone: tz })
    return now.substring(0, 4) // YYYY
  }

  const getDateTime = () => {
    return new Date().toLocaleString("sv-SE", {
      timeZone: tz,
      hour12: false
    }).replace(" ", "T") // "YYYY-MM-DDTHH:mm:ss"
      .replace("T", " ") // => "YYYY-MM-DD HH:mm:ss"
  }

  return {
    getNow,
    getToday,
    getMonth,
    getYear,
    getDateTime,
  }
}
