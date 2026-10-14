import type { WidgetProps } from '@lathephp/core';
import { Card } from '@lathephp/ui';

/** A card with the server time its PHP widget sends as the value. */
export default function Clock({ widget: card, value }: WidgetProps) {
  return (
    <Card className="p-6">
      <h3 className="font-semibold leading-none tracking-tight">{card.name}</h3>
      <time className="mt-2 block text-2xl tabular-nums">{String(value)}</time>
    </Card>
  );
}
