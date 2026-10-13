import { describe, expect, it } from 'vitest';
import { passes, type ConditionData } from '../fields/conditions';

const rule = (op: Exclude<ConditionData['op'], 'all' | 'any'>, value: unknown = null): ConditionData => ({ op, field: 'x', value });

describe('conditions', () => {
  it.each<[string, ConditionData, unknown, boolean]>([
    ['eq', rule('eq', 'company'), 'company', true],
    ['eq compares text', rule('eq', 1), '1', true],
    ['eq 0.4 is not 0', rule('eq', 0), '0.4', false],
    ['neq', rule('neq', 'company'), 'person', true],
    ['in', rule('in', ['a', 'b']), 'b', true],
    ['notIn', rule('notIn', ['a', 'b']), 'b', false],
    ['gt', rule('gt', 5), '6', true],
    ['gte', rule('gte', 5), '5', true],
    ['lt', rule('lt', 5), '5', false],
    ['lte', rule('lte', 5), 5, true],
    ['gt on text', rule('gt', 5), 'many', false],
    ['filled', rule('filled'), 'x', true],
    ['blank', rule('blank'), '', true],
    ['blank when missing', rule('blank'), undefined, true],
  ])('%s', (_name, condition, value, expected) => {
    expect(passes(condition, { x: value })).toBe(expected);
  });

  it('joins with allOf and anyOf', () => {
    const a: ConditionData = { op: 'eq', field: 'a', value: 1 };
    const b: ConditionData = { op: 'eq', field: 'b', value: 2 };

    expect(passes({ op: 'all', conditions: [a, b] }, { a: 1, b: 3 })).toBe(false);
    expect(passes({ op: 'any', conditions: [a, b] }, { a: 0, b: 2 })).toBe(true);
  });
});
