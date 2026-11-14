import {viewRegistry} from 'sulu-admin-bundle/containers';
import Dashboard from './Dashboard';
import widgetRegistry from './widgetRegistry';
import Activities from './widgets/Activities';
import OpenItems from './widgets/OpenItems';
import Translations from './widgets/Translations';
import Visitors from './widgets/Visitors';

viewRegistry.add('sulu_dashboard.dashboard', Dashboard);

// the custom tiles of this bundle use the same registry as the tiles of other bundles
widgetRegistry.add('sulu_open_items', OpenItems);
widgetRegistry.add('sulu_translations', Translations);
widgetRegistry.add('sulu_activities', Activities);
// shown only when the log statistics are installed (VisitorsWidget on the server)
widgetRegistry.add('sulu_visitors', Visitors);

export {widgetRegistry};
