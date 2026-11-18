// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import {
    METRIC_NAMES,
    chartData,
    createStatisticsStore,
    dailyRows,
    figure,
    hasNoData,
    missingFields,
    periodRange,
    rankingRows,
    statisticsUrl,
    todayIn,
    widgetKey,
} from '../../src/Resources/js/logStats.js';

// a fixed answer of the API of the log statistics (amoifr/sulu-log-stats-bundle ~0.1), five days
const answer = () => JSON.parse(readFileSync(new URL('../fixtures/log-stats-response.json', import.meta.url), 'utf8'));

test('the fixed answer has everything every tile expects', () => {
    for (const metric of METRIC_NAMES) {
        assert.deepEqual(missingFields(answer(), metric), [], metric);
    }
});

test('every metric of the tiles is known to the server too', () => {
    const php = readFileSync(new URL('../../src/Integration/LogStats/LogStatsTileWidget.php', import.meta.url), 'utf8');
    const keys = [...php.matchAll(/^\s+'([a-z_]+)' => \['sulu_dashboard\./gm)].map((match) => match[1]);

    assert.deepEqual([...keys].sort(), [...METRIC_NAMES].sort());
    assert.equal(widgetKey('chart_views'), 'sulu_visitors_chart_views');
});

test('a missing field is named, not shown as zero', () => {
    const data = answer();
    delete data.totals.pageViews;
    delete data.days[2].visitors;
    data.topNotFound = {};

    assert.deepEqual(missingFields(data, 'views'), ['totals.pageViews']);
    assert.deepEqual(missingFields(data, 'chart_visitors'), ['days[].visitors']);
    assert.deepEqual(missingFields(data, 'top_not_found'), ['topNotFound']);
    // other tiles do not care
    assert.deepEqual(missingFields(data, 'chart_views'), []);
});

test('an answer that is not an object, or a renamed list, is reported', () => {
    assert.deepEqual(missingFields(null, 'views'), ['response']);
    assert.deepEqual(missingFields('<html>', 'daily'), ['response']);

    const data = answer();
    data.days = data.days.map(({pageViews, ...rest}) => ({...rest, views: pageViews}));
    assert.deepEqual(missingFields(data, 'daily'), ['days[].pageViews']);
});

test('responseTime may be null (no durations in the logs): that is an answer, not a missing field', () => {
    const data = answer();
    data.responseTime = null;
    data.days.forEach((day) => { day.responseTime = null; });

    assert.deepEqual(missingFields(data, 'response_time'), []);
    assert.deepEqual(chartData('chart_response', data), {empty: 'durations'});
    assert.deepEqual(figure('response_time', data), {kind: 'ms', value: null, parts: []});
});

// what Sulu's Requester does with every answer (sulu-admin-bundle/services/Requester/Requester.js,
// transformResponseObject): null becomes undefined, in objects and in lists of objects
const asSuluRequester = (value) => {
    if (Array.isArray(value)) {
        return value.map((item) => (null !== item && 'object' === typeof item ? asSuluRequester(item) : item));
    }

    return Object.fromEntries(Object.entries(value).map(([key, item]) => [
        key,
        null === item ? undefined : ('object' === typeof item ? asSuluRequester(item) : item),
    ]));
};

test('the answer as the tiles get it from Sulu\'s Requester has everything every tile expects', () => {
    for (const metric of METRIC_NAMES) {
        assert.deepEqual(missingFields(asSuluRequester(answer()), metric), [], metric);
    }

    const data = answer();
    data.responseTime = null;
    data.days.forEach((day) => { day.responseTime = null; });
    const received = asSuluRequester(data);

    assert.equal(received.responseTime, undefined);
    for (const metric of METRIC_NAMES) {
        assert.deepEqual(missingFields(received, metric), [], metric);
    }
    assert.deepEqual(chartData('chart_response', received), {empty: 'durations'});
    assert.deepEqual(figure('response_time', received), {kind: 'ms', value: null, parts: []});
});

test('a responseTime that is neither an object nor empty is reported', () => {
    const data = answer();
    data.responseTime = 182;

    assert.deepEqual(missingFields(data, 'response_time'), ['responseTime']);
});

test('figures', () => {
    const data = answer();

    assert.equal(figure('views', data).value, data.totals.pageViews);
    assert.equal(figure('visitors', data).value, data.totals.visitors);
    assert.deepEqual(figure('server_errors', data).value, 23);
    assert.equal(figure('client_errors', data).parts[0].value, Math.round(figure('client_errors', data).value / data.totals.requests * 1000) / 10);
    assert.equal(figure('bots', data).value, Math.round(230 / 5920 * 1000) / 10);
    assert.deepEqual(figure('response_time', data).parts[0].value, {boundMs: 500, beyond: false});
    assert.equal(figure('bytes', data).kind, 'bytes');
});

test('charts', () => {
    const data = answer();
    const views = chartData('chart_views', data);

    assert.equal(views.kind, 'line');
    assert.equal(views.labels.length, 5);
    assert.deepEqual(views.series[0].values, [940, 1050, 980, 1120, 590]);

    const status = chartData('chart_status', data);
    assert.equal(status.stacked, true);
    assert.deepEqual(status.series.map((entry) => entry.color), ['green', 'blue', 'yellow', 'red']);
    assert.deepEqual(status.series[1].values, [70, 80, 76, 90, 40]);

    const hours = chartData('chart_hours', data);
    assert.equal(hours.labels.length, 24);
    assert.equal(hours.labels[7], '7 h');

    const response = chartData('chart_response', data);
    assert.deepEqual(response.series[0].values, [182, 175, 211, 168, null]);
    assert.deepEqual(response.series[1].values, [500, 500, 750, 300, null]);
    assert.equal(response.unit, 'ms');
    assert.equal(chartData('top_pages', data), null);
});

test('lists and the table of days', () => {
    const data = answer();
    const rows = rankingRows('top_pages', data);

    assert.equal(rows.length, 3);
    assert.equal(rows[0].title, '/');
    assert.equal(rows[0].meta, (1520).toLocaleString());
    assert.deepEqual(rankingRows('top_errors', data).map((row) => row.title), ['/search']);

    const days = dailyRows(data);
    assert.equal(days.length, 5);
    assert.equal(days[0].day, '2026-11-14'); // newest first
    assert.equal(days[0].averageMs, null);
    assert.equal(days[1].serverError, 10);
});

test('a period without any request is recognised', () => {
    const data = answer();
    assert.equal(hasNoData(data), false);

    data.totals.requests = 0;
    data.days.forEach((day) => { day.requests = 0; });
    assert.equal(hasNoData(data), true);
});

test('the period becomes one address, counted in the time zone of the statistics', () => {
    // 23:30 on 13 Nov in UTC is already the 14th in Berlin
    const now = new Date('2026-11-13T23:30:00Z');

    assert.equal(todayIn('Europe/Berlin', now), '2026-11-14');
    assert.equal(todayIn('UTC', now), '2026-11-13');
    assert.deepEqual(periodRange({days: 7}, 'Europe/Berlin', now), {from: '2026-11-08', to: '2026-11-14'});
    assert.deepEqual(periodRange({from: '2026-10-01', to: '2026-10-31'}, 'UTC', now), {from: '2026-10-01', to: '2026-10-31'});
    assert.equal(statisticsUrl('/admin/api/log-stats', {days: 30}, 'UTC', now), '/admin/api/log-stats?from=2026-10-15&to=2026-11-13');
    assert.equal(statisticsUrl('/x?a=1', {days: 1}, 'UTC', now), '/x?a=1&from=2026-11-13&to=2026-11-13');
});

test('the store asks once per address, for all tiles', async () => {
    let asked = 0;
    const store = createStatisticsStore({request: async (url) => { asked++; return {url}; }, now: () => 0});

    const [a, b, c] = await Promise.all([store.load('/a'), store.load('/a'), store.load('/a')]);
    await store.load('/b');

    assert.equal(asked, 2);
    assert.equal(a, b);
    assert.equal(b, c);
});

test('the store forgets a failed request and an old answer', async () => {
    let asked = 0;
    let time = 0;
    let fail = true;
    const store = createStatisticsStore({
        request: async () => { asked++; if (fail) { throw new Error('down'); } return {n: asked}; },
        ttl: 1000,
        now: () => time,
    });

    await assert.rejects(store.load('/a'));
    fail = false;
    assert.deepEqual(await store.load('/a'), {n: 2}); // asks again

    time = 500;
    await store.load('/a');
    assert.equal(asked, 2); // still fresh

    time = 1500;
    assert.deepEqual(await store.load('/a'), {n: 3}); // expired
});
