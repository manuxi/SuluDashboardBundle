// The calculations of the chart tile without React, so that they can be tested with `node --test tests/js/*.test.mjs`.

// palette names a series may use as `color` in the data; the order is the default order of the series
export const PALETTE = ['blue', 'green', 'yellow', 'red', 'gray'];

const HEX_COLOR = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

const toNumber = (value) => {
    if (value === null || value === undefined || value === '' || typeof value === 'boolean') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
};

// the colour of the series at this position: a palette name or a hex colour from the data, else the palette in order
export const seriesColor = (color, index) => {
    if (typeof color === 'string' && PALETTE.includes(color)) {
        return {name: color, hex: null};
    }

    if (typeof color === 'string' && HEX_COLOR.test(color)) {
        return {name: null, hex: color};
    }

    return {name: PALETTE[index % PALETTE.length], hex: null};
};

// Brings the data of a tile into one safe shape. Every kind but "bar" becomes "line";
// values that are not numbers become null (a gap in a line, zero in a bar).
export const normalize = (data) => {
    const source = data && typeof data === 'object' ? data : {};
    const labels = Array.isArray(source.labels) ? source.labels : [];
    const series = (Array.isArray(source.series) ? source.series : [])
        .filter((entry) => entry && typeof entry === 'object')
        .map((entry, index) => ({
            label: entry.label,
            values: labels.map((_, position) => toNumber(Array.isArray(entry.values) ? entry.values[position] : null)),
            color: seriesColor(entry.color, index),
        }));
    const kind = 'bar' === source.kind ? 'bar' : 'line';

    return {
        kind,
        stacked: 'bar' === kind && true === source.stacked && series.length > 1,
        labels,
        series,
        unit: source.unit,
        empty: 0 === labels.length || 0 === series.length,
    };
};

// sum of the series per position; gaps and negative values do not count (stacks only make sense with positive parts)
export const stackTotals = (series, count) => Array.from({length: count}, (_, index) => series.reduce((sum, entry) => {
    const value = entry.values[index];

    return sum + (null !== value && undefined !== value && value > 0 ? value : 0);
}, 0));

// start and end of every series in the stack at one position
export const stackSegments = (series, index) => {
    let top = 0;

    return series.map((entry) => {
        const value = entry.values[index];
        const size = null !== value && undefined !== value && value > 0 ? value : 0;
        const segment = {start: top, end: top + size, value};
        top += size;

        return segment;
    });
};

// the classic "nice numbers" (1, 2, 5 times a power of ten): rounds a range or a step to a pleasant value
export const niceNumber = (value, round) => {
    if (!(value > 0)) {
        return 1;
    }

    const exponent = Math.floor(Math.log10(value));
    const fraction = value / Math.pow(10, exponent);
    let nice;

    if (round) {
        nice = fraction < 1.5 ? 1 : fraction < 3 ? 2 : fraction < 7 ? 5 : 10;
    } else {
        nice = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;
    }

    return nice * Math.pow(10, exponent);
};

const decimalsOf = (step) => {
    const text = String(step);

    if (text.includes('e-')) {
        return parseInt(text.split('e-')[1], 10);
    }

    return text.includes('.') ? text.split('.')[1].length : 0;
};

// Axis from the smallest and the largest value. Zero is always part of the axis (bars must start there, a line that
// does not would exaggerate small changes). `integers` keeps the step a whole number, so counts do not get ticks like 0.5.
export const niceScale = (min, max, maxTicks = 5, integers = false) => {
    const low = Math.min(0, Number.isFinite(min) ? min : 0);
    let high = Math.max(0, Number.isFinite(max) ? max : 0);

    if (low === high) {
        high = 1;
    }

    const range = niceNumber(high - low, false);
    let step = niceNumber(range / Math.max(1, maxTicks - 1), true);

    if (integers && step < 1) {
        step = 1;
    }

    const decimals = decimalsOf(step);
    const round = (value) => Number(value.toFixed(decimals));
    const start = round(Math.floor(low / step) * step);
    const end = round(Math.ceil(high / step) * step);
    const ticks = [];

    for (let value = start; value <= end + step / 2; value = round(value + step)) {
        ticks.push(value);
    }

    return {min: start, max: end, step, ticks, decimals};
};

// How many x labels fit next to each other in the given width.
export const maxLabels = (width, longestLabel) => Math.max(1, Math.floor(width / (longestLabel * 6.5 + 14)));

// Indices of the x labels to show: every n-th one, so that at most `limit` remain and the first one always stays.
export const thinLabels = (count, limit) => {
    const step = Math.max(1, Math.ceil(count / Math.max(1, limit)));
    const indices = [];

    for (let index = 0; index < count; index += step) {
        indices.push(index);
    }

    return indices;
};

// Path of a line through points ({x, y} or null for a gap); a gap starts a new piece.
export const linePath = (points) => {
    let path = '';
    let open = false;

    points.forEach((point) => {
        if (!point) {
            open = false;

            return;
        }

        path += (open ? 'L' : 'M') + point.x.toFixed(1) + ' ' + point.y.toFixed(1);
        open = true;
    });

    return path;
};

// Points that no line reaches (their neighbours are gaps), so they get a dot of their own.
export const isolated = (points, index) => Boolean(points[index]) && !points[index - 1] && !points[index + 1];

// Maps a value of the axis to a position (linear), used for y (inverted by the caller's range).
export const scale = (min, max, from, to) => (value) => (max === min ? from : from + (value - min) / (max - min) * (to - from));
