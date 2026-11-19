// The pure logic of the tiles of facts of the tab "System" (Condition, Versions, Environment): what the server hands
// over becomes rows the component can rely on. No Sulu imports, so it runs under node (tests/js/facts.test.mjs).
// data: {status?: 'ok' | 'warning' | 'error', headline?, rows: [{title, value?, text?, severity?}]}; texts as in utils.js tx().

// Sulu's icons for the severity of a row (the same ones the list tile uses)
export const SEVERITY_ICONS = {error: 'su-exclamation-triangle', warning: 'su-exclamation-circle', ok: 'su-check-circle'};

const STATUSES = Object.keys(SEVERITY_ICONS);

const present = (value) => value !== null && value !== undefined && value !== '';

// the data of the tile in a form the component can rely on: a known status or none, rows that are objects with a title
// and a known severity or none
export const normalize = (data) => {
    const source = data && typeof data === 'object' ? data : {};
    const rows = (Array.isArray(source.rows) ? source.rows : [])
        .filter((row) => row && typeof row === 'object' && present(row.title))
        .map((row) => ({
            title: row.title,
            value: present(row.value) ? row.value : null,
            text: present(row.text) ? row.text : null,
            severity: STATUSES.includes(row.severity) ? row.severity : null,
        }));

    return {
        status: STATUSES.includes(source.status) ? source.status : null,
        headline: present(source.headline) ? source.headline : null,
        rows,
        // a row without an icon keeps the place of one, if another row has an icon: the names stay in one line
        hasIcons: rows.some((row) => row.severity !== null),
    };
};
