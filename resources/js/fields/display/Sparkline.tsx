const PAD = 2;

/** The series as numbers; anything else in it is dropped. */
export function seriesOf(value: unknown): number[] {
  return Array.isArray(value) ? value.filter((item): item is number => typeof item === 'number' && Number.isFinite(item)) : [];
}

/** A line or bar chart as inline SVG, so no chart library ships. */
export function Sparkline({ series, width, height, bars, label }: { series: number[]; width: number; height: number; bars: boolean; label: string }) {
  const max = Math.max(...series);
  // A bar chart rises from zero; a line fills the box between its lowest and highest value.
  const min = bars ? Math.min(0, ...series) : Math.min(...series);
  const ratio = (value: number) => (max === min ? 0.5 : (value - min) / (max - min));
  const inner = { width: width - PAD * 2, height: height - PAD * 2 };
  const line = series.length === 1 ? [series[0] ?? 0, series[0] ?? 0] : series;

  return (
    <svg role="img" aria-label={label} width={width} height={height} viewBox={`0 0 ${String(width)} ${String(height)}`} className="inline-block align-middle text-primary">
      {bars ? (
        series.map((value, index) => {
          const slot = inner.width / series.length;
          const barHeight = Math.max(1, ratio(value) * inner.height);

          return <rect key={index} x={PAD + index * slot + slot * 0.1} y={height - PAD - barHeight} width={slot * 0.8} height={barHeight} fill="currentColor" />;
        })
      ) : (
        <polyline
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinejoin="round"
          strokeLinecap="round"
          points={line.map((value, index) => `${String(PAD + (index * inner.width) / (line.length - 1))},${String(height - PAD - ratio(value) * inner.height)}`).join(' ')}
        />
      )}
    </svg>
  );
}
