import { startStimulusApp } from '@symfony/stimulus-bundle';
import ReportBusFinderController from './controllers/report_bus_finder_controller.js';

const app = startStimulusApp();
app.register('report-bus-finder', ReportBusFinderController);
