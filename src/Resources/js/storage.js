// The pure logic of the tile "Storage" of the tab "System": what the server hands over becomes data the component can rely
// on. No Sulu imports, so it runs under node (tests/js/storage.test.mjs).

const SKINS = ['progress', 'warning'];

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

const number = (value, fallback) => (typeof value === 'number' && isFinite(value) ? value : fallback);

// {text, direction: 'up' | 'down'} or null
const change = (value) => (isObject(value) && typeof value.text === 'string' && value.text !== ''
    ? {text: value.text, direction: value.direction === 'down' ? 'down' : 'up'}
    : null);

// "12 %", and "<1 %" for a share that is there but too small to round to 1
export const percentText = (percent) => {
    if (!(percent > 0)) {
        return '0 %';
    }

    return percent < 1 ? '<1 %' : Math.round(percent) + ' %';
};

// the length of a bar: never outside 0..max, so a wrong value from the server cannot break the bar
export const barValue = (item) => Math.min(Math.max(0, item.bytes), item.max);

// the data of the tile in a known form: a state, items that are items. Without a single item nothing is measured yet.
export const normalize = (data) => {
    const source = isObject(data) ? data : {};
    const items = (Array.isArray(source.items) ? source.items : []).filter(isObject).map((item) => ({
        label: item.label === undefined || item.label === null ? '' : item.label,
        path: typeof item.path === 'string' && item.path !== '' ? item.path : null,
        text: typeof item.text === 'string' ? item.text : '',
        bytes: Math.max(0, number(item.bytes, 0)),
        max: Math.max(1, number(item.max, 1)),
        percent: Math.min(100, Math.max(0, number(item.percent, 0))),
        skin: SKINS.indexOf(item.skin) === -1 ? 'progress' : item.skin,
        change: change(item.change),
    }));

    const total = isObject(source.total) ? source.total : {};
    const media = isObject(source.media) && typeof source.media.count === 'number' && typeof source.media.text === 'string'
        ? {count: source.media.count, text: source.media.text}
        : null;

    return {
        status: items.length > 0 ? 'ok' : 'never',
        command: typeof source.command === 'string' ? source.command : '',
        totalText: typeof total.text === 'string' ? total.text : '',
        totalChange: change(total.change),
        age: isObject(source.age) ? source.age : null,
        previousAge: isObject(source.previousAge) ? source.previousAge : null,
        stale: source.stale === true,
        items,
        media,
        hints: (Array.isArray(source.hints) ? source.hints : []).filter((hint) => isObject(hint) && hint.title),
    };
};
