// The tile "Security vulnerabilities" of the tab "System" (widget sulu_system_vulnerabilities): the advisories of
// "composer audit" in Sulu's table, the worst first, and below it the packages that are no longer maintained.
// data: {status: 'never' | 'unavailable' | 'ok' | 'vulnerable', advisories: [{package, installed, id, cve, title, link,
// severity, reportedAt}], abandoned: [{package, replacement}], reason, command} (see SystemTileData on the server).
// The title of an advisory is a link to its source; it opens in a new tab (Sulu has no link in tables, so a plain anchor
// with rel="noopener noreferrer"). What the data may hold and how it becomes rows: system.js.
import React from 'react';
import {Button, Icon, Table} from 'sulu-admin-bundle/components';
import Card from 'sulu-admin-bundle/components/Card';
import {translate} from 'sulu-admin-bundle/utils';
import {advisoryName, hasSeverity, isWebLink, normalize, reportedDay, severityKey, visibleRows} from '../system';
import {tx} from '../utils';
import styles from '../dashboard.scss';

const VISIBLE_ROWS = 10;

// a cell that never breaks: a package name or an ID is cut with an ellipsis when it is very long, the full text is the title
const Fixed = ({children, className}) => <span className={[styles.fixedCell, className].filter(Boolean).join(' ')} title={typeof children === 'string' ? children : undefined}>{children}</span>;

// the description breaks at word boundaries
const Words = ({children}) => <span className={styles.wordsCell}>{children}</span>;

const formatDay = (value) => {
    const date = reportedDay(value);

    return date ? date.toLocaleDateString(undefined, {dateStyle: 'medium', timeZone: 'UTC'}) : '–';
};

export default class SystemVulnerabilities extends React.Component {
    state = {expanded: false};

    handleToggle = () => this.setState({expanded: !this.state.expanded});

    renderMessage(kind, text, hint, detail) {
        const icons = {ok: 'su-check-circle', warning: 'su-exclamation-circle', error: 'su-exclamation-triangle'};
        const classes = {ok: styles.severityOk, warning: styles.severityWarning, error: styles.severityError};

        return (
            <div className={styles.systemMessage}>
                <Icon className={classes[kind]} name={icons[kind]} />
                <div>
                    <div className={kind === 'error' ? styles.severityTitleError : undefined}>{text}</div>
                    {hint && <div className={styles.rowText}>{hint}</div>}
                    {detail && <div className={styles.rowText}>{detail}</div>}
                </div>
            </div>
        );
    }

    renderAdvisories(advisories) {
        const {expanded} = this.state;
        const visible = visibleRows(advisories, expanded, VISIBLE_ROWS);
        const showSeverity = hasSeverity(advisories);

        return (
            <div className={styles.tableWrap}>
                <Table skin="light">
                    {/* Sulu's Table.Header and Table.Row read their children: arrays without false entries */}
                    <Table.Header>
                        {[
                            <Table.HeaderCell key="package">{translate('sulu_dashboard.system_col_package')}</Table.HeaderCell>,
                            <Table.HeaderCell key="title">{translate('sulu_dashboard.system_col_title')}</Table.HeaderCell>,
                            ...(showSeverity ? [<Table.HeaderCell key="severity">{translate('sulu_dashboard.system_col_severity')}</Table.HeaderCell>] : []),
                            <Table.HeaderCell key="reported">{translate('sulu_dashboard.system_col_reported')}</Table.HeaderCell>,
                        ]}
                    </Table.Header>
                    <Table.Body>
                        {visible.map((advisory, index) => {
                            const severity = severityKey(advisory.severity);
                            const title = advisory.title || advisory.id || advisory.package;
                            const name = advisoryName(advisory);

                            return (
                                <Table.Row id={index} key={index}>
                                    {[
                                        <Table.Cell key="package">
                                            <div className={styles.cellStack}>
                                                <Fixed>{advisory.package}</Fixed>
                                                {advisory.installed && <Fixed className={[styles.rowText, styles.fullLine].join(' ')}>{advisory.installed}</Fixed>}
                                            </div>
                                        </Table.Cell>,
                                        <Table.Cell key="title">
                                            <div className={styles.cellStack}>
                                                <Words>
                                                    {isWebLink(advisory.link)
                                                        ? <a className={styles.externalLink} href={advisory.link} rel="noopener noreferrer" target="_blank" title={translate('sulu_dashboard.system_opens_new_tab')}>{title}</a>
                                                        : title
                                                    }
                                                </Words>
                                                {name && <Fixed className={[styles.rowText, styles.fullLine].join(' ')}>{name}</Fixed>}
                                            </div>
                                        </Table.Cell>,
                                        ...(showSeverity ? [<Table.Cell key="severity"><Fixed>{severity ? translate(severity) : '–'}</Fixed></Table.Cell>] : []),
                                        <Table.Cell key="reported"><Fixed>{formatDay(advisory.reportedAt)}</Fixed></Table.Cell>,
                                    ]}
                                </Table.Row>
                            );
                        })}
                    </Table.Body>
                </Table>
                {advisories.length > VISIBLE_ROWS &&
                    <div className={styles.more}>
                        <Button onClick={this.handleToggle} skin="link">
                            {expanded
                                ? translate('sulu_dashboard.show_less')
                                : translate('sulu_dashboard.show_all', {count: advisories.length})}
                        </Button>
                    </div>
                }
            </div>
        );
    }

    renderAbandoned(abandoned) {
        return (
            <div className={styles.systemSection}>
                <h3 className={styles.systemSubtitle}>{translate('sulu_dashboard.system_abandoned')}</h3>
                <p className={styles.meta}>{translate('sulu_dashboard.system_abandoned_hint')}</p>
                <div className={styles.tableWrap}>
                    <Table skin="light">
                        <Table.Header>
                            <Table.HeaderCell>{translate('sulu_dashboard.system_col_package')}</Table.HeaderCell>
                            <Table.HeaderCell>{translate('sulu_dashboard.system_col_replacement')}</Table.HeaderCell>
                        </Table.Header>
                        <Table.Body>
                            {abandoned.map((entry, index) => (
                                <Table.Row id={index} key={index}>
                                    <Table.Cell><Fixed>{entry.package}</Fixed></Table.Cell>
                                    <Table.Cell><Fixed>{entry.replacement || translate('sulu_dashboard.system_no_replacement')}</Fixed></Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table>
                </div>
            </div>
        );
    }

    render() {
        const {widget, data} = this.props;
        const {state, advisories, abandoned, reason, detail, command} = normalize(data);

        return (
            <Card>
                <h2 className={styles.title}>{tx(widget.title)}</h2>
                {'never' === state && this.renderMessage('warning', translate('sulu_dashboard.system_vulnerabilities_never'), translate('sulu_dashboard.system_vulnerabilities_never_hint', {command}))}
                {'unavailable' === state && this.renderMessage('warning', translate('sulu_dashboard.system_audit_could_not_run'), reason ? tx(reason) : null, detail)}
                {'ok' === state && this.renderMessage('ok', translate('sulu_dashboard.system_vulnerabilities_none'), translate('sulu_dashboard.system_vulnerabilities_none_hint'))}
                {advisories.length > 0 && this.renderAdvisories(advisories)}
                {advisories.length > 0 && <p className={styles.rowText}>{translate('sulu_dashboard.system_vulnerabilities_found_hint')}</p>}
                {abandoned.length > 0 && this.renderAbandoned(abandoned)}
            </Card>
        );
    }
}
