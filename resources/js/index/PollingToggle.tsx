import { router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '../lib/cn';

function storageKey(uriKey: string): string {
  return `lathe-polling:${uriKey}`;
}

// Storage throws in a private window or with site data blocked; polling then stays on.
function storedChoice(uriKey: string): boolean {
  try {
    return localStorage.getItem(storageKey(uriKey)) !== 'off';
  } catch {
    return true;
  }
}

/** The toolbar toggle's state, remembered per Resource; on until someone turns it off. */
export function usePollingChoice(uriKey: string) {
  const [on, setOn] = useState(() => storedChoice(uriKey));

  function onToggle() {
    setOn(!on);

    try {
      localStorage.setItem(storageKey(uriKey), on ? 'off' : 'on');
    } catch {
      // The choice then lasts for this page only.
    }
  }

  return { on, onToggle };
}

/**
 * Reloads only the table props every `seconds`, so the URL, the scroll and the ticked rows stay.
 * A hidden browser tab stops the timer; showing the tab again starts a new interval.
 */
export function usePolling(seconds: number | null, on: boolean) {
  const [hidden, setHidden] = useState(() => document.hidden);

  useEffect(() => {
    const listener = () => {
      setHidden(document.hidden);
    };
    document.addEventListener('visibilitychange', listener);

    return () => {
      document.removeEventListener('visibilitychange', listener);
    };
  }, []);

  useEffect(() => {
    if (!seconds || !on || hidden) {
      return;
    }

    const timer = setInterval(() => {
      router.reload({ only: ['table'] });
    }, seconds * 1000);

    return () => {
      clearInterval(timer);
    };
  }, [seconds, on, hidden]);
}

export function PollingToggle({ on, onToggle }: { on: boolean; onToggle: () => void }) {
  const { __ } = useTranslation();

  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      className={cn(
        'inline-flex h-8 items-center gap-2 rounded-md border border-border px-3 text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring',
        on ? 'text-foreground' : 'text-muted-foreground',
      )}
      onClick={onToggle}
    >
      <RefreshCw aria-hidden="true" className={cn('size-4', on && 'text-primary')} />
      {__('Auto refresh')}
    </button>
  );
}
