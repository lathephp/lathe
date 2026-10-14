import './lathe.css';
import './renderHooks';
import './demoLogin';
import './fields/StarRating';
import './filters/PriceTier';
import { page, plugin, widget } from '@lathephp/core';
import Clock from './Clock';
import Overview from './Overview';
import SystemStatus from './pages/SystemStatus';
import UiGallery from './pages/UiGallery';

widget('workbench-clock', Clock);
page('ui-gallery', UiGallery);
page('system-status', SystemStatus);
plugin('insights', Overview);
