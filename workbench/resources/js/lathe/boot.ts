import { page, widget } from '@lathephp/core';
import Clock from './Clock';
import SystemStatus from './SystemStatus';
import UiGallery from './UiGallery';

widget('workbench-clock', Clock);
page('ui-gallery', UiGallery);
page('system-status', SystemStatus);
