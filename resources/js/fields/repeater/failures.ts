import { createContext } from 'react';

/** Counts the form's failed saves, so a repeater scrolls to its first failing row once per save. */
export const RepeaterFailuresContext = createContext(0);
