// Tile "Visitors (7 days)" (widget sulu_visitors, only with amoifr/sulu-log-stats-bundle). Reads the admin API of the
// log statistics (GET /admin/api/log-stats): without a period it answers with the last 30 days up to its own today, the
// last 7 of them are added up here. The visitors are counted per day (the statistics keep no identifier across days), so the
// sum is the sum of the daily visitors. The share of error pages is the share of the requests with status 4xx and 5xx.
import React from 'react';
import {Loader} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import NumberTile from '../tiles/NumberTile';
import styles from '../dashboard.scss';

const sum = (days, key) => days.reduce((total, day) => total + (day[key] || 0), 0);

const errorShare = (days) => {
    const requests = sum(days, 'requests');
    const errors = days.reduce((total, day) => total + ((day.statusClasses || {})[4] || 0) + ((day.statusClasses || {})[5] || 0), 0);

    return requests > 0 ? Math.round(errors / requests * 1000) / 10 : 0;
};

const format = (value) => new Intl.NumberFormat().format(value);

export default class Visitors extends React.Component {
    state = {days: undefined, error: undefined};

    componentDidMount() {
        const {apiUrl, days} = this.props.data;

        if (!apiUrl) {
            this.setState({error: translate('sulu_dashboard.visitors_not_set_up')});

            return;
        }

        Requester.get(apiUrl)
            .then((statistics) => !this.unmounted && this.setState({days: (statistics.days || []).slice(-days)}))
            .catch(() => !this.unmounted && this.setState({error: translate('sulu_dashboard.widget_error')}));
    }

    componentWillUnmount() {
        this.unmounted = true;
    }

    render() {
        const {widget} = this.props;
        const {days, error} = this.state;

        if (error || !days) {
            return (
                <Card>
                    <h2 className={styles.title}>{translate(widget.title)}</h2>
                    {error ? <p className={styles.error}>{error}</p> : <Loader />}
                </Card>
            );
        }

        if (sum(days, 'requests') === 0) {
            return (
                <Card>
                    <h2 className={styles.title}>{translate(widget.title)}</h2>
                    <p className={styles.meta}>{translate('sulu_dashboard.visitors_no_data')}</p>
                </Card>
            );
        }

        // the figure is the page views; visitors and the share of error pages are the parts below it
        const data = {
            value: format(sum(days, 'pageViews')),
            parts: [
                {label: 'sulu_dashboard.visitors_count', count: format(sum(days, 'visitors'))},
                {label: 'sulu_dashboard.error_share', count: format(errorShare(days)) + ' %'},
            ],
        };

        return <NumberTile data={data} widget={{...widget, title: 'sulu_dashboard.page_views_week'}} />;
    }
}
