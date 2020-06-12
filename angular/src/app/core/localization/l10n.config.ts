import { L10nConfig, ProviderType, StorageStrategy } from "angular-l10n";

export const l10nConfig: L10nConfig = getL10nConfig();

function getL10nConfig(): L10nConfig {
  let langDE: string = "de";
  let langEN: string = "en";

  // Plentymarkets defaults to German
  let defaultLang: string = langDE;
  let knownLangs: string[] = [langDE, langEN];

  let langInLocalStorage: string = localStorage.getItem("plentymarkets_lang_");
  let resolvedLang: string = langInLocalStorage;

  if (resolvedLang == null) {
    // ask browser for perferred language
    resolvedLang = navigator.language.slice(0, 2).toLocaleLowerCase();

    // as of 1.1.2, no longer (re)defining 'plentymarkets_lang_'
    // because we do not own the plentymarkets-wide language
  }

  if (!knownLangs.includes(resolvedLang)) {
    resolvedLang = defaultLang;
  }

  // default to production settings
  let prefix: string = "assets/lang/locale-";
  let terraComponentsLocalePrefix: string =
    "assets/lang/terra-components/locale-";

  if (process.env.ENV !== "production") {
    // settings for local deployments
    prefix = "src/app/assets/lang/locale-";
    terraComponentsLocalePrefix =
      "node_modules/@plentymarkets/terra-components/app/assets/lang/locale-";
  }

  // build the config json.
  //
  // - using StorageStrategy.Local
  //      * we are matching the Plentymarkets language in local storage
  //      * we should NOT be defining the language for this site at a larger scope,
  //      * our language settings should not persist after use
  //
  // - specify DE before EN
  //      * matches Plentymarkets

  return {
    locale: {
      languages: [
        {
          code: langDE,
          dir: "ltr",
        },
        {
          code: langEN,
          dir: "ltr",
        },
      ],
      language: resolvedLang,
      defaultLocale: { languageCode: resolvedLang},
      storage: StorageStrategy.Local,
    },
    translation: {
      providers: [
        {
          type: ProviderType.Static,
          prefix: prefix,
        },
        {
          type: ProviderType.Static,
          prefix: terraComponentsLocalePrefix,
        },
      ],
      caching: true,
      composedKeySeparator: ".",
      i18nPlural: false,
    },
  };
}
