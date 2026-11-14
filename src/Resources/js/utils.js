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

// a text in tile data: plain text, an admin translation key, or {key, parameters} for a key with parameters
export const tx = (value) => (
    value && typeof value === 'object' ? translate(value.key, value.parameters) : translate(String(value))
);
