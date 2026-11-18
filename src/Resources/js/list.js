// What the list tile (tiles/ListTile.js) derives from its data: which rows are shown, whether there are links, a meta
// column and a table head. No Sulu imports, so it runs under node (tests/js/list.test.mjs).

// data: {rows, limit?, header?}. header: false leaves out the table head (rows that are key and value).
export const listLayout = (data, expanded, defaultLimit) => {
    const source = data && typeof data === 'object' ? data : {};
    const rows = Array.isArray(source.rows) ? source.rows : [];
    const limit = source.limit > 0 ? source.limit : defaultLimit;

    return {
        rows,
        limit,
        visible: expanded ? rows : rows.slice(0, limit),
        hasLinks: rows.some((row) => row.link),
        hasMeta: rows.some((row) => row.meta),
        hasHeader: source.header !== false,
    };
};
