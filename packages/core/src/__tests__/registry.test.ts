import { lazy } from 'react';
import { describe, expect, it } from 'vitest';
import {
  __panel,
  actionModal,
  booting,
  field,
  filter,
  page,
  plugin,
  renderHook,
  resourcePlugin,
  widget,
  type FieldProps,
  type FilterProps,
  type PageProps,
  type ResourcePluginProps,
  type WidgetProps,
} from '../index';

// The shapes the generated stubs give their components, so this file also checks they still type-check.
const FirstField = (props: { field: { attribute: string; placeholder?: string }; value: unknown; onChange: (attribute: string, value: unknown) => void }) =>
  String(props.value);
const SecondField = (props: FieldProps) => props.field.attribute;
const FirstFilter = (props: { filter: { name: string }; value: unknown; onChange: (value: unknown) => void }) => props.filter.name;
const SecondFilter = (props: FilterProps) => props.filter.name;
const FirstWidget = (props: { widget: { name: string } }) => props.widget.name;
const SecondWidget = (props: WidgetProps) => String(props.value);
const FirstPage = (props: { pageKey: string; basePath: string }) => props.pageKey;
const SecondPage = (props: PageProps) => props.basePath;
const FirstResourcePlugin = (props: { resourceName: string; resourceId: string }) => props.resourceName;
const SecondResourcePlugin = (props: ResourcePluginProps) => props.resourceId;

describe('registration', () => {
  it('keeps the last component registered for a field key', () => {
    field('color-picker', FirstField);
    field('color-picker', SecondField);

    expect(__panel.fieldComponent('color-picker')).toBe(SecondField);
  });

  it('keeps the last component registered for a filter key', () => {
    filter('color-filter', FirstFilter);
    filter('color-filter', SecondFilter);

    expect(__panel.filterComponent('color-filter')).toBe(SecondFilter);
  });

  it('keeps the last component registered for a widget key', () => {
    widget('total-users', FirstWidget);
    widget('total-users', SecondWidget);

    expect(__panel.widgetComponent('total-users')).toBe(SecondWidget);
  });

  it('keeps the last component registered for a page key', () => {
    page('reports', FirstPage);
    page('reports', SecondPage);

    expect(__panel.pageComponent('reports')).toBe(SecondPage);
  });

  it('keeps the last component registered for a resource plugin key', () => {
    resourcePlugin('audit-trail', FirstResourcePlugin);
    resourcePlugin('audit-trail', SecondResourcePlugin);

    expect(__panel.resourcePluginComponent('audit-trail')).toBe(SecondResourcePlugin);
  });

  it('pins: keeps the last page registered for a plugin and the last action modal', () => {
    const first = () => 'first';
    const second = () => 'second';
    plugin('heartbeat', first);
    plugin('heartbeat', second);
    actionModal('confirm-refund', first);
    actionModal('confirm-refund', second);

    expect(__panel.pluginPage('heartbeat')).toBe(second);
    expect(__panel.actionModalComponent('confirm-refund')).toBe(second);
  });

  it('adds render hooks for a position to a list in registration order', () => {
    const first = () => 'first';
    const second = () => 'second';
    renderHook('page:before', first);
    renderHook('page:before', second, { resources: ['users'] });

    expect(__panel.renderHooks('page:before')).toEqual([
      { component: first, resources: undefined },
      { component: second, resources: ['users'] },
    ]);
    expect(__panel.renderHooks('page:after')).toEqual([]);
  });

  it('takes a React.lazy component', () => {
    const loaded = lazy(() => Promise.resolve({ default: SecondWidget }));
    widget('lazy-widget', loaded);

    expect(__panel.widgetComponent('lazy-widget')).toBe(loaded);
  });

  it('pins: runs a booting callback registered after boot at once', () => {
    const ran: string[] = [];
    __panel.boot();
    booting(() => ran.push('late'));

    expect(ran).toEqual(['late']);
  });
});
