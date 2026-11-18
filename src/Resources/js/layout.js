// Layout of the dashboard as plain functions, without React or the DOM, so they can be tested with `node --test tests/js/*.test.mjs`
// (tests/js/layout.test.mjs).
//
// A tile is {key, rank, height, pinned, hidden, place}:
//   place   'main', 'side' or 'auto': the column the tile asks for (see WidgetColumns on the server)
//   rank    sort position inside a column, lowest first; ties keep the order of the input
//   height  measured height in pixels (1 for all tiles when nothing was measured: the columns are then filled by count)
//   pinned  the balance never moves this tile to the other column
//   hidden  does not take any room
//
// The tiles of the row of figures never take part: they stay in their row.

const size = (tile, gap) => (tile.hidden ? 0 : tile.height + gap);

const total = (tiles, gap) => tiles.reduce((sum, tile) => sum + size(tile, gap), 0);

// the position at which a tile with the given rank goes into a column that is sorted by rank
const insertAt = (column, rank) => {
    const index = column.findIndex((tile) => tile.rank > rank);

    return index === -1 ? column.length : index;
};

const insert = (column, tile) => column.splice(insertAt(column, tile.rank), 0, tile);

// stable sort by rank (Array.prototype.sort is stable, but keep the intent visible)
const byRank = (tiles) => tiles.map((tile, index) => ({tile, index}))
    .sort((a, b) => (a.tile.rank - b.tile.rank) || (a.index - b.index))
    .map(({tile}) => tile);

/**
 * Puts the tiles into the two columns.
 *
 * 1. Tiles with place main or side go there, in the order of their rank.
 * 2. Tiles with place auto go one after another into the column that is shorter at that moment.
 * 3. With balance, tiles that are not pinned move from the end of the longer column into the shorter one as long as that
 *    makes the difference smaller: a tile moves only if it is smaller than the difference (then the difference becomes
 *    smaller by twice its size). The difference is therefore at most the size of the smallest tile that may move.
 *    Every step makes the difference smaller, so the loop ends.
 *
 * @param {Array} tiles
 * @param {{gap?: number, balance?: boolean}} options gap = space between two tiles in pixels
 *
 * @return {{main: string[], side: string[]}} keys of the tiles per column, in display order
 */
export function distribute(tiles, {gap = 20, balance = true} = {}) {
    const main = byRank(tiles.filter((tile) => tile.place === 'main'));
    const side = byRank(tiles.filter((tile) => tile.place === 'side'));

    byRank(tiles.filter((tile) => tile.place === 'auto')).forEach((tile) => {
        insert(total(main, gap) <= total(side, gap) ? main : side, tile);
    });

    while (balance) {
        const [longer, shorter] = total(main, gap) >= total(side, gap) ? [main, side] : [side, main];
        const difference = total(longer, gap) - total(shorter, gap);

        // from the end of the column: the bottom tile that is small enough and may move
        let index = longer.length - 1;
        while (index >= 0 && (longer[index].pinned || longer[index].hidden || size(longer[index], gap) >= difference)) {
            index--;
        }

        if (index < 0) {
            break;
        }

        insert(shorter, longer.splice(index, 1)[0]);
    }

    return {main: main.map((tile) => tile.key), side: side.map((tile) => tile.key)};
}

/**
 * Builds the tile list for distribute() from the tiles of the server and the arrangement saved by the user.
 *
 * Without a saved arrangement every tile asks for its own column and may move. With one, the saved tiles keep their
 * column and order and are pinned; tiles that are new since then (not in the saved lists) are placed by the usual rules,
 * after the saved ones.
 *
 * @param {Array<{key: string, column: string, pinned?: boolean}>} widgets in priority order, highest first
 * @param {?{row?: string[], main?: string[], side?: string[], hidden?: string[]}} saved
 * @param {Object<string, number>} heights measured heights per key; unknown keys count as the average (or 1)
 *
 * @return {{row: string[], main: string[], side: string[], hidden: string[]}}
 */
export function arrange(widgets, saved, heights, {gap = 20, balance = true} = {}) {
    const known = heights || {};
    const measured = Object.values(known).filter((height) => height > 0);
    const fallback = measured.length > 0 ? measured.reduce((sum, height) => sum + height, 0) / measured.length : 1;
    const arrangement = saved || {};
    const hidden = (arrangement.hidden || []).filter((key) => widgets.some((widget) => widget.key === key));

    const rowKeys = widgets.filter((widget) => widget.column === 'row').map((widget) => widget.key);
    const savedRow = (arrangement.row || []).filter((key) => rowKeys.includes(key));
    const row = [...savedRow, ...rowKeys.filter((key) => !savedRow.includes(key))];

    const rank = {};
    ['main', 'side'].forEach((column) => {
        (arrangement[column] || []).forEach((key, index) => {
            const widget = widgets.find((candidate) => candidate.key === key);
            if (widget && widget.column !== 'row' && !(key in rank)) {
                rank[key] = {column, index};
            }
        });
    });

    const tiles = widgets.filter((widget) => widget.column !== 'row').map((widget, order) => {
        const fixed = rank[widget.key];

        return {
            key: widget.key,
            rank: fixed ? fixed.index : 1000000 + order,
            height: known[widget.key] > 0 ? known[widget.key] : fallback,
            pinned: !!fixed || !!widget.pinned,
            hidden: hidden.includes(widget.key),
            place: fixed ? fixed.column : ('main' === widget.column || 'side' === widget.column ? widget.column : 'auto'),
        };
    });

    return {row, hidden, ...distribute(tiles, {gap, balance})};
}

/**
 * Do the two arrangements put every tile in the same place?
 */
export function sameArrangement(a, b) {
    return ['row', 'main', 'side'].every((column) => JSON.stringify(a[column]) === JSON.stringify(b[column]));
}

/**
 * Moves an entry inside a list (the result of a drag with react-sortable-hoc), without changing the original.
 */
export function move(list, from, to) {
    const result = list.slice();
    result.splice(to, 0, result.splice(from, 1)[0]);

    return result;
}
