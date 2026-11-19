// The title of a template as Sulu's template select shows it ("Blog"), in the admin language of the user, or null when
// the template is unknown. templateTitles: {pages: {template: {locale: title}}, articles: {...}} (view option of the server).
export const templateTitle = (templateTitles, resourceKey, template, userLocale) => {
    const titles = ((templateTitles || {})[resourceKey] || {})[template] || {};

    return titles[userLocale] || Object.values(titles)[0] || null;
};
