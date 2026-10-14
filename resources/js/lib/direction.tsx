import { createContext, useContext, type ReactNode } from 'react';

export type Direction = 'ltr' | 'rtl';

const DirectionContext = createContext<Direction>('ltr');

/** Tells the panel's components which way text runs, from the server's rtl prop. */
export function DirectionProvider({ direction, children }: { direction: Direction; children: ReactNode }) {
  return <DirectionContext.Provider value={direction}>{children}</DirectionContext.Provider>;
}

export function useDirection(): Direction {
  return useContext(DirectionContext);
}
