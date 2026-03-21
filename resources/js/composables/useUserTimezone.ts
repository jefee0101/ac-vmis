export function useUserTimezone() {
  const timezone = (() => {
    if (typeof Intl === 'undefined' || !Intl.DateTimeFormat) return 'UTC'
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
  })()

  return { timezone }
}
