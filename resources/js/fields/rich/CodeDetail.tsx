import { DetailFallbackField, stringValue, type DetailFieldProps } from '../registry';
import { Collapsible, lineCount } from '../text/Collapsible';

/** The code as text in a language block: React escapes it, so markup in the value never runs. */
export default function CodeDetail({ field }: DetailFieldProps) {
  const text = typeof field.value === 'string' ? field.value : field.display || stringValue(field.value);

  if (text === '') {
    return <DetailFallbackField field={field} />;
  }

  const language = typeof field.language === 'string' && /^[a-z0-9]+$/.test(field.language) ? field.language : 'plaintext';

  return (
    <Collapsible lines={lineCount(text)}>
      <pre className="overflow-x-auto rounded-md border border-input bg-muted p-3 font-mono text-sm">
        <code className={`language-${language}`}>{text}</code>
      </pre>
    </Collapsible>
  );
}
