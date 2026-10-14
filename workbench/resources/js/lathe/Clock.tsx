import { useEffect, useState } from 'react';

/** The viewer's time, ticking each second. The widget card draws the title, so this draws only the body. */
export default function Clock() {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const timer = setInterval(() => {
      setNow(new Date());
    }, 1000);

    return () => {
      clearInterval(timer);
    };
  }, []);

  return (
    <time dateTime={now.toISOString()} className="block text-2xl tabular-nums">
      {now.toLocaleTimeString()}
    </time>
  );
}
