import { createContext } from 'react';

/** The card header's right-hand slot, for a renderer to portal a line into; null where the header shows something else. */
export const WidgetMeta = createContext<HTMLElement | null>(null);
