// The pure logic of the tile "Security vulnerabilities" of the tab "System": what the server hands over becomes the
// rows of the tables. No Sulu imports, so it runs under node (tests/js/system.test.mjs).

const SEVERITY_KEYS = {
    critical: 'sulu_dashboard.system_severity_critical',
    high: 'sulu_dashboard.system_severity_high',
    medium: 'sulu_dashboard.system_severity_medium',
    low: 'sulu_dashboard.system_severity_low',
};

const STATES = ['never', 'unavailable', 'ok', 'vulnerable'];

// only a web link becomes a link: whatever else the data holds ("javascript:", "data:") is shown as text
export const isWebLink = (url) => typeof url === 'string' && url.length <= 500 && /^https?:\/\/[^\s<>"']+$/i.test(url);

// translation key of a severity; null for an unknown one (composer often delivers none)
export const severityKey = (severity) => (Object.prototype.hasOwnProperty.call(SEVERITY_KEYS, severity) ? SEVERITY_KEYS[severity] : null);

// the name of an advisory: the CVE if there is one, otherwise the ID of the advisory database
export const advisoryName = (advisory) => advisory.cve || advisory.id || null;

// "2026-10-18T14:45:00+00:00" -> a Date at midnight UTC of that day; null if the text is no date. The day is the one the
// source states, so it is shown in UTC and never moves by a day in another time zone.
export const reportedDay = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
    if (!match) {
        return null;
    }

    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

    return isNaN(date) ? null : date;
};

const listOf = (value) => (Array.isArray(value) ? value.filter((entry) => entry && typeof entry === 'object') : []);

// the data of the tile in a form the component can rely on: a known state, lists that are lists. A state "vulnerable"
// without a single entry is "ok", an unknown state counts as "never" (nothing is known yet).
export const normalize = (data) => {
    const source = data && typeof data === 'object' ? data : {};
    const advisories = listOf(source.advisories);
    const abandoned = listOf(source.abandoned);
    let state = STATES.includes(source.status) ? source.status : 'never';
    if ('vulnerable' === state && advisories.length === 0) {
        state = 'ok';
    }

    return {
        state,
        advisories: 'vulnerable' === state ? advisories : [],
        abandoned,
        reason: typeof source.reason === 'string' ? source.reason : null,
        command: typeof source.command === 'string' ? source.command : '',
    };
};

// rows shown before "Show all"
export const visibleRows = (rows, expanded, limit) => (expanded ? rows : rows.slice(0, limit));
