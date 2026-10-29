import { Suspense, useRef, useState, type ReactNode } from 'react';
import { lazyNamed } from '../../lib/lazy';

// The card loads on the first hover, which waits 300 ms and a request anyway, not with the index.
const PeekCard = lazyNamed(() => import('./PeekCard').then((module) => module.PeekCard));

const cardWidth = 288;

// Long enough that a pointer passing over a row opens nothing.
const hoverDelayMs = 300;

/** Wraps a related value so hovering it opens a card of the related record's peek fields. */
export function RelationPeek({ uriKey, id, children }: { uriKey: string; id: string | number; children: ReactNode }) {
  const [position, setPosition] = useState<{ top: number; left: number } | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  return (
    <span
      className="inline-block"
      onMouseEnter={(event) => {
        const trigger = event.currentTarget;
        timer.current = setTimeout(() => {
          const rect = trigger.getBoundingClientRect();
          // The card is 288 px wide; it stays inside a narrow viewport with an 8 px margin.
          setPosition({ top: rect.bottom + 4, left: Math.max(8, Math.min(rect.left, window.innerWidth - cardWidth - 8)) });
        }, hoverDelayMs);
      }}
      onMouseLeave={() => {
        clearTimeout(timer.current);
        setPosition(null);
      }}
    >
      {children}
      {position !== null && (
        <Suspense fallback={null}>
          <PeekCard uriKey={uriKey} id={id} top={position.top} left={position.left} />
        </Suspense>
      )}
    </span>
  );
}
