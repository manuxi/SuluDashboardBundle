import {viewRegistry} from 'sulu-admin-bundle/containers';
import Dashboard from './Dashboard';
import widgetRegistry from './widgetRegistry';
import Activities from './widgets/Activities';
import OpenItems from './widgets/OpenItems';
import Translations from './widgets/Translations';
import SystemStorage from './widgets/SystemStorage';
import SystemVulnerabilities from './widgets/SystemVulnerabilities';
import Visitors from './widgets/Visitors';
import VisitorTile from './widgets/VisitorTile';
import Redirect from './Redirect';
import {METRIC_NAMES, widgetKey} from './logStats';

viewRegistry.add('sulu_dashboard.dashboard', Dashboard);
// stands on the path of a view that a tab has replaced (for example /log-stats) and opens the tab
viewRegistry.add('sulu_dashboard.redirect', Redirect);

// the custom tiles of this bundle use the same registry as the tiles of other bundles
widgetRegistry.add('sulu_open_items', OpenItems);
widgetRegistry.add('sulu_translations', Translations);
widgetRegistry.add('sulu_activities', Activities);
// shown only when the log statistics are installed (VisitorsWidget on the server)
widgetRegistry.add('sulu_visitors', Visitors);
// the tiles of the tab "Visitors" (group visitors): one component, the metric comes from the server
METRIC_NAMES.forEach((metric) => widgetRegistry.add(widgetKey(metric), VisitorTile));

// the table of vulnerabilities and the storage of the tab "System" (the other tiles of the tab are lists)
widgetRegistry.add('sulu_system_vulnerabilities', SystemVulnerabilities);
widgetRegistry.add('sulu_system_storage', SystemStorage);

export {widgetRegistry};
