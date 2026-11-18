// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {listLayout} from '../../src/Resources/js/list.js';

const rows = (count) => Array.from({length: count}, (_, index) => ({title: 'row ' + index}));

test('a list has a table head unless the data says header: false', () => {
    assert.equal(listLayout({rows: rows(2)}, false, 10).hasHeader, true);
    assert.equal(listLayout({rows: rows(2), header: true}, false, 10).hasHeader, true);
    assert.equal(listLayout({rows: rows(2), header: false}, false, 10).hasHeader, false);
});

test('a meta column and links exist only when a row has them', () => {
    const layout = listLayout({rows: [{title: 'a'}, {title: 'b', meta: '8.4'}, {title: 'c', link: {view: 'x'}}]}, false, 10);
    assert.equal(layout.hasMeta, true);
    assert.equal(layout.hasLinks, true);

    const plain = listLayout({rows: rows(3)}, false, 10);
    assert.equal(plain.hasMeta, false);
    assert.equal(plain.hasLinks, false);
});

test('an empty or broken list is an empty list', () => {
    for (const data of [undefined, null, {}, {rows: null}, {rows: 'x'}, 'nonsense']) {
        const layout = listLayout(data, false, 10);
        assert.deepEqual(layout.rows, []);
        assert.deepEqual(layout.visible, []);
        assert.equal(layout.hasMeta, false);
    }
});

test('ten rows are shown, or the limit of the data, until the rest is asked for', () => {
    assert.equal(listLayout({rows: rows(13)}, false, 10).visible.length, 10);
    assert.equal(listLayout({rows: rows(13)}, true, 10).visible.length, 13);
    assert.equal(listLayout({rows: rows(13), limit: 30}, false, 10).visible.length, 13);
    assert.equal(listLayout({rows: rows(13), limit: 3}, false, 10).visible.length, 3);
    assert.equal(listLayout({rows: rows(13), limit: 0}, false, 10).limit, 10);
});
