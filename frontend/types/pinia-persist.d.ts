// Augmentation du module pinia pour typer l'option `persist` des stores.
// Le `export {}` est obligatoire : il transforme ce fichier en MODULE
// pour que `declare module 'pinia'` soit une AUGMENTATION et non un remplacement.
export {}

declare module 'pinia' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  export interface DefineStoreOptionsBase<S, Store> {
    persist?: boolean
  }
}
