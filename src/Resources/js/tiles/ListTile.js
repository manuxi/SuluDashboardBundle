// Widget type "list": a short table of entries, ten rows and the rest on demand.
// data: {rows: [{title, text?, meta?, state?, link?: {view, attributes}}], empty?, footer?: {title, rows}}
// text is a grey second line under the title, meta a second column. state is a workflow place (Sulu's PublishIndicator),
// link opens a Sulu view via the pen button. Every text may be plain text, an admin translation key or {key, parameters}.
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, PublishIndicator, Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {INDICATOR, navigate, tx} from '../utils';
import Footer from './Footer';
import styles from '../dashboard.scss';

const VISIBLE_ROWS = 10;

const Ellipsis = ({children, className, title}) => (
    <span className={className ? styles.ellipsis + ' ' + className : styles.ellipsis} title={title}>{children}</span>
);

@observer
export default class ListTile extends React.Component {
    @observable expanded = false;

    @action handleToggleExpanded = () => {
        this.expanded = !this.expanded;
    };

    handleEdit = (id) => {
        const row = (this.props.data.rows || [])[id];

        navigate(this.props.router, row && row.link);
    };

    render() {
        const {widget, data, router} = this.props;
        const rows = data.rows || [];
        const visible = this.expanded ? rows : rows.slice(0, VISIBLE_ROWS);
        const hasLinks = rows.some((row) => row.link);
        const hasMeta = rows.some((row) => row.meta);

        return (
            <Card>
                <h2 className={styles.title}>{tx(widget.title)}</h2>
                <div className={styles.tableWrap}>
                    <Table
                        buttons={hasLinks ? [{icon: 'su-pen', onClick: this.handleEdit}] : []}
                        placeholderText={tx(data.empty || 'sulu_dashboard.empty')}
                        skin="light"
                    >
                        <Table.Header>
                            <Table.HeaderCell>{translate('sulu_admin.title')}</Table.HeaderCell>
                            {hasMeta && <Table.HeaderCell>{' '}</Table.HeaderCell>}
                        </Table.Header>
                        <Table.Body>
                            {visible.map((row, index) => (
                                <Table.Row id={index} key={index}>
                                    <Table.Cell>
                                        {row.state && INDICATOR[row.state] &&
                                            <PublishIndicator className={styles.indicator} {...INDICATOR[row.state]} />
                                        }
                                        <div className={styles.rowTexts}>
                                            <Ellipsis title={tx(row.title)}>{tx(row.title)}</Ellipsis>
                                            {row.text && <Ellipsis className={styles.rowText}>{tx(row.text)}</Ellipsis>}
                                        </div>
                                    </Table.Cell>
                                    {hasMeta && <Table.Cell><Ellipsis>{tx(row.meta)}</Ellipsis></Table.Cell>}
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>
                {data.footer && data.footer.rows && data.footer.rows.length > 0 &&
                    <Footer footer={data.footer} router={router} />
                }
                {rows.length > VISIBLE_ROWS &&
                    <div className={styles.more}>
                        <Button onClick={this.handleToggleExpanded} skin="link">
                            {this.expanded
                                ? translate('sulu_dashboard.show_less')
                                : translate('sulu_dashboard.show_all', {count: rows.length})}
                        </Button>
                    </div>
                }
            </Card>
        );
    }
}
