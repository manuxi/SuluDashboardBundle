// Logic of the tile "Search engine details" without React, so that it can be tested with Node.

// like the tile "Translations": ten entries at first, "Show all" shows everything the server delivers
export const VISIBLE_ENTRIES = 10;

// the list of a ring is open or closed; a click on another ring switches, a click on the open ring closes it
export const nextOpen = (current, key) => (current === key ? null : key);

// the item of the open list, null when the key is unknown (for example after the language changed)
export const openItem = (items, key) => items.find((item) => item.key === key) || null;

// the entries to show, and whether "Show all" has anything to add
export const visibleRows = (rows, showAll, limit = VISIBLE_ENTRIES) => ({
    rows: showAll ? rows : rows.slice(0, limit),
    canShowAll: !showAll && rows.length > limit,
});

// how many entries lack the detail (the ring says 3 / 4: one is missing)
export const missingCount = (item) => (item.missing ? item.missing.count : Math.max(0, item.max - item.value));
