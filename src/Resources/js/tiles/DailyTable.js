// The table of days of the tab "Visitors": one row per day, the newest first, ten rows and the rest on demand.
// props.rows: [{day, pageViews, visitors, success, redirect, clientError, serverError, averageMs}] (see logStats.js dailyRows)
import React from 'react';
import {Button, Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {formatDay} from '../logStats';
import {tx} from '../utils';
import styles from '../dashboard.scss';

const VISIBLE_ROWS = 10;

const count = (value) => value.toLocaleString();

export default class DailyTable extends React.Component {
    state = {expanded: false};

    handleToggle = () => this.setState({expanded: !this.state.expanded});

    render() {
        const {rows, widget} = this.props;
        const visible = this.state.expanded ? rows : rows.slice(0, VISIBLE_ROWS);

        return (
            <Card>
                <h2 className={styles.title}>{tx(widget.title)}</h2>
                <div className={styles.tableWrap}>
                    <Table placeholderText={translate('sulu_dashboard.visitors_nothing')} skin="light">
                        <Table.Header>
                            <Table.HeaderCell>{translate('sulu_dashboard.visitors_col_day')}</Table.HeaderCell>
                            <Table.HeaderCell>{translate('sulu_dashboard.page_views')}</Table.HeaderCell>
                            <Table.HeaderCell>{translate('sulu_dashboard.visitors_count')}</Table.HeaderCell>
                            <Table.HeaderCell>2xx</Table.HeaderCell>
                            <Table.HeaderCell>3xx</Table.HeaderCell>
                            <Table.HeaderCell>4xx</Table.HeaderCell>
                            <Table.HeaderCell>5xx</Table.HeaderCell>
                            <Table.HeaderCell>{translate('sulu_dashboard.visitors_col_time')}</Table.HeaderCell>
                        </Table.Header>
                        <Table.Body>
                            {visible.map((row) => (
                                <Table.Row id={row.day} key={row.day}>
                                    <Table.Cell>{formatDay(row.day)}</Table.Cell>
                                    <Table.Cell>{count(row.pageViews)}</Table.Cell>
                                    <Table.Cell>{count(row.visitors)}</Table.Cell>
                                    <Table.Cell>{count(row.success)}</Table.Cell>
                                    <Table.Cell>{count(row.redirect)}</Table.Cell>
                                    <Table.Cell>{count(row.clientError)}</Table.Cell>
                                    <Table.Cell>{count(row.serverError)}</Table.Cell>
                                    <Table.Cell>{null === row.averageMs ? '–' : Math.round(row.averageMs).toLocaleString() + ' ms'}</Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>
                {rows.length > VISIBLE_ROWS &&
                    <div className={styles.more}>
                        <Button onClick={this.handleToggle} skin="link">
                            {this.state.expanded
                                ? translate('sulu_dashboard.show_less')
                                : translate('sulu_dashboard.show_all', {count: rows.length})}
                        </Button>
                    </div>
                }
            </Card>
        );
    }
}
