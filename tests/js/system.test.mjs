// Run with: node --test tests/js/*.test.mjs   (Node 22 or newer, no packages needed)
import assert from 'node:assert/strict';
import {test} from 'node:test';
import {advisoryName, hasSeverity, isWebLink, normalize, reportedDay, severityKey, visibleRows} from '../../src/Resources/js/system.js';

test('only a web link becomes a link', () => {
    assert.equal(isWebLink('https://github.com/advisories/GHSA-1'), true);
    assert.equal(isWebLink('http://example.com/a?b=1'), true);
    assert.equal(isWebLink('javascript:alert(1)'), false);
    assert.equal(isWebLink('data:text/html,<b>x</b>'), false);
    assert.equal(isWebLink('//example.com/x'), false);
    assert.equal(isWebLink('https://example.com/a b'), false);
    assert.equal(isWebLink('https://example.com/"onclick="x'), false);
    assert.equal(isWebLink('https://example.com/' + 'a'.repeat(600)), false);
    assert.equal(isWebLink(null), false);
    assert.equal(isWebLink(undefined), false);
    assert.equal(isWebLink(42), false);
});

test('a severity has a translation key, an unknown one has none', () => {
    assert.equal(severityKey('critical'), 'sulu_dashboard.system_severity_critical');
    assert.equal(severityKey('low'), 'sulu_dashboard.system_severity_low');
    assert.equal(severityKey(null), null);
    assert.equal(severityKey('urgent'), null);
    assert.equal(severityKey('constructor'), null);
});

test('an advisory is named by its CVE, otherwise by its ID', () => {
    assert.equal(advisoryName({cve: 'CVE-2026-1', id: 'PKSA-1'}), 'CVE-2026-1');
    assert.equal(advisoryName({cve: null, id: 'PKSA-1'}), 'PKSA-1');
    assert.equal(advisoryName({cve: null, id: null}), null);
});

test('the reported day is the day of the source, in every time zone', () => {
    const date = reportedDay('2026-10-18T23:45:00-05:00');
    assert.equal(date.toISOString(), '2026-10-18T00:00:00.000Z');
    assert.equal(reportedDay('2026-02-30').toISOString(), '2026-03-02T00:00:00.000Z');
    assert.equal(reportedDay(null), null);
    assert.equal(reportedDay(''), null);
    assert.equal(reportedDay('yesterday'), null);
});

test('the data is normalized: known state, lists are lists', () => {
    assert.deepEqual(normalize(undefined), {state: 'never', advisories: [], abandoned: [], reason: null, detail: null, command: ''});
    assert.deepEqual(normalize('nonsense'), {state: 'never', advisories: [], abandoned: [], reason: null, detail: null, command: ''});
    assert.equal(normalize({status: 'something else'}).state, 'never');

    const vulnerable = normalize({status: 'vulnerable', advisories: [{package: 'a/b'}, null, 'text', {package: 'c/d'}], abandoned: 'no list', command: 'php x'});
    assert.equal(vulnerable.state, 'vulnerable');
    assert.deepEqual(vulnerable.advisories.map((entry) => entry.package), ['a/b', 'c/d']);
    assert.deepEqual(vulnerable.abandoned, []);
    assert.equal(vulnerable.command, 'php x');
});

test('vulnerable without an entry is ok, and only a vulnerable state has entries', () => {
    assert.equal(normalize({status: 'vulnerable', advisories: []}).state, 'ok');
    assert.equal(normalize({status: 'vulnerable'}).state, 'ok');
    assert.deepEqual(normalize({status: 'ok', advisories: [{package: 'a/b'}]}).advisories, []);
    assert.equal(normalize({status: 'unavailable', reason: 'sulu_dashboard.system_audit_error_timeout'}).reason, 'sulu_dashboard.system_audit_error_timeout');
    assert.equal(normalize({status: 'ok', abandoned: [{package: 'a/old', replacement: null}]}).abandoned.length, 1);
    assert.equal(normalize({status: 'unavailable', detail: 'curl error 6'}).detail, 'curl error 6');
    assert.equal(normalize({status: 'unavailable', detail: ''}).detail, null);
});

test('ten rows are shown until the rest is asked for', () => {
    const rows = Array.from({length: 13}, (_, index) => index);
    assert.equal(visibleRows(rows, false, 10).length, 10);
    assert.equal(visibleRows(rows, true, 10).length, 13);
    assert.equal(visibleRows([], false, 10).length, 0);
});

test('the severity column exists only when an advisory has a known severity', () => {
    assert.equal(hasSeverity([{severity: null}, {severity: undefined}, {severity: 'urgent'}]), false);
    assert.equal(hasSeverity([{severity: null}, {severity: 'high'}]), true);
    assert.equal(hasSeverity([]), false);
});
