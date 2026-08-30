const configuredApiUrl = import.meta.env.VITE_API_BASE_URL?.trim()

// The compiled module lives in <deployment>/assets, so this resolves to
// <deployment>/backend whether the app is hosted at / or a subdirectory.
export const API_BASE_URL = configuredApiUrl && configuredApiUrl !== 'auto'
  ? configuredApiUrl.replace(/\/$/, '')
  : new URL(/* @vite-ignore */ '../backend', import.meta.url).href.replace(/\/$/, '')
