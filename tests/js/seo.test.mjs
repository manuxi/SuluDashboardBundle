// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {missingCount, nextOpen, openItem, visibleRows, VISIBLE_ENTRIES} from '../../src/Resources/js/seo.js';

const rows = (count) => Array.from({length: count}, (_, index) => ({title: 'row ' + index}));

test('a click on a ring opens its list, on another ring switches, on the open ring closes', () => {
    assert.equal(nextOpen(null, 'seoTitle'), 'seoTitle');
    assert.equal(nextOpen('seoTitle', 'seoDescription'), 'seoDescription');
    assert.equal(nextOpen('seoTitle', 'seoTitle'), null);
});

test('the open list is found by key, an unknown key shows nothing', () => {
    const items = [{key: 'seoTitle'}, {key: 'excerptImage'}];
    assert.equal(openItem(items, 'excerptImage'), items[1]);
    assert.equal(openItem(items, 'gone'), null);
    assert.equal(openItem(items, null), null);
});

test('ten entries at first, "Show all" only when there are more', () => {
    const many = visibleRows(rows(25), false);
    assert.equal(many.rows.length, VISIBLE_ENTRIES);
    assert.equal(many.canShowAll, true);

    assert.equal(visibleRows(rows(25), true).rows.length, 25);
    assert.equal(visibleRows(rows(25), true).canShowAll, false);

    const few = visibleRows(rows(4), false);
    assert.equal(few.rows.length, 4);
    assert.equal(few.canShowAll, false);
});

test('missing count comes from the server, or from value and max', () => {
    assert.equal(missingCount({value: 3, max: 4, missing: {count: 1, rows: []}}), 1);
    assert.equal(missingCount({value: 3, max: 4}), 1);
    assert.equal(missingCount({value: 5, max: 4}), 0);
});
