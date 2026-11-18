// Widget type "chart": a line chart (one or two series) or a bar chart (simple or stacked) drawn as SVG, without a library.
// data: {kind: 'line'|'bar', stacked?: bool, labels: [], series: [{label, values: [], color?}], unit?}
// Texts (title, series labels, unit) may be plain text, an admin translation key or {key, parameters}. `color` is a name
// of the palette (blue, green, yellow, red, gray) or a hex colour; without it the series take the palette in order.
// The chart has a title tooltip per point or bar, a legend from two series on, and a table of the data as a fallback.
import React from 'react';
import {Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {isolated, linePath, maxLabels, niceScale, normalize, scale, stackSegments, stackTotals, thinLabels} from '../chartMath';
import {tx} from '../utils';
import styles from '../dashboard.scss';

const HEIGHT = 220;
const TOP = 14;
const UNIT_ROW = 14;
const BOTTOM = 26;
const RIGHT = 8;
const CHARACTER = 6.5;
const DEFAULT_WIDTH = 400;
const MAX_DOTS = 31;
const MAX_BAR = 56;

// the x labels are texts of the data ("12 Nov", "Mon"), not keys, so only an object goes through the translation
const label = (value) => (value !== null && typeof value === 'object' ? tx(value) : String(value));

const format = (value, decimals = 2) => (null === value || undefined === value
    ? '–'
    : value.toLocaleString(undefined, {maximumFractionDigits: decimals}));

// a palette name is a CSS class, a hex colour from the data goes in as the text colour (the drawing uses currentColor)
const colorClass = (color, extra) => [color.name ? styles['series_' + color.name] : null, extra].filter(Boolean).join(' ') || undefined;
const colorStyle = (color) => (color.hex ? {color: color.hex} : undefined);

export default class ChartTile extends React.Component {
    state = {width: DEFAULT_WIDTH};

    frame = React.createRef();

    componentDidMount() {
        this.measure();

        if (typeof ResizeObserver !== 'undefined' && this.frame.current) {
            this.observer = new ResizeObserver(() => this.measure());
            this.observer.observe(this.frame.current);
        }
    }

    componentWillUnmount() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    measure() {
        const width = this.frame.current ? Math.floor(this.frame.current.clientWidth) : 0;

        if (width > 0 && width !== this.state.width) {
            this.setState({width});
        }
    }

    // tooltip of one position: "12 Nov: Page views 1,204 ms, Visitors 380 ms"
    tip(chart, index) {
        const unit = chart.unit ? ' ' + tx(chart.unit) : '';
        const parts = chart.series.map((entry) => (
            (1 === chart.series.length ? '' : tx(entry.label) + ' ') + format(entry.values[index]) + unit
        ));

        return label(chart.labels[index]) + ': ' + parts.join(', ');
    }

    summary(chart) {
        const names = chart.series.map((entry) => tx(entry.label)).join(', ');
        const last = chart.labels.length - 1;

        return tx(this.props.widget.title) + (names ? ': ' + names : '')
            + ' (' + label(chart.labels[0]) + (last > 0 ? ' – ' + label(chart.labels[last]) : '') + ')';
    }

    renderChart(chart) {
        const {width} = this.state;
        const count = chart.labels.length;
        const values = chart.series.flatMap((entry) => entry.values).filter((value) => null !== value);
        const totals = chart.stacked ? stackTotals(chart.series, count) : [];
        const high = chart.stacked ? Math.max(0, ...totals) : Math.max(0, ...values);
        const low = chart.stacked ? 0 : Math.min(0, ...values);
        const axis = niceScale(low, high, 5, values.every((value) => Number.isInteger(value)));
        const left = Math.max(...axis.ticks.map((tick) => format(tick, axis.decimals).length)) * CHARACTER + 14;
        const plotWidth = Math.max(40, width - left - RIGHT);
        const top = TOP + (chart.unit ? UNIT_ROW : 0);
        const bottom = HEIGHT - BOTTOM;
        const y = scale(axis.min, axis.max, bottom, top);
        const band = plotWidth / count;
        const center = (index) => left + band * (index + 0.5);
        const longest = Math.max(...chart.labels.map((entry) => label(entry).length));
        const shown = thinLabels(count, maxLabels(plotWidth, longest));
        const isLine = 'line' === chart.kind;
        const groupWidth = Math.min(band * 0.8, MAX_BAR * (chart.stacked ? 1 : chart.series.length));

        return (
            <svg aria-label={this.summary(chart)} className={styles.chartSvg} height={HEIGHT} role="img" width={width}>
                {axis.ticks.map((tick) => (
                    <g key={tick}>
                        <line
                            className={0 === tick ? styles.chartAxis : styles.chartGrid}
                            x1={left}
                            x2={left + plotWidth}
                            y1={y(tick)}
                            y2={y(tick)}
                        />
                        <text className={styles.chartText} dy="0.32em" textAnchor="end" x={left - 8} y={y(tick)}>
                            {format(tick, axis.decimals)}
                        </text>
                    </g>
                ))}
                {chart.unit && (
                    <text className={styles.chartText} textAnchor="end" x={left - 8} y={TOP - 2}>{tx(chart.unit)}</text>
                )}
                {shown.map((index) => (
                    <text className={styles.chartText} key={index} textAnchor="middle" x={center(index)} y={HEIGHT - 8}>
                        {label(chart.labels[index])}
                    </text>
                ))}
                {isLine ? this.renderLines(chart, center, y, count) : this.renderBars(chart, center, y, groupWidth, totals)}
                {isLine && chart.labels.map((entry, index) => (
                    <rect className={styles.chartHit} height={bottom - top} key={index} width={band} x={left + band * index} y={top}>
                        <title>{this.tip(chart, index)}</title>
                    </rect>
                ))}
            </svg>
        );
    }

    renderLines(chart, center, y, count) {
        return chart.series.map((entry, seriesIndex) => {
            const points = entry.values.map((value, index) => (null === value ? null : {x: center(index), y: y(value)}));

            return (
                <g className={colorClass(entry.color)} key={seriesIndex} style={colorStyle(entry.color)}>
                    <path className={styles.chartLine} d={linePath(points)} />
                    {points.map((point, index) => point && (count <= MAX_DOTS || isolated(points, index)) && (
                        <circle className={styles.chartDot} cx={point.x} cy={point.y} key={index} r={3} />
                    ))}
                </g>
            );
        });
    }

    renderBars(chart, center, y, groupWidth, totals) {
        const barWidth = chart.stacked ? groupWidth : groupWidth / chart.series.length;

        return chart.labels.map((entry, index) => {
            const segments = chart.stacked ? stackSegments(chart.series, index) : null;
            const tip = this.tip(chart, index) + (chart.stacked ? ' (' + format(totals[index]) + ')' : '');

            return (
                <g key={index}>
                    {chart.series.map((entrySeries, seriesIndex) => {
                        const value = entrySeries.values[index] || 0;
                        const start = chart.stacked ? segments[seriesIndex].start : 0;
                        const end = chart.stacked ? segments[seriesIndex].end : value;
                        const upper = y(Math.max(start, end));
                        const lower = y(Math.min(start, end));

                        return (
                            <rect
                                className={colorClass(entrySeries.color, styles.chartBar)}
                                height={Math.max(0, lower - upper)}
                                key={seriesIndex}
                                style={colorStyle(entrySeries.color)}
                                width={Math.max(1, barWidth - (chart.stacked ? 0 : 1))}
                                x={center(index) - groupWidth / 2 + (chart.stacked ? 0 : barWidth * seriesIndex)}
                                y={upper}
                            >
                                <title>{tip}</title>
                            </rect>
                        );
                    })}
                </g>
            );
        });
    }

    renderLegend(chart) {
        return (
            <ul className={styles.chartLegend}>
                {chart.series.map((entry, index) => (
                    <li key={index}>
                        <span className={colorClass(entry.color)} style={colorStyle(entry.color)}>
                            <i className={styles.chartSwatch} />
                        </span>
                        {tx(entry.label)}
                    </li>
                ))}
            </ul>
        );
    }

    renderTable(chart) {
        const unit = chart.unit ? ' ' + tx(chart.unit) : '';

        return (
            <details className={styles.chartData}>
                <summary>{translate('sulu_dashboard.chart_data_table')}</summary>
                <div className={styles.tableWrap}>
                    <Table skin="light">
                        <Table.Header>
                            <Table.HeaderCell>{' '}</Table.HeaderCell>
                            {chart.series.map((entry, index) => <Table.HeaderCell key={index}>{tx(entry.label)}</Table.HeaderCell>)}
                        </Table.Header>
                        <Table.Body>
                            {chart.labels.map((entry, index) => (
                                <Table.Row id={index} key={index}>
                                    <Table.Cell>{label(entry)}</Table.Cell>
                                    {chart.series.map((entrySeries, seriesIndex) => (
                                        <Table.Cell key={seriesIndex}>
                                            {format(entrySeries.values[index]) + (null === entrySeries.values[index] ? '' : unit)}
                                        </Table.Cell>
                                    ))}
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>
            </details>
        );
    }

    render() {
        const {widget, data} = this.props;
        const chart = normalize(data);

        return (
            <Card>
                <h2 className={styles.title}>{tx(widget.title)}</h2>
                <div className={styles.chartFrame} ref={this.frame}>
                    {chart.empty
                        ? <p className={styles.meta}>{translate('sulu_dashboard.empty')}</p>
                        : this.renderChart(chart)
                    }
                </div>
                {!chart.empty && chart.series.length > 1 && this.renderLegend(chart)}
                {!chart.empty && this.renderTable(chart)}
            </Card>
        );
    }
}
