// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {
    isolated, linePath, maxLabels, niceNumber, niceScale, normalize, scale, seriesColor, stackSegments, stackTotals, thinLabels,
} from '../../src/Resources/js/chartMath.js';

test('nice numbers are 1, 2, 5 or 10 times a power of ten', () => {
    assert.equal(niceNumber(0.7, false), 1);
    assert.equal(niceNumber(130, false), 200);
    assert.equal(niceNumber(1234, false), 2000);
    assert.equal(niceNumber(4.2, true), 5);
    assert.equal(niceNumber(1.2, true), 1);
    assert.equal(niceNumber(0, true), 1);
});

test('the axis starts at zero and ends at a round value above the highest one', () => {
    const scaleOf = niceScale(0, 87);

    assert.equal(scaleOf.min, 0);
    assert.equal(scaleOf.max, 100);
    assert.deepEqual(scaleOf.ticks, [0, 20, 40, 60, 80, 100]);
});

test('a line that never leaves a high level still gets zero on the axis', () => {
    const scaleOf = niceScale(950, 1010);

    assert.equal(scaleOf.min, 0);
    assert.equal(scaleOf.max, 1500);
    assert.ok(scaleOf.ticks.includes(1000));
});

test('negative values extend the axis below zero', () => {
    const scaleOf = niceScale(-30, 70);

    assert.equal(scaleOf.min, -40);
    assert.equal(scaleOf.max, 80);
    assert.ok(scaleOf.ticks.includes(0));
});

test('empty and all-zero data give a usable axis', () => {
    assert.equal(niceScale(0, 0).max, 1);
    assert.deepEqual(niceScale(Infinity, NaN).ticks, niceScale(0, 0).ticks);
});

test('counts do not get fractional ticks, measurements may', () => {
    assert.deepEqual(niceScale(0, 3, 5, true).ticks, [0, 1, 2, 3]);
    assert.deepEqual(niceScale(0, 1, 5, false).ticks, [0, 0.2, 0.4, 0.6, 0.8, 1]);
    assert.equal(niceScale(0, 0.8).step, 0.2);
    assert.deepEqual(niceScale(0, 0.8).ticks, [0, 0.2, 0.4, 0.6, 0.8]);
});

test('labels are thinned to every n-th one and the first stays', () => {
    assert.deepEqual(thinLabels(5, 10), [0, 1, 2, 3, 4]);
    assert.deepEqual(thinLabels(30, 10), [0, 3, 6, 9, 12, 15, 18, 21, 24, 27]);
    assert.deepEqual(thinLabels(90, 7).length <= 7, true);
    assert.equal(thinLabels(90, 7)[0], 0);
    assert.deepEqual(thinLabels(0, 5), []);
    assert.deepEqual(thinLabels(3, 0), [0]);
});

test('the number of labels that fit depends on the width and the longest label', () => {
    assert.equal(maxLabels(400, 6), 7);
    assert.equal(maxLabels(10, 20), 1);
});

test('stack totals add the series per position, gaps and negatives count as zero', () => {
    const series = [{values: [1, 2, null]}, {values: [3, -4, 5]}];

    assert.deepEqual(stackTotals(series, 3), [4, 2, 5]);
});

test('stack segments follow each other from zero', () => {
    const series = [{values: [2]}, {values: [3]}, {values: [null]}, {values: [1]}];

    assert.deepEqual(stackSegments(series, 0).map((segment) => [segment.start, segment.end]), [[0, 2], [2, 5], [5, 5], [5, 6]]);
});

test('a line path restarts after a gap', () => {
    const points = [{x: 0, y: 10}, {x: 10, y: 20}, null, {x: 30, y: 5}];

    assert.equal(linePath(points), 'M0.0 10.0L10.0 20.0M30.0 5.0');
    assert.equal(linePath([null, null]), '');
});

test('a point between two gaps is isolated', () => {
    const points = [{x: 0, y: 0}, null, {x: 2, y: 0}, {x: 3, y: 0}, null, {x: 5, y: 0}];

    assert.deepEqual(points.map((_, index) => isolated(points, index)), [true, false, false, false, false, true]);
});

test('scale maps values linearly and survives an empty domain', () => {
    const y = scale(0, 100, 200, 0);

    assert.equal(y(0), 200);
    assert.equal(y(50), 100);
    assert.equal(y(100), 0);
    assert.equal(scale(5, 5, 10, 20)(5), 10);
});

test('series colours: palette name or hex from the data, else the palette in order', () => {
    assert.deepEqual(seriesColor('red', 0), {name: 'red', hex: null});
    assert.deepEqual(seriesColor('#a1b', 0), {name: null, hex: '#a1b'});
    assert.deepEqual(seriesColor('url(javascript:alert(1))', 1), {name: 'green', hex: null});
    assert.deepEqual(seriesColor(undefined, 0), {name: 'blue', hex: null});
    assert.deepEqual(seriesColor(undefined, 5), {name: 'blue', hex: null});
});

test('data is normalized: values align with labels, bad values become gaps', () => {
    const chart = normalize({
        kind: 'bar',
        stacked: true,
        labels: ['a', 'b', 'c'],
        series: [{label: 'x', values: [1, 'oops', '3']}, {label: 'y', values: [4]}],
        unit: 'ms',
    });

    assert.equal(chart.kind, 'bar');
    assert.equal(chart.stacked, true);
    assert.deepEqual(chart.series[0].values, [1, null, 3]);
    assert.deepEqual(chart.series[1].values, [4, null, null]);
    assert.equal(chart.empty, false);
});

test('stacked needs a bar chart with two series, unknown kinds draw a line', () => {
    assert.equal(normalize({kind: 'bar', stacked: true, labels: ['a'], series: [{values: [1]}]}).stacked, false);
    assert.equal(normalize({kind: 'line', stacked: true, labels: ['a'], series: [{values: [1]}, {values: [2]}]}).stacked, false);
    assert.equal(normalize({kind: 'pie', labels: ['a'], series: [{values: [1]}]}).kind, 'line');
});

test('missing or broken data is empty and never throws', () => {
    assert.equal(normalize(undefined).empty, true);
    assert.equal(normalize(null).empty, true);
    assert.equal(normalize({labels: ['a']}).empty, true);
    assert.equal(normalize({labels: [], series: [{values: []}]}).empty, true);
    assert.equal(normalize({labels: 'x', series: 'y'}).empty, true);
});
