/** A declarative rule from the server (Lathe\Field\Condition), evaluated as staff type. */
export type ConditionData =
  | { op: 'all' | 'any'; conditions: ConditionData[] }
  | { op: 'eq' | 'neq' | 'in' | 'notIn' | 'gt' | 'gte' | 'lt' | 'lte' | 'filled' | 'blank'; field: string; value: unknown };

// Form values are compared as text, as the server does: 1 equals "1", 0.4 never equals 0.
function text(value: unknown): string | null {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }

  return typeof value === 'string' || typeof value === 'number' ? String(value) : JSON.stringify(value);
}

function blank(value: unknown): boolean {
  return value === null || value === undefined || value === '' || value === false || (Array.isArray(value) && value.length === 0);
}

function numeric(value: unknown): number | null {
  if (typeof value === 'number') {
    return value;
  }

  return typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value)) ? Number(value) : null;
}

/** Whether the condition holds for these form values, keyed by attribute. */
export function passes(condition: ConditionData, values: Record<string, unknown>): boolean {
  if ('conditions' in condition) {
    return condition.op === 'all' ? condition.conditions.every((child) => passes(child, values)) : condition.conditions.some((child) => passes(child, values));
  }

  const actual = values[condition.field];

  switch (condition.op) {
    case 'eq':
      return text(actual) === text(condition.value);
    case 'neq':
      return text(actual) !== text(condition.value);
    case 'in':
    case 'notIn': {
      const listed = Array.isArray(condition.value) && condition.value.map(text).includes(text(actual));

      return condition.op === 'in' ? listed : !listed;
    }
    case 'filled':
      return !blank(actual);
    case 'blank':
      return blank(actual);
    default: {
      const left = numeric(actual);
      const right = numeric(condition.value);

      if (left === null || right === null) {
        return false;
      }

      return condition.op === 'gt' ? left > right : condition.op === 'gte' ? left >= right : condition.op === 'lt' ? left < right : left <= right;
    }
  }
}
