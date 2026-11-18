// The tiles of the tab "Visitors" (group "visitors", only with amoifr/sulu-log-stats-bundle) as plain functions, without
// React and without Sulu, so that they can be tested with `node --test tests/js` (tests/js/logStats.test.mjs).
//
// All tiles read the same answer of the admin API of the log statistics, GET /admin/api/log-stats?from=&to=. The shape
// they rely on (tested with tests/fixtures/log-stats-response.json):
//   {timezone, from, to,
//    totals: {requests, pageViews, botRequests, bytes, visitors, statusClasses: {1..5}},
//    days: [{day, requests, pageViews, botRequests, bytes, visitors, statusClasses, responseTime}],
//    pageViewsByHourOfDay: [24 numbers],
//    responseTime: {requests, averageMs, p95: {boundMs, beyond}, histogram} | null,
//    topPages, topNotFound, topServerErrors: [{path, count}]}
// Reading is tolerant: a missing number counts as 0, a missing list as empty. But missingFields() names what the tile
// expected and did not find, and the tile says so instead of showing zeros that look real.
import {fromIsoDay, toIsoDay} from './period.js';

// the metrics: one tile each (key sulu_visitors_<metric>), with the fields of the answer it needs
export const METRICS = {
    views: ['totals.pageViews:number'],
    visitors: ['totals.visitors:number'],
    requests: ['totals.requests:number'],
    bots: ['totals.botRequests:number', 'totals.requests:number'],
    client_errors: ['totals.statusClasses.4:number', 'totals.requests:number'],
    server_errors: ['totals.statusClasses.5:number', 'totals.requests:number'],
    response_time: ['responseTime:present'],
    bytes: ['totals.bytes:number'],
    chart_views: ['days:array', 'days[].day:present', 'days[].pageViews:number'],
    chart_visitors: ['days:array', 'days[].day:present', 'days[].visitors:number'],
    chart_status: ['days:array', 'days[].day:present', 'days[].statusClasses:object'],
    chart_hours: ['pageViewsByHourOfDay:array'],
    chart_response: ['days:array', 'days[].day:present', 'days[].responseTime:present'],
    top_pages: ['topPages:array', 'topPages[].path:present', 'topPages[].count:number'],
    top_not_found: ['topNotFound:array', 'topNotFound[].path:present', 'topNotFound[].count:number'],
    top_errors: ['topServerErrors:array', 'topServerErrors[].path:present', 'topServerErrors[].count:number'],
    daily: ['days:array', 'days[].day:present', 'days[].pageViews:number', 'days[].visitors:number', 'days[].statusClasses:object', 'days[].responseTime:present'],
};

export const METRIC_NAMES = Object.keys(METRICS);

// the key of the tile of a metric (the same on the server, src/Integration/LogStats/LogStatsTileWidget.php)
export const widgetKey = (metric) => 'sulu_visitors_' + metric;

// --- reading the answer ---

const isObject = (value) => null !== value && 'object' === typeof value && !Array.isArray(value);

const num = (value) => (Number.isFinite(value) ? value : 0);

const list = (value) => (Array.isArray(value) ? value : []);

const statusClass = (statusClasses, name) => num((isObject(statusClasses) ? statusClasses : {})[name]);

const matches = (value, type) => {
    switch (type) {
        case 'number': return Number.isFinite(value);
        case 'array': return Array.isArray(value);
        case 'object': return isObject(value);
        default: return undefined !== value;
    }
};

// 'totals.pageViews:number' checked against the answer: pushes the path if the field is missing or of the wrong type
const check = (node, segments, type, path, missing) => {
    const [head, ...rest] = segments;
    const value = isObject(node) ? node[head] : undefined;
    const here = path + (path ? '.' : '') + head;

    if (0 === rest.length) {
        if (!matches(value, type)) {
            missing.push(here);
        }

        return;
    }

    check(value, rest, type, here, missing);
};

// The fields a tile expects and does not find in the answer, once each ('days[].pageViews'); [] if all are there.
export const missingFields = (statistics, metric) => {
    const rules = METRICS[metric] || [];
    const missing = [];

    if (!isObject(statistics)) {
        return ['response'];
    }

    rules.forEach((rule) => {
        const [path, type] = rule.split(':');
        const found = [];

        if (path.includes('[]')) {
            // 'days[].x': each element of the list is checked for 'x'
            const [listPath, field] = path.split('[].');
            list(statistics[listPath]).forEach((item) => {
                if (!matches(isObject(item) ? item[field] : undefined, type)) {
                    found.push(listPath + '[].' + field);
                }
            });
        } else {
            check(statistics, path.split('.'), type, '', found);
        }

        found.forEach((name) => !missing.includes(name) && missing.push(name));
    });

    return missing;
};

// no request at all in the period: the import has not read these days (yet)
export const hasNoData = (statistics) => isObject(statistics)
    && 0 === num((isObject(statistics.totals) ? statistics.totals : {}).requests)
    && !list(statistics.days).some((day) => isObject(day) && num(day.requests) > 0);

// --- period and request ---

// 'YYYY-MM-DD' of today in a time zone (the one of the log statistics: its days end at midnight there); the local day if
// the zone is unknown
export const todayIn = (timezone, now = new Date()) => {
    try {
        const parts = new Intl.DateTimeFormat('en-CA', {timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit'})
            .formatToParts(now)
            .reduce((all, part) => ({...all, [part.type]: part.value}), {});

        return parts.year + '-' + parts.month + '-' + parts.day;
    } catch (error) {
        return toIsoDay(now);
    }
};

const shiftDay = (day, days) => {
    const date = fromIsoDay(day);

    return date ? toIsoDay(new Date(date.getFullYear(), date.getMonth(), date.getDate() + days)) : day;
};

// {from, to} (both included) of a period: {days} counts back from today in the time zone of the statistics
export const periodRange = (period, timezone, now = new Date()) => {
    if (period && undefined !== period.days) {
        const to = todayIn(timezone, now);

        return {from: shiftDay(to, -(period.days - 1)), to};
    }

    if (period && period.from && period.to) {
        return {from: period.from, to: period.to};
    }

    const to = todayIn(timezone, now);

    return {from: shiftDay(to, -29), to};
};

// the address of the answer for a period; also the key of the shared store
export const statisticsUrl = (apiUrl, period, timezone, now = new Date()) => {
    const {from, to} = periodRange(period, timezone, now);

    return apiUrl + (apiUrl.includes('?') ? '&' : '?') + 'from=' + from + '&to=' + to;
};

// One request per address, shared by all tiles: the promise is kept, so ten tiles that mount together ask once. An
// answer is reused for `ttl` milliseconds (the import runs once a day, but a user who opens the tab again after a while
// expects fresh numbers); a failed request is forgotten at once so the next try asks again.
export const createStatisticsStore = ({request, ttl = 60000, now = () => Date.now()}) => {
    const entries = new Map();

    return {
        load(url) {
            const entry = entries.get(url);
            if (entry && now() - entry.at < ttl) {
                return entry.promise;
            }

            const promise = Promise.resolve(request(url));
            entries.set(url, {promise, at: now()});
            promise.catch(() => entries.get(url) && entries.get(url).promise === promise && entries.delete(url));

            return promise;
        },
        clear() {
            entries.clear();
        },
    };
};

// --- values of the tiles ---

const share = (part, total) => (total > 0 ? Math.round(part / total * 1000) / 10 : null);

const p95 = (statistics) => {
    const value = isObject(statistics.responseTime) ? statistics.responseTime.p95 : null;

    return isObject(value) ? {boundMs: num(value.boundMs), beyond: true === value.beyond} : null;
};

// A figure: {kind: 'count'|'percent'|'ms'|'bytes'|'bound', value, parts: [{label, kind, value}]}. value null = no figure
// ("–"). 'bound' is a bucket bound of the response times ("up to 300 ms", or "over 10,000 ms" with beyond).
export const figure = (metric, statistics) => {
    const totals = isObject(statistics.totals) ? statistics.totals : {};
    const requests = num(totals.requests);

    switch (metric) {
        case 'views': return {kind: 'count', value: num(totals.pageViews), parts: []};
        case 'visitors': return {kind: 'count', value: num(totals.visitors), parts: []};
        case 'requests': return {kind: 'count', value: requests, parts: []};
        case 'bots': return {kind: 'percent', value: share(num(totals.botRequests), requests), parts: [{label: 'sulu_dashboard.visitors_count_of_requests', kind: 'count', value: num(totals.botRequests)}]};
        case 'client_errors':
        case 'server_errors': {
            const count = statusClass(totals.statusClasses, 'client_errors' === metric ? 4 : 5);

            return {kind: 'count', value: count, parts: [{label: 'sulu_dashboard.visitors_share_of_requests', kind: 'percent', value: share(count, requests)}]};
        }
        case 'response_time': {
            const time = isObject(statistics.responseTime) ? statistics.responseTime : null;

            return {kind: 'ms', value: time ? num(time.averageMs) : null, parts: time ? [{label: 'sulu_dashboard.visitors_p95', kind: 'bound', value: p95(statistics)}] : []};
        }
        case 'bytes': return {kind: 'bytes', value: num(totals.bytes), parts: []};
        default: return {kind: 'count', value: null, parts: []};
    }
};

// the day as a short label ("12 Nov"), read in UTC so that no time zone moves it
export const formatDay = (day) => {
    const date = new Date(String(day) + 'T00:00:00Z');

    return isNaN(date) ? String(day) : new Intl.DateTimeFormat(undefined, {day: 'numeric', month: 'short', timeZone: 'UTC'}).format(date);
};

const dayLabels = (statistics) => list(statistics.days).map((day) => formatDay(isObject(day) ? day.day : ''));

const series = (label, values, color) => ({label, values, color});

// the data of a chart tile (see tiles/ChartTile.js); null if the metric has no chart, {empty: 'durations'} if the logs
// hold no response times
export const chartData = (metric, statistics) => {
    const days = list(statistics.days).map((day) => (isObject(day) ? day : {}));

    switch (metric) {
        case 'chart_views':
            return {kind: 'line', labels: dayLabels(statistics), series: [series('sulu_dashboard.page_views', days.map((day) => num(day.pageViews)), 'blue')]};
        case 'chart_visitors':
            return {kind: 'line', labels: dayLabels(statistics), series: [series('sulu_dashboard.visitors_count', days.map((day) => num(day.visitors)), 'green')]};
        case 'chart_status':
            return {
                kind: 'bar',
                stacked: true,
                labels: dayLabels(statistics),
                series: [
                    series('sulu_dashboard.visitors_status_success', days.map((day) => statusClass(day.statusClasses, 2)), 'green'),
                    // the class 1xx (information) is added to the redirects, like the view of the log statistics does
                    series('sulu_dashboard.visitors_status_redirect', days.map((day) => statusClass(day.statusClasses, 3) + statusClass(day.statusClasses, 1)), 'gray'),
                    series('sulu_dashboard.visitors_status_client', days.map((day) => statusClass(day.statusClasses, 4)), 'yellow'),
                    series('sulu_dashboard.visitors_status_server', days.map((day) => statusClass(day.statusClasses, 5)), 'red'),
                ],
            };
        case 'chart_hours':
            return {
                kind: 'bar',
                labels: list(statistics.pageViewsByHourOfDay).map((_, hour) => hour + ' h'),
                series: [series('sulu_dashboard.page_views', list(statistics.pageViewsByHourOfDay).map(num), 'blue')],
            };
        case 'chart_response': {
            if (!days.some((day) => isObject(day.responseTime))) {
                return {empty: 'durations'};
            }

            const time = (day) => (isObject(day.responseTime) ? day.responseTime : null);

            return {
                kind: 'line',
                unit: 'ms',
                labels: dayLabels(statistics),
                series: [
                    series('sulu_dashboard.visitors_series_average', days.map((day) => (time(day) ? Math.round(num(time(day).averageMs)) : null)), 'blue'),
                    series('sulu_dashboard.visitors_series_p95', days.map((day) => (time(day) && isObject(time(day).p95) ? num(time(day).p95.boundMs) : null)), 'gray'),
                ],
            };
        }
        default:
            return null;
    }
};

const RANKINGS = {top_pages: 'topPages', top_not_found: 'topNotFound', top_errors: 'topServerErrors'};

// the rows of a top list (see tiles/ListTile.js): the path and its count
export const rankingRows = (metric, statistics) => list(statistics[RANKINGS[metric]])
    .filter(isObject)
    .map((entry) => ({title: String(entry.path), meta: num(entry.count).toLocaleString()}));

// one row of the table of days
export const dailyRows = (statistics) => list(statistics.days)
    .filter(isObject)
    .map((day) => ({
        day: String(day.day),
        pageViews: num(day.pageViews),
        visitors: num(day.visitors),
        success: statusClass(day.statusClasses, 2),
        redirect: statusClass(day.statusClasses, 3) + statusClass(day.statusClasses, 1),
        clientError: statusClass(day.statusClasses, 4),
        serverError: statusClass(day.statusClasses, 5),
        averageMs: isObject(day.responseTime) ? num(day.responseTime.averageMs) : null,
    }))
    .reverse();
