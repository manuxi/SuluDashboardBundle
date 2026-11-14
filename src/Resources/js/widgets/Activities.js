// Tile "Recent activity" (widget sulu_activities). Reads the activity API of Sulu: it translates the description on the
// server (all placeholders, language of the user) and checks the permissions, so nothing is translated or checked here.
import React from 'react';
import {Loader} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import {formatDate} from '../utils';
import styles from '../dashboard.scss';

// activity types that are noise on a start page
const QUIET_ACTIVITIES = ['preview_link_generated'];

// the description marks the user name with <b>. Render it as text, never as HTML: titles in it are not escaped.
const ActivityText = ({description}) => String(description || '').split(/<\/?b>/).map((part, index) => (
    index % 2 === 1 ? <strong key={index}>{part}</strong> : <React.Fragment key={index}>{part}</React.Fragment>
));

export default class Activities extends React.Component {
    state = {activities: undefined};

    componentDidMount() {
        const locale = encodeURIComponent(this.props.locale);

        Requester.get('/admin/api/activities?locale=' + locale + '&page=1&limit=20&fields=type,timestamp,description')
            .then((response) => {
                if (this.unmounted) {
                    return;
                }
                this.setState({
                    activities: ((response._embedded || {}).activities || [])
                        .filter((activity) => !QUIET_ACTIVITIES.includes(activity.type))
                        .slice(0, 8),
                });
            })
            .catch(() => !this.unmounted && this.setState({activities: []}));
    }

    componentWillUnmount() {
        this.unmounted = true;
    }

    render() {
        const {widget} = this.props;
        const {activities} = this.state;

        return (
            <Card>
                <h2 className={styles.title}>{translate(widget.title)}</h2>
                {!activities && <Loader />}
                {activities && activities.length === 0 &&
                    <p className={styles.meta}>{translate('sulu_dashboard.no_activities')}</p>
                }
                <ul className={styles.list}>
                    {(activities || []).map((activity) => (
                        <li key={activity.id}>
                            <ActivityText description={activity.description} />
                            <div className={styles.meta}>{formatDate(activity.timestamp)}</div>
                        </li>
                    ))}
                </ul>
            </Card>
        );
    }
}
