// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {arrange, distribute, move, sameArrangement} from '../../src/Resources/js/layout.js';

const tile = (key, height, place = 'auto', extra = {}) => ({key, rank: 0, height, place, pinned: false, hidden: false, ...extra});
const ranked = (...tiles) => tiles.map((entry, index) => ({...entry, rank: index}));

test('an empty dashboard stays empty', () => {
    assert.deepEqual(distribute([]), {main: [], side: []});
});

test('tiles stay in the column they ask for when nothing is clearly longer', () => {
    const result = distribute(ranked(tile('a', 300, 'main'), tile('b', 200, 'side'), tile('c', 150, 'side')));

    assert.deepEqual(result, {main: ['a'], side: ['b', 'c']});
});

test('auto tiles go into the shorter column, one after another', () => {
    const result = distribute(ranked(tile('a', 400, 'main'), tile('b', 100, 'side'), tile('x', 100), tile('y', 100), tile('z', 100)));

    assert.deepEqual(result, {main: ['a'], side: ['b', 'x', 'y', 'z']});
});

test('balance moves tiles from the end of the longer column and keeps the order of the rest', () => {
    // side is 4 x 220 = 880, main is 220: s4 moves (main 440, side 660); s3 is not smaller than the remaining difference
    // of 220, moving it would only swap the columns
    const result = distribute(ranked(
        tile('open', 200, 'main'),
        tile('s1', 200, 'side'),
        tile('s2', 200, 'side'),
        tile('s3', 200, 'side'),
        tile('s4', 200, 'side'),
    ));

    assert.deepEqual(result, {main: ['open', 's4'], side: ['s1', 's2', 's3']});
});

test('a moved tile is inserted by its rank, not at the end', () => {
    const rankOf = {m1: 0, s1: 1, s2: 2, m2: 3, s3: 4, s4: 5, s5: 6};
    const result = distribute(Object.entries(rankOf).map(([key, rank]) => tile(key, 100, key.startsWith('m') ? 'main' : 'side', {rank})));

    assert.equal(result.main.length + result.side.length, 7);
    assert.ok(result.main.some((key) => key.startsWith('s')), 'something moved over');
    for (const column of [result.main, result.side]) {
        const ranks = column.map((key) => rankOf[key]);
        assert.deepEqual(ranks, [...ranks].sort((a, b) => a - b));
    }
});

test('pinned tiles never move to the other column', () => {
    const result = distribute(ranked(
        tile('open', 100, 'main', {pinned: true}),
        tile('activity', 500, 'side', {pinned: true}),
        tile('visitors', 100, 'side', {pinned: true}),
    ));

    assert.deepEqual(result, {main: ['open'], side: ['activity', 'visitors']});
});

test('a tile that is not smaller than the difference does not move (it would make things worse)', () => {
    const result = distribute(ranked(tile('a', 100, 'main'), tile('c', 300, 'side')));

    assert.deepEqual(result, {main: ['a'], side: ['c']});
});

test('the loop ends and the difference does not get larger', () => {
    const heights = [50, 400, 90, 130, 70, 260, 30, 310];
    const tiles = ranked(...heights.map((height, index) => tile('t' + index, height, index % 3 ? 'side' : 'main')));
    const sum = (keys) => keys.reduce((total, key) => total + heights[Number(key.slice(1))] + 20, 0);
    const before = distribute(tiles, {balance: false});
    const after = distribute(tiles, {balance: true});

    assert.ok(Math.abs(sum(after.main) - sum(after.side)) <= Math.abs(sum(before.main) - sum(before.side)));
    assert.equal(after.main.length + after.side.length, heights.length);
});

test('without balance every tile stays where it asked to be', () => {
    const result = distribute(ranked(tile('a', 100, 'main'), tile('b', 100, 'side'), tile('c', 100, 'side'), tile('d', 100, 'side')), {balance: false});

    assert.deepEqual(result, {main: ['a'], side: ['b', 'c', 'd']});
});

test('hidden tiles take no room and are not moved', () => {
    const result = distribute(ranked(
        tile('a', 100, 'main'),
        tile('b', 100, 'side'),
        tile('gone', 900, 'side', {hidden: true}),
        tile('c', 100, 'side'),
    ));

    assert.deepEqual(result, {main: ['a'], side: ['b', 'gone', 'c']});
});

const widgets = [
    {key: 'n1', column: 'row'},
    {key: 'n2', column: 'row'},
    {key: 'open', column: 'main', pinned: true},
    {key: 'seo', column: 'main'},
    {key: 'activity', column: 'side', pinned: true},
    {key: 'visitors', column: 'side'},
    {key: 'reviews', column: 'side'},
    {key: 'free', column: 'auto'},
];

test('arrange without a saved layout: row, columns, nothing hidden', () => {
    const result = arrange(widgets, null, {open: 400, seo: 200, activity: 400, visitors: 200, reviews: 200, free: 200});

    assert.deepEqual(result.row, ['n1', 'n2']);
    assert.deepEqual(result.hidden, []);
    assert.equal(result.main.length + result.side.length, 6);
    assert.ok(result.main.includes('open') && result.side.includes('activity'));
});

test('arrange: a saved layout wins, even where the balance would do something else', () => {
    const saved = {row: ['n2', 'n1'], main: ['seo', 'open'], side: ['activity', 'visitors', 'reviews', 'free'], hidden: ['reviews']};
    const heights = {open: 100, seo: 100, activity: 100, visitors: 100, reviews: 100, free: 100};
    const result = arrange(widgets, saved, heights);

    assert.deepEqual(result.row, ['n2', 'n1']);
    assert.deepEqual(result.main, ['seo', 'open']);
    assert.deepEqual(result.side, ['activity', 'visitors', 'reviews', 'free']);
    assert.deepEqual(result.hidden, ['reviews']);
});

test('arrange: a tile that is new since the layout was saved goes into the shorter column, after the saved ones', () => {
    const saved = {row: ['n1', 'n2'], main: ['open', 'seo'], side: ['activity', 'visitors', 'reviews']};
    const result = arrange(widgets, saved, {open: 400, seo: 200, activity: 200, visitors: 100, reviews: 100, free: 100});

    assert.deepEqual(result.main, ['open', 'seo']);
    assert.deepEqual(result.side, ['activity', 'visitors', 'reviews', 'free']);
});

test('arrange: unknown keys and a tile that is not allowed to leave the row are ignored', () => {
    const saved = {row: ['ghost', 'n2'], main: ['ghost', 'n1', 'open'], side: [], hidden: ['ghost']};
    const result = arrange(widgets, saved, {});

    assert.deepEqual(result.row, ['n2', 'n1']);
    assert.ok(!result.main.includes('ghost') && !result.main.includes('n1'));
    assert.deepEqual(result.hidden, []);
});

test('arrange: a hidden known tile stays hidden, an unknown one is dropped', () => {
    const result = arrange(widgets, {hidden: ['seo', 'gone']}, {});

    assert.deepEqual(result.hidden, ['seo']);
});

test('sameArrangement and move', () => {
    assert.ok(sameArrangement({row: ['a'], main: ['b'], side: []}, {row: ['a'], main: ['b'], side: [], hidden: ['x']}));
    assert.ok(!sameArrangement({row: ['a'], main: ['b'], side: []}, {row: ['a'], main: [], side: ['b']}));
    assert.deepEqual(move(['a', 'b', 'c'], 0, 2), ['b', 'c', 'a']);
    assert.deepEqual(move(['a', 'b', 'c'], 2, 0), ['c', 'a', 'b']);
});
