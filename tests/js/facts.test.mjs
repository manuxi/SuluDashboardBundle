// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {normalize, SEVERITY_ICONS} from '../../src/Resources/js/facts.js';

test('Sulu icons exist for ok, warning and error', () => {
    assert.deepEqual(Object.keys(SEVERITY_ICONS).sort(), ['error', 'ok', 'warning']);
    assert.equal(SEVERITY_ICONS.ok, 'su-check-circle');
});

test('without data there is no status, no headline and no row', () => {
    for (const data of [undefined, null, 'x', 5, {}, {rows: 'x'}]) {
        const facts = normalize(data);
        assert.equal(facts.status, null);
        assert.equal(facts.headline, null);
        assert.deepEqual(facts.rows, []);
        assert.equal(facts.hasIcons, false);
    }
});

test('rows keep title, value, text and a known severity; the rest is dropped', () => {
    const facts = normalize({
        status: 'warning',
        headline: 'sulu_dashboard.system_attention',
        rows: [
            {title: 'PHP', value: {text: '8.4.1'}, text: 'grey', severity: 'ok'},
            {title: 'Symfony', value: '', text: null, severity: 'bogus'},
            {value: 'no title'},
            null,
            'string',
        ],
    });

    assert.equal(facts.status, 'warning');
    assert.equal(facts.headline, 'sulu_dashboard.system_attention');
    assert.deepEqual(facts.rows, [
        {title: 'PHP', value: {text: '8.4.1'}, text: 'grey', severity: 'ok'},
        {title: 'Symfony', value: null, text: null, severity: null},
    ]);
});

test('an unknown status is none', () => {
    assert.equal(normalize({status: 'purple', rows: []}).status, null);
});

test('rows without an icon keep its place only if another row has one', () => {
    assert.equal(normalize({rows: [{title: 'a'}, {title: 'b'}]}).hasIcons, false);
    assert.equal(normalize({rows: [{title: 'a'}, {title: 'b', severity: 'warning'}]}).hasIcons, true);
});
