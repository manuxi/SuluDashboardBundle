// The tiles of the tab "Visitors" (widgets sulu_visitors_<metric>, group "visitors", only with amoifr/sulu-log-stats-bundle).
// One component for all of them: the server says per tile which metric it shows (data.metric) and where to read
// (data.apiUrl, data.timezone). All tiles share one request per period (logStatsStore.js) and show the answer as number,
// chart, top list or table. What they read and how it becomes figures: logStats.js.
// If the answer lacks fields the tile expects (another version of the log statistics), the tile says which ones.
import React from 'react';
import {Loader} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {transformBytesToReadableString, translate} from 'sulu-admin-bundle/utils';
import {chartData, dailyRows, figure, hasNoData, missingFields, rankingRows, statisticsUrl} from '../logStats';
import store from '../logStatsStore';
import {periodKey} from '../period';
import ChartTile from '../tiles/ChartTile';
import DailyTable from '../tiles/DailyTable';
import ListTile from '../tiles/ListTile';
import NumberTile from '../tiles/NumberTile';
import {tx} from '../utils';
import styles from '../dashboard.scss';

const DEFAULT_PERIOD = {days: 30};

const formatValue = (kind, value) => {
    if (null === value) {
        return '–';
    }

    switch (kind) {
        case 'percent': return value.toLocaleString() + ' %';
        case 'ms': return Math.round(value).toLocaleString() + ' ms';
        case 'bytes': return transformBytesToReadableString(value);
        case 'bound': return (value.beyond ? '> ' : '≤ ') + value.boundMs.toLocaleString() + ' ms';
        default: return value.toLocaleString();
    }
};

export default class VisitorTile extends React.Component {
    state = {statistics: undefined, error: undefined};

    componentDidMount() {
        this.load();
    }

    componentDidUpdate(prevProps) {
        if (periodKey(prevProps.period || DEFAULT_PERIOD) !== periodKey(this.props.period || DEFAULT_PERIOD)) {
            this.load();
        }
    }

    componentWillUnmount() {
        this.unmounted = true;
    }

    get metric() {
        const {data, widget} = this.props;

        return data.metric || widget.key.replace(/^sulu_visitors_/, '');
    }

    load() {
        const {data, period} = this.props;

        if (!data.apiUrl) {
            this.setState({statistics: undefined, error: translate('sulu_dashboard.visitors_not_set_up')});

            return;
        }

        this.setState({statistics: undefined, error: undefined});
        store.load(statisticsUrl(data.apiUrl, period || DEFAULT_PERIOD, data.timezone))
            .then((statistics) => !this.unmounted && this.setState({statistics}))
            .catch(() => !this.unmounted && this.setState({error: translate('sulu_dashboard.widget_error')}));
    }

    renderMessage(text, isError) {
        return (
            <Card>
                <h2 className={styles.title}>{tx(this.props.widget.title)}</h2>
                <p className={isError ? styles.error : styles.meta}>{text}</p>
            </Card>
        );
    }

    render() {
        const {widget} = this.props;
        const {statistics, error} = this.state;
        const metric = this.metric;

        if (error) {
            return this.renderMessage(error, true);
        }

        if (undefined === statistics) {
            return <Card><Loader /></Card>;
        }

        // never a silent zero: if the answer is not what the tile expects, say so
        const missing = missingFields(statistics, metric);
        if (missing.length > 0) {
            return this.renderMessage(translate('sulu_dashboard.visitors_api_changed', {fields: missing.join(', ')}), true);
        }

        const isChart = metric.startsWith('chart_');
        const isRanking = metric.startsWith('top_');

        if ((isChart || isRanking || 'daily' === metric) && hasNoData(statistics)) {
            return this.renderMessage(translate('sulu_dashboard.visitors_no_period_data'), false);
        }

        if (isChart) {
            const chart = chartData(metric, statistics);

            return chart && chart.empty
                ? this.renderMessage(translate('sulu_dashboard.visitors_no_' + chart.empty), false)
                : <ChartTile data={chart} widget={widget} />;
        }

        if (isRanking) {
            return <ListTile data={{rows: rankingRows(metric, statistics), empty: 'sulu_dashboard.visitors_nothing'}} router={this.props.router} widget={widget} />;
        }

        if ('daily' === metric) {
            return <DailyTable rows={dailyRows(statistics)} widget={widget} />;
        }

        const result = figure(metric, statistics);

        return (
            <NumberTile
                data={{
                    value: formatValue(result.kind, result.value),
                    parts: result.parts.map((part) => ({label: part.label, count: formatValue(part.kind, part.value)})),
                }}
                widget={widget}
            />
        );
    }
}
