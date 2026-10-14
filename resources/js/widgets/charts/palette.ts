/** The colour of the nth series: the five theme chart tokens, in turn. */
export function seriesColor(index: number): string {
  return `var(--chart-${String((index % 5) + 1)})`;
}
