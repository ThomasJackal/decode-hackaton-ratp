import { startStimulusApp } from '@symfony/stimulus-bundle';
import ReportBusFinderController from './controllers/report_bus_finder_controller.js';
import ReportQrCaptureController from './controllers/report_qr_capture_controller.js';
import ReportListRowsController from './controllers/report_list_rows_controller.js';

const app = startStimulusApp();
app.register('report-bus-finder', ReportBusFinderController);
app.register('report-qr-capture', ReportQrCaptureController);
app.register('report-list-rows', ReportListRowsController);
