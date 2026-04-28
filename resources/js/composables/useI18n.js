import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const defaultLocales = ['pt', 'en', 'es'];

function translationValue(translations, path) {
    return path.split('.').reduce((carry, key) => carry?.[key], translations);
}

function interpolatePlaceholders(value, replacements = {}) {
    if (typeof value !== 'string') {
        return value;
    }

    return Object.entries(replacements).reduce((carry, [key, replacement]) => {
        return carry.replaceAll(`:${key}`, String(replacement));
    }, value);
}

export function useI18n() {
    const page = usePage();

    const translations = computed(() => page.props.translations ?? {});
    const locale = computed(() => {
        if (typeof page.props.locale === 'string' && page.props.locale !== '') {
            return page.props.locale;
        }

        return document.documentElement.lang.slice(0, 2) || 'en';
    });
    const availableLocales = computed(() => {
        if (Array.isArray(page.props.availableLocales) && page.props.availableLocales.length > 0) {
            return page.props.availableLocales;
        }

        return defaultLocales;
    });

    function t(path, fallback, replacements = {}) {
        if (fallback && typeof fallback === 'object' && ! Array.isArray(fallback)) {
            replacements = fallback;
            fallback = undefined;
        }

        const value = translationValue(translations.value, path);

        return interpolatePlaceholders(value ?? fallback ?? path, replacements);
    }

    return {
        availableLocales,
        locale,
        t,
    };
}
