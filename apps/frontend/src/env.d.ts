/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string
  readonly VITE_APP_VERSION: string
  readonly DEV: boolean
  readonly PROD: boolean
  readonly BASE_URL: string
  readonly MODE: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

// Allow importing CSS files
declare module '*.css' {
  const content: string
  export default content
}

// Allow importing font packages
declare module '@fontsource-variable/inter'
declare module '@fontsource-variable/jetbrains-mono'
