export type ThemeMode = 'light' | 'dark'

const THEME_KEY = 'ac-vmis-theme-mode'
const THEME_OPTIONS: Array<{ value: ThemeMode; label: string }> = [
  { value: 'light', label: 'Light' },
  { value: 'dark', label: 'Dark' },
]

function normalizeTheme(value: string | null | undefined): ThemeMode {
  if (value === 'dark' || value === 'blue') return 'dark'
  return 'light'
}

export function getStoredTheme(): ThemeMode {
  if (typeof window === 'undefined') return 'light'
  return normalizeTheme(window.localStorage.getItem(THEME_KEY))
}

export function applyTheme(mode: ThemeMode) {
  if (typeof document === 'undefined') return
  const root = document.documentElement

  root.classList.forEach((className) => {
    if (className.startsWith('theme-')) {
      root.classList.remove(className)
    }
  })
  if (mode === 'dark') root.classList.add('theme-dark')
  root.setAttribute('data-theme', mode)
}

export function initTheme() {
  applyTheme(getStoredTheme())
}

export function setStoredTheme(mode: ThemeMode) {
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(THEME_KEY, mode)
  }
  applyTheme(mode)
}

export function useTheme() {
  return {
    themeOptions: THEME_OPTIONS,
    getTheme: getStoredTheme,
    setTheme: setStoredTheme,
  }
}
