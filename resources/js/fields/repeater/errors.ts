import { createContext } from 'react';

/**
 * The form's validation messages keyed by their full input key (`specifications.0.name`),
 * so a repeater can mark, open and scroll to the rows that failed.
 */
export const RepeaterErrorsContext = createContext<Record<string, string>>({});

/** The key of the row a nested repeater sits in (`tree.0`); empty at the top of the form. */
export const RepeaterPathContext = createContext('');
