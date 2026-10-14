import { useEffect, useState } from 'react';

const duration = 600;

function animates(): boolean {
  return typeof window.matchMedia === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Counts up to the number when it loads; with reduced motion asked for, it is the number at once. */
export function useAnimatedNumber(target: number | null): number | null {
  const [shown, setShown] = useState(0);
  const motion = animates();

  useEffect(() => {
    if (!motion || target === null) {
      return;
    }

    const start = performance.now();
    let frame = 0;
    const step = (now: number) => {
      const progress = Math.min(1, (now - start) / duration);
      setShown(progress === 1 ? target : target * (1 - (1 - progress) ** 3));

      if (progress < 1) {
        frame = requestAnimationFrame(step);
      }
    };
    frame = requestAnimationFrame(step);

    return () => {
      cancelAnimationFrame(frame);
    };
  }, [motion, target]);

  return motion && target !== null ? shown : target;
}
