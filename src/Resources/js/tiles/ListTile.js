// Widget type "list": a short table of entries, ten rows and the rest on demand.
// data: {rows: [{title, text?, meta?, state?, severity?, link?: {view, attributes}}], limit?, empty?, footer?: {title, rows}}
// text is a grey second line under the title, meta a second column. state is a workflow place (Sulu's PublishIndicator),
// severity ('error' | 'warning') puts Sulu's warning icon in front of the row (red title for 'error'), limit is the number
// of rows shown before "Show all" (default 10), link opens a Sulu view via the pen button. Every text may be plain text, an admin translation key or {key, parameters}.
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, Icon, PublishIndicator, Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {INDICATOR, navigate, tx} from '../utils';
import Footer from './Footer';
import styles from '../dashboard.scss';

const VISIBLE_ROWS = 10;

const SEVERITY_ICONS = {error: 'su-exclamation-triangle', warning: 'su-exclamation-circle'};

// wrap: a row with a severity is a message and must be readable in full, so it breaks into lines instead of being cut off
const Ellipsis = ({children, className, title, wrap}) => {
    const classes = [wrap ? styles.wrap : styles.ellipsis, className].filter(Boolean).join(' ');

    return <span className={classes} title={title}>{children}</span>;
};

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
        const limit = data.limit > 0 ? data.limit : VISIBLE_ROWS;
        const visible = this.expanded ? rows : rows.slice(0, limit);
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
                        {/* Sulu's Table maps its children and reads their props: no false or null entries. */}
                        <Table.Header>
                            {[
                                <Table.HeaderCell key="title">{translate('sulu_admin.title')}</Table.HeaderCell>,
                                ...(hasMeta ? [<Table.HeaderCell key="meta">{' '}</Table.HeaderCell>] : []),
                            ]}
                        </Table.Header>
                        <Table.Body>
                            {visible.map((row, index) => (
                                <Table.Row id={index} key={index}>
                                    {[
                                        <Table.Cell key="title">
                                            {row.severity && SEVERITY_ICONS[row.severity] &&
                                                <Icon
                                                    className={styles['severity' + (row.severity === 'error' ? 'Error' : 'Warning')]}
                                                    name={SEVERITY_ICONS[row.severity]}
                                                />
                                            }
                                            {row.state && INDICATOR[row.state] &&
                                                <PublishIndicator className={styles.indicator} {...INDICATOR[row.state]} />
                                            }
                                            <div className={styles.rowTexts}>
                                                <Ellipsis className={row.severity === 'error' ? styles.severityTitleError : undefined} title={tx(row.title)} wrap={!!row.severity}>{tx(row.title)}</Ellipsis>
                                                {row.text && <Ellipsis className={styles.rowText} title={tx(row.text)} wrap={!!row.severity}>{tx(row.text)}</Ellipsis>}
                                            </div>
                                        </Table.Cell>,
                                        ...(hasMeta
                                            ? [<Table.Cell key="meta"><Ellipsis>{tx(row.meta)}</Ellipsis></Table.Cell>]
                                            : []),
                                    ]}
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>
                {data.footer && data.footer.rows && data.footer.rows.length > 0 &&
                    <Footer footer={data.footer} router={router} />
                }
                {rows.length > limit &&
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
