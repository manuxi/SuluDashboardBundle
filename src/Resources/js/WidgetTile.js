// One tile: loads its own data (so an error stays inside the tile) and shows it by type. Types number, progress, list, bars
// and chart are built in; "custom" looks up a React component in the widgetRegistry under the key of the tile.
// The answer is kept in props.cache (owned by the dashboard) so that a tile the dashboard moves to the other column, which
// mounts it again, shows its data at once. props.onSettled(key) tells the dashboard that the data arrived or failed.
import React from 'react';
import {Loader} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import BarsTile from './tiles/BarsTile';
import ChartTile from './tiles/ChartTile';
import ListTile from './tiles/ListTile';
import NumberTile from './tiles/NumberTile';
import ProgressTile from './tiles/ProgressTile';
import widgetRegistry from './widgetRegistry';
import styles from './dashboard.scss';

const TYPES = {
    number: NumberTile,
    progress: ProgressTile,
    list: ListTile,
    bars: BarsTile,
    chart: ChartTile,
};

export default class WidgetTile extends React.Component {
    state = {data: (this.props.cache || {})[this.props.widget.key + ':' + this.props.locale], error: false};

    componentDidMount() {
        if (this.state.data !== undefined) {
            this.settled();

            return;
        }

        this.load();
    }

    componentDidUpdate(prevProps) {
        if (prevProps.locale !== this.props.locale) {
            this.load();
        }
    }

    componentWillUnmount() {
        this.unmounted = true;
    }

    settled() {
        if (this.props.onSettled) {
            this.props.onSettled(this.props.widget.key);
        }
    }

    load() {
        const {cache, widget, locale} = this.props;
        this.setState({data: undefined, error: false});

        Requester.get('/admin/api/dashboard/widgets/' + encodeURIComponent(widget.key) + '?locale=' + encodeURIComponent(locale))
            .then((response) => {
                const data = response.data || {};
                if (cache) {
                    cache[widget.key + ':' + locale] = data;
                }
                if (!this.unmounted) {
                    this.setState({data}, () => this.settled());
                }
            })
            .catch(() => {
                if (!this.unmounted) {
                    this.setState({error: true}, () => this.settled());
                }
            });
    }

    render() {
        const {cache, widget, locale, locales, options, router} = this.props;
        const {data, error} = this.state;

        if (error) {
            return (
                <Card>
                    <h2 className={styles.title}>{translate(widget.title)}</h2>
                    <p className={styles.error}>{translate('sulu_dashboard.widget_error')}</p>
                </Card>
            );
        }

        if (data === undefined) {
            return <Card><Loader /></Card>;
        }

        let Component;
        if ('custom' === widget.type) {
            if (!widgetRegistry.has(widget.key)) {
                return (
                    <Card>
                        <h2 className={styles.title}>{translate(widget.title)}</h2>
                        <p className={styles.error}>{translate('sulu_dashboard.widget_unknown')}</p>
                    </Card>
                );
            }
            Component = widgetRegistry.get(widget.key);
        } else {
            Component = TYPES[widget.type];
        }

        return <Component cache={cache} data={data} locale={locale} locales={locales} options={options} router={router} widget={widget} />;
    }
}
