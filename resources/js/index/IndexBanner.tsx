import { useTranslation } from '@lathephp/core';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';

interface IndexBannerProps {
  message: string;
  onReset: () => void;
}

/** A URL the server refused: its message in place of the rows, and a way back to the plain index. */
export function IndexBanner({ message, onReset }: IndexBannerProps) {
  const { __ } = useTranslation();

  return (
    <Card role="alert" className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
      <p>{message}</p>
      <Button variant="outline" size="sm" onClick={onReset}>
        {__('Reset')}
      </Button>
    </Card>
  );
}
