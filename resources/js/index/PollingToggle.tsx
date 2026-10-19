import { router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

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
      className="lathe-button lathe-button-ghost lathe-button-size-sm lathe-polling"
      onClick={onToggle}
    >
      <RefreshCw aria-hidden="true" className="size-3.5" />
      {__('Auto refresh')}
    </button>
  );
}
