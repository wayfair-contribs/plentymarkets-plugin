import {
    L10nConfig,
    ProviderType,
    StorageStrategy
} from 'angular-l10n';

export const l10nConfig:L10nConfig = getL10nConfig();

function getL10nConfig():L10nConfig
{
    let langInLocalStorage:string = localStorage.getItem('plentymarkets_lang_');
    let lang:string = null;

    if(langInLocalStorage !== null)
    {
        lang = langInLocalStorage;
    }
    else
    {
        lang = navigator.language.slice(0, 2).toLocaleLowerCase();

        if(lang !== 'de' && lang !== 'en')
        {
            lang = 'en';
        }

        localStorage.setItem('plentymarkets_lang_', lang);
    }

    let prefix:string = null;
    let terraComponentsLocalePrefix:string = null;

    // Definitions for i18n
    if(process.env.ENV === 'production')
    {
        prefix = 'assets/lang/locale-';
        terraComponentsLocalePrefix = 'assets/lang/terra-components/locale-';
    }
    else
    {
        prefix = 'src/app/assets/lang/locale-';
        terraComponentsLocalePrefix = 'node_modules/@plentymarkets/terra-components/app/assets/lang/locale-';
    }

    return {
        locale:      {
            languages: [
                {
                    code: 'en',
                    dir:  'ltr'
                },
                {
                    code: 'de',
                    dir:  'ltr'
                }
            ],
            language:  lang,
            defaultLocale: { languageCode: lang},
            storage:   StorageStrategy.Cookie
        },
        translation: {
            providers:            [
                {
                    type:   ProviderType.Static,
                    prefix: prefix
                },
                {
                    type:   ProviderType.Static,
                    prefix: terraComponentsLocalePrefix
                }
            ],
            caching:              true,
            composedKeySeparator: '.',
            i18nPlural:           false
        }
    };
}
