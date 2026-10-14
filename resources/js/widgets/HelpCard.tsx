import type { WidgetRendererProps } from './types';

/** Static text from HelpWidget::content(), kept as text: line breaks show, tags stay literal. */
export function HelpCard({ widget }: WidgetRendererProps) {
  return <p className="text-sm whitespace-pre-wrap text-muted-foreground">{typeof widget.content === 'string' ? widget.content : ''}</p>;
}
