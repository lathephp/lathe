/** The browser tab title: the page first, then the app, so tabs stay tellable apart. */
export function pageTitle(title: string, appName: string): string {
  return title === '' ? appName : `${title} · ${appName}`;
}
