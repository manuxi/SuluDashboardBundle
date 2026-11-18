// The shared store of the tiles of the tab "Visitors": one request per period for all tiles (logStats.js,
// createStatisticsStore). The dashboard mounts all tiles at once, so without it every tile would ask for the same answer.
import {Requester} from 'sulu-admin-bundle/services';
import {createStatisticsStore} from './logStats';

export default createStatisticsStore({request: (url) => Requester.get(url)});
