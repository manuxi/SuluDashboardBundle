// Run with: node --test tests/js   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {customStart, fromIsoDay, periodKey, resolvePeriod, selectValue, spanDays, toIsoDay, validateRange} from '../../src/Resources/js/period.js';

const config = {days: [7, 30, 90, 365], default: 30, maxDays: 366};

test('a local date becomes a day text without moving by a day', () => {
    assert.equal(toIsoDay(new Date(2026, 0, 5, 23, 59)), '2026-01-05');
    assert.equal(toIsoDay(new Date(2026, 11, 31, 0, 0)), '2026-12-31');
    assert.equal(toIsoDay(undefined), null);
    assert.equal(toIsoDay(new Date('nonsense')), null);
});

test('only real days are accepted', () => {
    assert.ok(fromIsoDay('2026-02-28'));
    assert.equal(fromIsoDay('2026-02-30'), undefined);
    assert.equal(fromIsoDay('26-02-01'), undefined);
    assert.equal(fromIsoDay(''), undefined);
    assert.equal(fromIsoDay(null), undefined);
});

test('the span includes both days', () => {
    assert.equal(spanDays('2026-11-18', '2026-11-18'), 1);
    assert.equal(spanDays('2026-11-01', '2026-11-30'), 30);
    assert.equal(spanDays('2026-11-18', '2026-11-17'), 0);
    assert.equal(spanDays('2026-03-20', '2026-03-31'), 12);
    assert.equal(spanDays('2026-03-01', '2026-04-01'), 32);
});

test('a custom period is checked like the API of the log statistics', () => {
    assert.equal(validateRange('2026-11-01', '2026-11-18', 366), null);
    assert.equal(validateRange('2026-11-18', '2026-11-18', 366), null);
    assert.equal(validateRange('', '2026-11-18', 366), 'missing');
    assert.equal(validateRange('2026-11-18', null, 366), 'missing');
    assert.equal(validateRange('2026-11-19', '2026-11-18', 366), 'order');
    assert.equal(validateRange('2025-11-19', '2026-11-18', 366), null, 'exactly 365 days');
    assert.equal(validateRange('2025-11-18', '2026-11-18', 366), null, 'exactly 366 days');
    assert.equal(validateRange('2025-11-17', '2026-11-18', 366), 'too_long', '367 days');
});

test('a saved period is used only while it is still allowed', () => {
    assert.deepEqual(resolvePeriod(undefined, config), {days: 30});
    assert.deepEqual(resolvePeriod(null, config), {days: 30});
    assert.deepEqual(resolvePeriod({days: 90}, config), {days: 90});
    assert.deepEqual(resolvePeriod({days: 14}, config), {days: 30}, 'not in the list any more');
    assert.deepEqual(resolvePeriod({from: '2026-10-01', to: '2026-10-31'}, config), {from: '2026-10-01', to: '2026-10-31'});
    assert.deepEqual(resolvePeriod({from: '2026-10-31', to: '2026-10-01'}, config), {days: 30});
    assert.deepEqual(resolvePeriod({from: 'x', to: 'y'}, config), {days: 30});
    assert.deepEqual(resolvePeriod('7', config), {days: 30});
});

test('the select shows the days or "custom", and a period has a key', () => {
    assert.equal(selectValue({days: 7}), 7);
    assert.equal(selectValue({from: '2026-10-01', to: '2026-10-31'}), 'custom');
    assert.notEqual(periodKey({days: 7}), periodKey({days: 30}));
    assert.notEqual(periodKey({days: 7}), periodKey({from: '2026-10-01', to: '2026-10-07'}));
});

test('the custom bar starts with the current period', () => {
    assert.deepEqual(customStart({days: 7}, '2026-11-18'), {from: '2026-11-12', to: '2026-11-18'});
    assert.deepEqual(customStart({days: 30}, '2026-03-05'), {from: '2026-02-04', to: '2026-03-05'});
    assert.deepEqual(customStart({from: '2026-10-01', to: '2026-10-31'}, '2026-11-18'), {from: '2026-10-01', to: '2026-10-31'});
});
