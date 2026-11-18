// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {barValue, normalize, percentText} from '../../src/Resources/js/storage.js';

test('a share is rounded, a tiny one says so, nothing is zero', () => {
    assert.equal(percentText(59.6), '60 %');
    assert.equal(percentText(0.4), '<1 %');
    assert.equal(percentText(0), '0 %');
    assert.equal(percentText(undefined), '0 %');
    assert.equal(percentText(NaN), '0 %');
});

test('a bar never leaves its track', () => {
    assert.equal(barValue({bytes: 50, max: 100}), 50);
    assert.equal(barValue({bytes: 500, max: 100}), 100);
    assert.equal(barValue({bytes: -5, max: 100}), 0);
});

test('without data or without items nothing is measured yet', () => {
    assert.equal(normalize(undefined).status, 'never');
    assert.equal(normalize(null).status, 'never');
    assert.equal(normalize('x').status, 'never');
    assert.equal(normalize({status: 'never', command: 'php bin/adminconsole x'}).command, 'php bin/adminconsole x');
    assert.equal(normalize({items: []}).status, 'never');
    assert.equal(normalize({items: 'broken'}).status, 'never');
});

test('items are made safe: unknown skin, broken numbers and entries that are no items', () => {
    const storage = normalize({
        items: [
            {label: 'sulu_dashboard.system_storage_cache', path: 'var/cache', text: '1.0 GB', bytes: 100, max: 200, percent: 50, skin: 'warning', change: {text: '+1.0 MB', direction: 'up'}},
            {label: {text: 'var/x'}, text: '1 MB', bytes: 'many', max: 0, percent: 250, skin: 'danger', change: {text: '', direction: 'up'}},
            'broken',
            null,
        ],
    });

    assert.equal(storage.status, 'ok');
    assert.equal(storage.items.length, 2);
    assert.deepEqual(storage.items[0].change, {text: '+1.0 MB', direction: 'up'});
    assert.equal(storage.items[0].skin, 'warning');
    assert.equal(storage.items[1].skin, 'progress');
    assert.equal(storage.items[1].bytes, 0);
    assert.equal(storage.items[1].max, 1);
    assert.equal(storage.items[1].percent, 100);
    assert.equal(storage.items[1].path, null);
    assert.equal(storage.items[1].change, null);
});

test('a change in the other direction is shown as down, anything else as up', () => {
    const storage = normalize({items: [{bytes: 1, max: 1, change: {text: '−4 MB', direction: 'down'}}, {bytes: 1, max: 1, change: {text: '+4 MB', direction: 'sideways'}}]});

    assert.equal(storage.items[0].change.direction, 'down');
    assert.equal(storage.items[1].change.direction, 'up');
});

test('the sum, the age, the media and the hints are taken over, wrong shapes are dropped', () => {
    const storage = normalize({
        items: [{bytes: 1, max: 1}],
        total: {text: '1,5 GB', change: {text: '+7 MB', direction: 'up'}},
        age: {key: 'sulu_dashboard.system_age_hours', parameters: {count: 2}},
        previousAge: 'broken',
        stale: true,
        media: {count: 884, text: '516 MB'},
        hints: [{title: {key: 'a'}}, {text: 'no title'}, 'broken'],
    });

    assert.equal(storage.totalText, '1,5 GB');
    assert.deepEqual(storage.totalChange, {text: '+7 MB', direction: 'up'});
    assert.deepEqual(storage.age, {key: 'sulu_dashboard.system_age_hours', parameters: {count: 2}});
    assert.equal(storage.previousAge, null);
    assert.equal(storage.stale, true);
    assert.deepEqual(storage.media, {count: 884, text: '516 MB'});
    assert.equal(storage.hints.length, 1);

    assert.equal(normalize({items: [{bytes: 1, max: 1}], media: {count: 'x', text: 'y'}}).media, null);
    assert.equal(normalize({items: [{bytes: 1, max: 1}], stale: 'yes'}).stale, false);
});

test('a folder named by its own path shows the path once, a speaking name keeps the path as a grey addition', () => {
    const storage = normalize({items: [
        {label: {text: 'var/content-transfer'}, path: 'var/content-transfer', bytes: 1, max: 1},
        {label: 'sulu_dashboard.system_storage_cache', path: 'var/cache', bytes: 1, max: 1},
    ]});

    assert.equal(storage.items[0].path, null);
    assert.deepEqual(storage.items[0].label, {text: 'var/content-transfer'});
    assert.equal(storage.items[1].path, 'var/cache');
});
