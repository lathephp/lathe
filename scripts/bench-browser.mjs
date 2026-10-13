// The browser budgets B5 and B6, run in Playwright's Chromium against the served workbench.
//
// B5: /lathe/bench/table (a workbench-only route) renders a 100-row by 20-text-column fixture
// through the production Resources/Index page. Each run reloads only the table prop through
// the panel's router with a new run number, so the server answers with new row ids and the
// table mounts 100 fresh rows. The clock starts on Inertia's inertia:beforeUpdate event,
// after the response is parsed and before the page swap asks React to render, so the
// network is left out. It stops in the first requestAnimationFrame after the commit put the
// new rows in the DOM. 3 warm-up and 20 timed runs; the result is the 19th-ranked value.
//
// B6: Orders at 412 x 823 with 4x CPU slowdown, 1.6 Mbps down, 750 Kbps up and 150 ms
// latency. Ten rounds of a Total header click, typing in the search box, opening and
// closing Filters and ticking the first row's checkbox; INP is read with web-vitals' onINP.
// The sign-in and the first Orders load run unthrottled.
//
// The browser context bypasses the panel's CSP, because the workbench sends a nonce policy
// that refuses the injected web-vitals script.
//
// Usage: pnpm bench:browser. LATHE_BENCH_URL names a running workbench; without it the
// script serves the workbench itself on a free port. LATHE_BENCH_RESULTS and LATHE_BENCH_RUN
// name the result file, as for the Pest budget group. Exits 1 when a budget is missed.
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { cpus, tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import process from 'node:process';
import { setTimeout } from 'node:timers/promises';
import { fileURLToPath, URL } from 'node:url';
import { chromium, devices } from 'playwright';

/* global document, fetch, MutationObserver, performance, requestAnimationFrame, window */

const root = fileURLToPath(new URL('..', import.meta.url));
const budgets = { table: 50, inp: 200 };

function freePort() {
  return new Promise((resolve, reject) => {
    const server = createServer();
    server.on('error', reject);
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close(() => {
        resolve(port);
      });
    });
  });
}

async function serve() {
  const port = await freePort();
  const child = spawn('php', ['vendor/bin/testbench', 'serve', `--port=${String(port)}`], { cwd: root, detached: true, stdio: 'ignore' });
  const url = `http://127.0.0.1:${String(port)}`;

  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      await fetch(`${url}/lathe/login`);

      return { url, stop: () => process.kill(-child.pid) };
    } catch {
      await setTimeout(100);
    }
  }

  process.kill(-child.pid);
  throw new Error(`The workbench did not answer on ${url} within 10 seconds.`);
}

async function signIn(page, url) {
  await page.goto(`${url}/lathe/login`);
  await page.getByLabel('Email').fill('admin@lathephp.com');
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL(`${url}/lathe`);
}

async function tableRender(browser, url) {
  const context = await browser.newContext({ ...devices['Desktop Chrome'], bypassCSP: true });
  const page = await context.newPage();
  await signIn(page, url);
  await page.goto(`${url}/lathe/bench/table`);
  await page.locator('table tbody tr').first().waitFor();

  const times = await page.evaluate(async (runs) => {
    const { router } = await import('@lathephp/core');
    const measured = [];

    for (let run = 1; run <= runs; run++) {
      measured.push(
        await new Promise((resolve) => {
          let start = 0;

          document.addEventListener(
            'inertia:beforeUpdate',
            () => {
              start = performance.now();
            },
            { once: true },
          );

          // The commit and its DOM writes run in one task; this callback runs right after it.
          const observer = new MutationObserver(() => {
            const cell = document.querySelector('table tbody tr:first-child td:nth-child(2)');

            if (start > 0 && cell?.textContent.startsWith(`r${String(run)}-`)) {
              observer.disconnect();
              requestAnimationFrame(() => {
                resolve(performance.now() - start);
              });
            }
          });
          observer.observe(document.body, { childList: true, subtree: true, characterData: true });
          router.reload({ only: ['table'], data: { run } });
        }),
      );
    }

    return measured;
  }, 23);

  await context.close();

  // The first 3 are warm-up.
  return times.slice(3).sort((a, b) => a - b)[18];
}

async function interactionToNextPaint(browser, url) {
  const context = await browser.newContext({ viewport: { width: 412, height: 823 }, isMobile: true, bypassCSP: true });
  // One script: Playwright runs each init script in its own scope, so the IIFE's webVitals
  // global is not visible to a second one.
  const webVitals = readFileSync(join(root, 'node_modules/web-vitals/dist/web-vitals.iife.js'), 'utf8');
  await context.addInitScript(
    `${webVitals}\nwindow.latheInp = null;\nwebVitals.onINP((metric) => { window.latheInp = metric.value; }, { reportAllChanges: true });`,
  );

  const page = await context.newPage();
  await signIn(page, url);
  await page.goto(`${url}/lathe/resources/orders`);
  const rows = page.locator('table tbody tr:not([data-skeleton]) input[type=checkbox]');
  await rows.first().waitFor();

  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false,
    latency: 150,
    downloadThroughput: (1.6 * 1024 * 1024) / 8,
    uploadThroughput: (750 * 1024) / 8,
  });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  // Wait for the visit an interaction started, then for the real rows to replace the skeleton.
  async function settled(changed) {
    await page.waitForURL(changed);
    await page.waitForLoadState('networkidle');
    await rows.first().waitFor();
  }

  const search = page.getByLabel('Search orders');

  for (let round = 0; round < 10; round++) {
    const sortBefore = new URL(page.url()).searchParams.get('sort');
    await page.getByRole('button', { name: 'Total' }).click();
    await settled((next) => next.searchParams.get('sort') !== sortBefore);

    // Two terms by turns, so every round changes the URL; both match orders.
    const term = round % 2 === 0 ? 'ORD-' : 'ORD-0';
    await search.clear();
    await search.pressSequentially(term, { delay: 100 });
    await settled((next) => next.searchParams.get('q') === term);

    const filters = page.getByRole('button', { name: 'Filters' });
    await filters.click();
    await page.waitForLoadState('networkidle');
    await filters.click();

    await rows.first().click();
  }

  // A frame and a task after the last tick, so web-vitals has seen its event entry.
  const inp = await page.evaluate(
    () =>
      new Promise((resolve) => {
        requestAnimationFrame(() => {
          window.setTimeout(() => {
            resolve(window.latheInp);
          }, 0);
        });
      }),
  );
  await context.close();

  if (typeof inp !== 'number') {
    throw new Error('web-vitals reported no INP for the Orders interactions.');
  }

  return inp;
}

function record(metrics, browserVersion) {
  const folder = process.env.LATHE_BENCH_RESULTS || join(tmpdir(), 'lathe-bench');
  const stamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').slice(0, 15);
  const path = join(folder, `${process.env.LATHE_BENCH_RUN || `${stamp}-${String(process.pid)}`}.json`);
  // The Pest budget group writes the full machine header when it runs first.
  const run = existsSync(path)
    ? JSON.parse(readFileSync(path, 'utf8'))
    : {
        header: {
          date: new Date().toISOString(),
          cpu_model: cpus()[0]?.model ?? null,
          cores: cpus().length,
          versions: { node: process.version, chromium: browserVersion },
        },
        metrics: [],
      };
  const names = metrics.map((metric) => metric.name);
  run.metrics = [...run.metrics.filter((metric) => !names.includes(metric.name)), ...metrics];

  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, `${JSON.stringify(run, null, 4)}\n`);

  return path;
}

const server = process.env.LATHE_BENCH_URL ? { url: process.env.LATHE_BENCH_URL.replace(/\/+$/, ''), stop: () => undefined } : await serve();
const browser = await chromium.launch();

try {
  const metrics = [
    { name: 'B5 table render 19th of 20', value: await tableRender(browser, server.url), unit: 'ms', budget: budgets.table },
    { name: 'B6 INP', value: await interactionToNextPaint(browser, server.url), unit: 'ms', budget: budgets.inp },
  ];
  const path = record(metrics, browser.version());
  let over = false;

  for (const metric of metrics) {
    const within = metric.value <= metric.budget;
    over ||= !within;
    process.stdout.write(`${metric.name}: ${metric.value.toFixed(1)} ms (budget ${String(metric.budget)} ms) ${within ? 'ok' : 'OVER'}\n`);
  }

  process.stdout.write(`results: ${path}\n`);
  process.exitCode = over ? 1 : 0;
} finally {
  await browser.close();
  server.stop();
}
