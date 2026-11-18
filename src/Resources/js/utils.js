import {translate} from 'sulu-admin-bundle/utils';

// same meaning as in Sulu's lists (AbstractTableAdapter): green = live version exists, orange = unpublished changes
export const INDICATOR = {
    published: {published: true, draft: false},
    draft: {published: true, draft: true},
    unpublished: {published: false, draft: true},
    review: {published: false, draft: true},
    review_draft: {published: true, draft: true},
};

export const toDate = (value) => new Date(String(value).replace(' ', 'T'));

export const formatDate = (value, timeStyle = 'short', dateStyle = 'medium') => {
    const date = toDate(value);

    return isNaN(date) ? '' : date.toLocaleString(undefined, {dateStyle, timeStyle});
};

export const percent = (part, total) => (total > 0 ? Math.round(part / total * 100) : 0);

// a link in tile data is a Sulu view with attributes, never a URL
export const navigate = (router, link) => {
    if (link && link.view) {
        router.navigate(link.view, link.attributes || {});
    }
};

// something that looks like a translation key ("sulu_dashboard.pages", "app.my_tile"): dot-separated words without spaces.
// Sulu's translate() returns an unknown key unchanged but logs a warning, so plain texts ("Hall 3", "Berlin") are not
// sent through it at all. A key has a letter: a number written with a thousands separator ("1.204") is not one.
const KEY_PATTERN = /^(?=.*[A-Za-z_])[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/;

// a text in tile data: plain text, an admin translation key, or {key, parameters} for a key with parameters
export const tx = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'object') {
        return translate(value.key, value.parameters);
    }

    const text = String(value);

    return KEY_PATTERN.test(text) ? translate(text) : text;
};
