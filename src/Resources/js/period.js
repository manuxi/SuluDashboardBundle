// The period of a tab of tiles as plain functions, without React or the DOM, so they can be tested with `node --test tests/js/*.test.mjs`
// (tests/js/period.test.mjs).
//
// A period is {days: 30} (the last days up to today) or {from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'} (a custom period, both days
// included). Days are texts, never Date objects: a date picker returns a local date, and a conversion through UTC would
// move it by a day in some time zones.

export const CUSTOM = 'custom';

const DAY = /^(\d{4})-(\d{2})-(\d{2})$/;

const MS_PER_DAY = 86400000;

// 'YYYY-MM-DD' of the local date of a Date
export const toIsoDay = (date) => {
    if (!(date instanceof Date) || isNaN(date)) {
        return null;
    }

    const pad = (value) => String(value).padStart(2, '0');

    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
};

// local Date (midnight) of 'YYYY-MM-DD'; undefined if the text is not a real day
export const fromIsoDay = (value) => {
    const match = DAY.exec(String(value || ''));
    if (!match) {
        return undefined;
    }

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

    return toIsoDay(date) === value ? date : undefined;
};

const utc = (date) => Date.UTC(date.getFullYear(), date.getMonth(), date.getDate());

// number of days from 'from' to 'to', both included (a single day is 1)
export const spanDays = (from, to) => {
    const start = fromIsoDay(from);
    const end = fromIsoDay(to);
    if (!start || !end) {
        return NaN;
    }

    return Math.round((utc(end) - utc(start)) / MS_PER_DAY) + 1;
};

// null if the custom period is allowed, else why not: 'missing' (a day is empty), 'order' (from is after to) or
// 'too_long' (more than maxDays days)
export const validateRange = (from, to, maxDays) => {
    if (!fromIsoDay(from) || !fromIsoDay(to)) {
        return 'missing';
    }

    const span = spanDays(from, to);
    if (span < 1) {
        return 'order';
    }

    return span > maxDays ? 'too_long' : null;
};

// the period as it was saved, if it is still allowed here; else the default
export const resolvePeriod = (saved, config) => {
    const fallback = {days: config.default};

    if (!saved || 'object' !== typeof saved) {
        return fallback;
    }

    if (undefined !== saved.days) {
        return config.days.includes(saved.days) ? {days: saved.days} : fallback;
    }

    return null === validateRange(saved.from, saved.to, config.maxDays) ? {from: saved.from, to: saved.to} : fallback;
};

// value of the select in the toolbar: the number of days, or CUSTOM
export const selectValue = (period) => (undefined !== period.days ? period.days : CUSTOM);

// a text that identifies the period (key of a tile, so it loads again when the period changes)
export const periodKey = (period) => (undefined !== period.days ? String(period.days) + 'd' : period.from + '..' + period.to);

// the days the custom bar starts with: the current custom period, or the days of the current period up to 'today'
// ('YYYY-MM-DD')
export const customStart = (period, today) => {
    if (undefined === period.days) {
        return {from: period.from, to: period.to};
    }

    const end = fromIsoDay(today);
    if (!end) {
        return {from: '', to: ''};
    }

    return {from: toIsoDay(new Date(end.getFullYear(), end.getMonth(), end.getDate() - (period.days - 1))), to: today};
};
