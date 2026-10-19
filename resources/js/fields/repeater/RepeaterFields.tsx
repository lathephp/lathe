import { useTranslation } from '@lathephp/core';
import { ChevronDown, ChevronRight, ChevronUp, Copy, GripVertical, Plus, Trash2 } from 'lucide-react';
import { useContext, useEffect, useId, useRef, useState, type DragEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { Modal } from '../../components/Modal';
import { cn } from '../../lib/cn';
import { DetailField, FormField, stringValue, type DetailFieldProps, type FormFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { RepeaterErrorsContext, RepeaterPathContext } from './errors';
import { RepeaterFailuresContext } from './failures';

interface Block {
  key: string;
  label: string;
  icon: string | null;
  confirmRemoval: boolean;
  fields: FieldSchema[];
}

interface RowLabel {
  field: string | null;
  fallback: string | null;
}

interface RepeaterConfig {
  mode: 'fields' | 'simple' | 'repeatables';
  fields: FieldSchema[];
  blocks: Block[];
  reorderable: boolean;
  addable: boolean;
  deletable: boolean;
  collapsible: boolean;
  collapsed: boolean;
  cloneable: boolean;
  itemNumbers: boolean;
  confirmRemoval: boolean;
  grid: number | null;
  addButtonText: string | null;
  rowLabel: RowLabel | null;
  max: number | null;
}

type Translate = ReturnType<typeof useTranslation>['__'];

// Whole class names, so Tailwind finds them in the source.
const GRID: Record<number, string> = {
  1: 'md:grid-cols-1',
  2: 'md:grid-cols-2',
  3: 'md:grid-cols-3',
  4: 'md:grid-cols-4',
  5: 'md:grid-cols-5',
  6: 'md:grid-cols-6',
};

let lastRowId = 0;

/** A key that follows a row while it moves; never sent to the server. */
function newRowId(): string {
  lastRowId += 1;

  return `row-${String(lastRowId)}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function config(field: FieldSchema): RepeaterConfig {
  const label = field.rowLabel;

  return {
    mode: field.mode === 'simple' || field.mode === 'repeatables' ? field.mode : 'fields',
    fields: Array.isArray(field.fields) ? (field.fields as FieldSchema[]) : [],
    blocks: Array.isArray(field.blocks) ? (field.blocks as Block[]) : [],
    reorderable: field.reorderable !== false,
    addable: field.addable !== false,
    deletable: field.deletable !== false,
    collapsible: field.collapsible === true || field.collapsed === true,
    collapsed: field.collapsed === true,
    cloneable: field.cloneable === true,
    itemNumbers: field.itemNumbers === true,
    confirmRemoval: field.confirmRemoval === true,
    grid: typeof field.grid === 'number' ? field.grid : null,
    addButtonText: typeof field.addButtonText === 'string' ? field.addButtonText : null,
    rowLabel: isRecord(label) ? { field: typeof label.field === 'string' ? label.field : null, fallback: typeof label.fallback === 'string' ? label.fallback : null } : null,
    max: typeof field.max === 'number' ? field.max : null,
  };
}

function blockOf(cfg: RepeaterConfig, row: unknown): Block | undefined {
  return isRecord(row) ? cfg.blocks.find((block) => block.key === row.type) : undefined;
}

/** The fields one row draws: the repeater's, or those of the row's block. */
function rowFields(cfg: RepeaterConfig, row: unknown): FieldSchema[] {
  return cfg.mode === 'repeatables' ? (blockOf(cfg, row)?.fields ?? []) : cfg.fields;
}

function emptyRow(cfg: RepeaterConfig, block?: Block): unknown {
  if (cfg.mode === 'simple') {
    return null;
  }

  const values = Object.fromEntries((block?.fields ?? cfg.fields).map((field) => [field.attribute, null]));

  return block === undefined ? values : { type: block.key, ...values };
}

function cellOf(cfg: RepeaterConfig, row: unknown, field: FieldSchema): unknown {
  if (cfg.mode === 'simple') {
    return row ?? null;
  }

  return isRecord(row) ? (row[field.attribute] ?? null) : null;
}

/** The row heading: the server label, the live sub-field, the template, then Row #N. */
function rowTitle(cfg: RepeaterConfig, row: unknown, index: number, __: Translate): string {
  let title: string | null = null;

  if (isRecord(row)) {
    const live = cfg.rowLabel?.field == null ? undefined : row[cfg.rowLabel.field];

    if (typeof row._label === 'string' && row._label !== '') {
      title = row._label;
    } else if ((typeof live === 'string' && live !== '') || typeof live === 'number') {
      title = String(live);
    }
  }

  const fallback = cfg.rowLabel?.fallback;
  title ??= fallback == null ? __('Row #:index', { index: index + 1 }) : fallback.replaceAll('{{index}}', String(index + 1));

  return cfg.itemNumbers ? `${String(index + 1)}. ${title}` : title;
}

/** The first message under a key or below it (a translatable sub-field keys by locale). */
function errorAt(errors: Record<string, string>, key: string): string | undefined {
  return errors[key] ?? Object.entries(errors).find(([name]) => name.startsWith(`${key}.`))?.[1];
}

export function RepeaterForm({ field, value, onChange, id, describedBy, disabled }: FormFieldProps) {
  const { __ } = useTranslation();
  const cfg = config(field);
  const errors = useContext(RepeaterErrorsContext);
  const failures = useContext(RepeaterFailuresContext);
  const parent = useContext(RepeaterPathContext);
  const path = parent === '' ? field.attribute : `${parent}.${field.attribute}`;
  const rows: unknown[] = Array.isArray(value) ? value : [];
  const [ids, setIds] = useState<string[]>(() => rows.map(() => newRowId()));
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [pending, setPending] = useState<number | null>(null);
  const [dragging, setDragging] = useState<number | null>(null);
  const firstFailing = useRef<HTMLLIElement>(null);
  const confirmTitle = useId();
  const keys = rows.map((_, index) => ids[index] ?? `row-at-${String(index)}`);
  const failing = rows.map((_, index) => errorAt(errors, `${path}.${String(index)}`) !== undefined);
  const firstFailingIndex = failing.indexOf(true);

  // A failed save brings the first failing row into view; failing rows stay open. Fixing a
  // field changes the errors too, and must not pull the page back here.
  useEffect(() => {
    firstFailing.current?.scrollIntoView({ block: 'center' });
  }, [failures]);

  function commit(next: unknown[], nextIds: string[]) {
    setIds(nextIds);
    onChange(next);
  }

  function add(block?: Block) {
    commit([...rows, emptyRow(cfg, block)], [...keys, newRowId()]);
  }

  function removeAt(index: number) {
    commit(
      rows.filter((_, at) => at !== index),
      keys.filter((_, at) => at !== index),
    );
  }

  function requestRemoval(index: number) {
    if (cfg.confirmRemoval || blockOf(cfg, rows[index])?.confirmRemoval === true) {
      setPending(index);
    } else {
      removeAt(index);
    }
  }

  function cloneAt(index: number) {
    const row = rows[index];
    // The copy is a new row: it carries no server key or server label.
    const copy: unknown = isRecord(row) ? Object.fromEntries(Object.entries(row).filter(([name]) => name !== '_key' && name !== '_label')) : row;

    commit([...rows, JSON.parse(JSON.stringify(copy ?? null)) as unknown], [...keys, newRowId()]);
  }

  function move(from: number, to: number) {
    if (from === to || to < 0 || to >= rows.length) {
      return;
    }

    const nextRows = [...rows];
    const nextIds = [...keys];
    nextRows.splice(to, 0, ...nextRows.splice(from, 1));
    nextIds.splice(to, 0, ...nextIds.splice(from, 1));
    commit(nextRows, nextIds);
  }

  function setCell(index: number, sub: FieldSchema, cell: unknown) {
    const row = rows[index];
    const next = cfg.mode === 'simple' ? cell : { ...(isRecord(row) ? row : {}), [sub.attribute]: cell };

    onChange(rows.map((current, at) => (at === index ? next : current)));
  }

  function onDragStart(event: DragEvent, index: number) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(index));
    setDragging(index);
  }

  function onDrop(event: DragEvent, index: number) {
    event.preventDefault();

    if (dragging !== null) {
      move(dragging, index);
    }

    setDragging(null);
  }

  const full = cfg.max !== null && rows.length >= cfg.max;
  const addButton = 'inline-flex h-8 items-center gap-1 rounded-md border border-input px-3 text-sm disabled:opacity-60';
  const iconButton = 'inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted disabled:opacity-40';

  return (
    <div id={id} role="group" aria-describedby={describedBy} className="space-y-2">
      <ol data-repeater-rows data-compact={field.compact || undefined} className={cn(field.compact ? 'space-y-1' : 'space-y-2')}>
        {rows.map((row, index) => {
          const key = keys[index] ?? String(index);
          const expanded = !cfg.collapsible || failing[index] === true || (open[key] ?? !cfg.collapsed);
          const rowPath = `${path}.${String(index)}`;
          const block = blockOf(cfg, row);

          return (
            <li
              key={key}
              ref={index === firstFailingIndex ? firstFailing : undefined}
              data-repeater-row
              data-invalid={failing[index] === true || undefined}
              onDragOver={(event) => {
                if (dragging !== null) {
                  event.preventDefault();
                }
              }}
              onDrop={(event) => {
                onDrop(event, index);
              }}
              className={cn('rounded-md border', failing[index] === true ? 'border-destructive' : 'border-border', dragging === index && 'opacity-50')}
            >
              <div className={cn('flex items-center gap-1 border-b border-border', field.compact ? 'px-1 py-0.5' : 'px-2 py-1')}>
                {cfg.reorderable ? (
                  <button
                    type="button"
                    draggable={!disabled}
                    disabled={disabled}
                    aria-label={__('Drag to reorder')}
                    onDragStart={(event) => {
                      onDragStart(event, index);
                    }}
                    onDragEnd={() => {
                      setDragging(null);
                    }}
                    className={cn(iconButton, 'cursor-grab')}
                  >
                    <GripVertical aria-hidden="true" className="size-4" />
                  </button>
                ) : null}
                {cfg.collapsible ? (
                  <button
                    type="button"
                    aria-expanded={expanded}
                    aria-label={expanded ? __('Collapse') : __('Expand')}
                    onClick={() => {
                      setOpen({ ...open, [key]: !expanded });
                    }}
                    className={iconButton}
                  >
                    {expanded ? <ChevronDown aria-hidden="true" className="size-4" /> : <ChevronRight aria-hidden="true" className="size-4 rtl:rotate-180" />}
                  </button>
                ) : null}
                <span data-row-title className="min-w-0 flex-1 truncate text-sm font-medium">
                  {rowTitle(cfg, row, index, __)}
                </span>
                {block === undefined || cfg.mode !== 'repeatables' ? null : <span className="text-xs text-muted-foreground">{block.label}</span>}
                {cfg.reorderable ? (
                  <>
                    <button
                      type="button"
                      aria-label={__('Move up')}
                      disabled={disabled || index === 0}
                      onClick={() => {
                        move(index, index - 1);
                      }}
                      className={iconButton}
                    >
                      <ChevronUp aria-hidden="true" className="size-4" />
                    </button>
                    <button
                      type="button"
                      aria-label={__('Move down')}
                      disabled={disabled || index === rows.length - 1}
                      onClick={() => {
                        move(index, index + 1);
                      }}
                      className={iconButton}
                    >
                      <ChevronDown aria-hidden="true" className="size-4" />
                    </button>
                  </>
                ) : null}
                {cfg.cloneable ? (
                  <button
                    type="button"
                    aria-label={__('Clone')}
                    disabled={disabled || full}
                    onClick={() => {
                      cloneAt(index);
                    }}
                    className={iconButton}
                  >
                    <Copy aria-hidden="true" className="size-4" />
                  </button>
                ) : null}
                {cfg.deletable ? (
                  <button
                    type="button"
                    aria-label={__('Remove')}
                    disabled={disabled}
                    onClick={() => {
                      requestRemoval(index);
                    }}
                    className={iconButton}
                  >
                    <Trash2 aria-hidden="true" className="size-4" />
                  </button>
                ) : null}
              </div>
              {expanded ? (
                <div data-row-body className={cn('grid gap-3', cfg.grid === null ? null : GRID[cfg.grid], field.compact ? 'p-2' : 'p-3')}>
                  <RepeaterPathContext value={rowPath}>
                    {rowFields(cfg, row).map((sub) => {
                      const subId = `${id}-${String(index)}-${sub.attribute}`;
                      const error = errorAt(errors, cfg.mode === 'simple' ? rowPath : `${rowPath}.${sub.attribute}`);
                      const cell = cellOf(cfg, row, sub);

                      return (
                        <div key={sub.attribute} className="min-w-0 space-y-1">
                          <label htmlFor={subId} className="text-xs font-medium">
                            {sub.label}
                          </label>
                          <FormField
                            field={{ ...sub, value: cell }}
                            value={cell}
                            onChange={(next) => {
                              setCell(index, sub, next);
                            }}
                            id={subId}
                            describedBy={error === undefined ? undefined : `${subId}-error`}
                            invalid={error !== undefined}
                            disabled={disabled || sub.readonly === true}
                          />
                          <FieldError id={`${subId}-error`} message={error} />
                        </div>
                      );
                    })}
                  </RepeaterPathContext>
                </div>
              ) : null}
            </li>
          );
        })}
      </ol>
      {cfg.addable ? (
        <div className="flex flex-wrap gap-2">
          {cfg.mode === 'repeatables' ? (
            cfg.blocks.map((block) => (
              <button
                key={block.key}
                type="button"
                disabled={disabled || full}
                onClick={() => {
                  add(block);
                }}
                className={addButton}
              >
                <Plus aria-hidden="true" className="size-4" />
                {__('Add :block', { block: block.label })}
              </button>
            ))
          ) : (
            <button
              type="button"
              disabled={disabled || full}
              onClick={() => {
                add();
              }}
              className={addButton}
            >
              <Plus aria-hidden="true" className="size-4" />
              {cfg.addButtonText ?? __('Add row')}
            </button>
          )}
        </div>
      ) : null}
      {pending === null ? null : (
        <Modal
          labelledBy={confirmTitle}
          onClose={() => {
            setPending(null);
          }}
        >
          <h2 id={confirmTitle} className="text-base font-semibold">
            {__('Remove this row?')}
          </h2>
          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => {
                setPending(null);
              }}
              className="lathe-button lathe-button-outline lathe-button-size-default"
            >
              {__('Cancel')}
            </button>
            <button
              type="button"
              onClick={() => {
                removeAt(pending);
                setPending(null);
              }}
              className="lathe-button lathe-button-size-default bg-destructive text-destructive-foreground shadow-xs hover:brightness-92"
            >
              {__('Remove')}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

export function RepeaterDetail({ field }: DetailFieldProps) {
  const { __ } = useTranslation();
  const cfg = config(field);
  const rows: unknown[] = Array.isArray(field.value) ? field.value : [];

  if (rows.length === 0) {
    return <span className="text-muted-foreground">—</span>;
  }

  return (
    <ol className="space-y-2">
      {rows.map((row, index) => (
        // Detail rows never move, so the position is a stable key.
        <li key={index} data-repeater-row className="space-y-2 rounded-md border border-border p-3">
          <p data-row-title className="text-sm font-medium">
            {rowTitle(cfg, row, index, __)}
          </p>
          <dl className={cn('grid gap-2', cfg.grid === null ? null : GRID[cfg.grid])}>
            {rowFields(cfg, row).map((sub) => {
              const cell = cellOf(cfg, row, sub);

              return (
                <div key={sub.attribute} className="min-w-0">
                  <dt className="text-xs text-muted-foreground">{sub.label}</dt>
                  <dd>
                    <DetailField field={{ ...sub, value: cell, display: stringValue(cell) }} />
                  </dd>
                </div>
              );
            })}
          </dl>
        </li>
      ))}
    </ol>
  );
}
