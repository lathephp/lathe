import { useEffect, useState, type ReactNode } from 'react';

/** The fill of a skeleton bar, which pulses unless reduced motion is asked for. */
export const skeletonBar = 'animate-pulse rounded-md bg-muted motion-reduce:animate-none';

/**
 * Shows nothing for the first 100 ms, so an answer that comes quickly never flashes a skeleton.
 * An instant visit's page shows its title and the shape of its layout through it, so the old
 * page is never left looking ready.
 */
export function AfterDelay({ children }: { children: ReactNode }) {
  const [shown, setShown] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setShown(true);
    }, 100);

    return () => {
      clearTimeout(timer);
    };
  }, []);

  return shown ? children : null;
}
