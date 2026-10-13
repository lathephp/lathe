import { plugin, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Badge } from '@lathephp/ui';
import { useCallback, useEffect, useState, type ReactNode } from 'react';

interface Status {
  app: string;
  php: string;
  laravel: string;
  database: string;
  time: string;
}

const empty: Status = { app: '', php: '', laravel: '', database: '', time: '' };

function Page() {
  const { apiPath } = useLathe();
  const { __ } = useTranslation();
  const [status, setStatus] = useState<Status>(empty);
  const url = `${apiPath}/plugins/heartbeat/status`;

  // Answers whether the status loaded. A failed request means the app or its database is down.
  // A promise chain, not try/catch: the React Compiler skips a component with ?? inside try.
  const load = useCallback(
    () =>
      request<Status>(url).then(
        (next) => {
          setStatus(next ?? empty);

          return true;
        },
        () => {
          setStatus((current) => ({ ...current, database: 'down' }));

          return false;
        },
      ),
    [url],
  );

  async function ping() {
    if (await load()) {
      toast.success(__('Heartbeat OK'));
    } else {
      toast.error(__('Heartbeat failed'));
    }
  }

  useEffect(() => {
    void load();
  }, [load]);

  const rows: [string, ReactNode][] = [
    [__('Application'), status.app],
    [__('PHP'), status.php],
    [__('Laravel'), status.laravel],
    [__('Database'), <Badge>{status.database}</Badge>],
    [__('Checked at'), status.time],
  ];

  return (
    <div>
      <h1>{__('Heartbeat')}</h1>
      <dl>
        {rows.map(([label, value]) => (
          <div key={label}>
            <dt>{label}</dt>
            <dd>{value}</dd>
          </div>
        ))}
      </dl>
      <button type="button" onClick={() => void ping()}>
        {__('Ping')}
      </button>
    </div>
  );
}

plugin('heartbeat', Page);
