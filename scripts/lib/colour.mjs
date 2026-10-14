// Colour maths for the contrast checks: WCAG 2.x ratios of CSS colours.
import process from 'node:process';

const clamp = (n) => Math.min(1, Math.max(0, n));
const linear = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

/** A CSS colour as linear sRGB [r, g, b]. Alpha is ignored: a translucent token has no fixed backdrop. */
function toLinear(value) {
  const v = value.trim().toLowerCase();
  let m;
  if ((m = v.match(/^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?(?:\s*\/.*)?\)$/))) {
    const L = m[2] ? Number(m[1]) / 100 : Number(m[1]);
    const a = Number(m[3]) * Math.cos((Number(m[4]) * Math.PI) / 180);
    const b = Number(m[3]) * Math.sin((Number(m[4]) * Math.PI) / 180);
    const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const mm = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;
    return [
      4.0767416621 * l - 3.3077115913 * mm + 0.2309699292 * s,
      -1.2684380046 * l + 2.6097574011 * mm - 0.3413193965 * s,
      -0.0041960863 * l - 0.7034186147 * mm + 1.707614701 * s,
    ].map(clamp);
  }
  if ((m = v.match(/^#([0-9a-f]{3,8})$/))) {
    let h = m[1];
    if (h.length < 6) h = [...h].map((c) => c + c).join('');
    return [0, 2, 4].map((i) => linear(parseInt(h.slice(i, i + 2), 16) / 255));
  }
  if ((m = v.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/))) {
    return [m[1], m[2], m[3]].map((n) => linear(clamp(Number(n) / 255)));
  }
  if ((m = v.match(/^hsla?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%/))) {
    const h = Number(m[1]);
    const s = Number(m[2]) / 100;
    const l = Number(m[3]) / 100;
    const f = (n) => {
      const k = (n + h / 30) % 12;
      return l - s * Math.min(l, 1 - l) * Math.max(-1, Math.min(k - 3, 9 - k, 1));
    };
    return [f(0), f(8), f(4)].map(linear);
  }
  throw new Error(`Cannot read the colour "${value}"`);
}

const luminance = (value) => {
  const [r, g, b] = toLinear(value);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export function ratio(a, b) {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

/** Declarations of a top-level rule such as ":where(:root)" or ".dark", as {token: value}. */
export function tokens(css, selector) {
  const start = css.indexOf(`${selector} {`);
  if (start === -1) throw new Error(`css has no ${selector} block`);
  const body = css.slice(css.indexOf('{', start) + 1, css.indexOf('}', start));
  const found = {};
  for (const [, name, value] of body.matchAll(/--([\w-]+):\s*([^;]+);/g)) found[name] = value.trim();
  return found;
}

/** The tokens of each mode; dark starts from the light values, as the cascade does. */
export function modes(css) {
  const light = tokens(css, ':where(:root)');
  return { light, dark: { ...light, ...tokens(css, '.dark') } };
}

export function report(failures, checked) {
  if (failures.length === 0) {
    process.stdout.write(`${checked} pairs readable\n`);
    return 0;
  }
  process.stdout.write(`${failures.join('\n')}\n`);
  process.stdout.write(`${failures.length} of ${checked} pairs too faint\n`);
  return 1;
}
