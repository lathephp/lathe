// Fails raw UI that bypasses the kit: a lowercase <button>, <input>, <select> or <textarea>
// (an <input type="hidden"> is allowed), and a raw palette colour such as bg-gray-100 or
// text-[#ff0000] in a class string. The kit parts and the theme tokens carry focus rings,
// disabled states, dark mode and the panel's brand, so a raw element or colour drifts from them.
// Use the part from @lathephp/ui (or the panel's resources/js/components/ui copy) and a theme
// token such as bg-muted or text-destructive. The kit itself is exempted by the config's files
// and ignores, so the rule has no path logic. An exception is an eslint-disable-next-line with a reason.
// A class string is a string in a className or class attribute, or an argument to cn, clsx or cva.
const ELEMENTS = new Set(['button', 'input', 'select', 'textarea']);
const CLASS_CALLS = new Set(['cn', 'clsx', 'cva']);
const CLASS_ATTRIBUTES = new Set(['className', 'class']);

const UTILITY = 'bg|text|border(?:-[xytrbse])?|ring-offset|ring|outline|fill|stroke|from|via|to|divide|decoration|shadow|accent|caret|placeholder';
const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
const RAW_COLOUR = new RegExp(
  `^(?:${UTILITY})-(?:(?:${PALETTE})-(?:50|[1-9]00|950)|white|black|\\[#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\\])$`,
);

// Drops everything up to the last ':' that is outside brackets, so data-[x]:hover:bg-red-500
// leaves bg-red-500 while [mask-image:linear-gradient(#000,transparent)] stays whole.
function stripVariants(token) {
  let depth = 0;
  let cut = -1;

  for (let i = 0; i < token.length; i++) {
    const char = token[i];

    if (char === '[') {
      depth++;
    } else if (char === ']') {
      depth--;
    } else if (char === ':' && depth === 0) {
      cut = i;
    }
  }

  return token.slice(cut + 1);
}

function rawColours(text) {
  return text
    .split(/\s+/)
    .filter(Boolean)
    .filter((token) => {
      const utility = stripVariants(token).replace(/^!/, '').replace(/\/(?:\d+|\[[^\]]*\])$/, '');

      return RAW_COLOUR.test(utility);
    });
}

function isStringLiteral(node) {
  return node.type === 'Literal' && typeof node.value === 'string';
}

// Collects every string literal and template quasi under a node, however nested.
function collectStrings(node, found) {
  if (node === null || typeof node !== 'object') {
    return;
  }

  if (Array.isArray(node)) {
    node.forEach((child) => collectStrings(child, found));

    return;
  }

  if (typeof node.type !== 'string') {
    return;
  }

  if (isStringLiteral(node) || node.type === 'TemplateElement') {
    found.push(node);

    return;
  }

  for (const key of Object.keys(node)) {
    if (key !== 'parent' && key !== 'loc' && key !== 'range') {
      collectStrings(node[key], found);
    }
  }
}

export default {
  meta: {
    type: 'problem',
    schema: [],
    messages: {
      element:
        'Use the kit part instead of a raw <{{name}}>: the one from @lathephp/ui, or the panel\'s resources/js/components/ui copy.',
      colour: 'Use a theme token (bg-muted, text-destructive, border-input) instead of the raw colour "{{token}}".',
    },
  },
  create(context) {
    // A string inside cn() inside a className is reached twice, so report each node once.
    const seen = new WeakSet();

    function checkStrings(root) {
      const strings = [];

      collectStrings(root, strings);

      for (const node of strings) {
        if (seen.has(node)) {
          continue;
        }

        seen.add(node);

        const text = node.type === 'TemplateElement' ? node.value.cooked : node.value;

        for (const token of rawColours(text ?? '')) {
          context.report({ node, messageId: 'colour', data: { token } });
        }
      }
    }

    return {
      JSXOpeningElement(node) {
        if (node.name.type !== 'JSXIdentifier' || !ELEMENTS.has(node.name.name)) {
          return;
        }

        const hidden =
          node.name.name === 'input' &&
          node.attributes.some(
            (attribute) =>
              attribute.type === 'JSXAttribute' &&
              attribute.name.name === 'type' &&
              attribute.value !== null &&
              isStringLiteral(attribute.value) &&
              attribute.value.value === 'hidden',
          );

        if (!hidden) {
          context.report({ node, messageId: 'element', data: { name: node.name.name } });
        }
      },
      JSXAttribute(node) {
        if (node.name.type === 'JSXIdentifier' && CLASS_ATTRIBUTES.has(node.name.name) && node.value) {
          checkStrings(node.value);
        }
      },
      CallExpression(node) {
        if (node.callee.type === 'Identifier' && CLASS_CALLS.has(node.callee.name)) {
          checkStrings(node.arguments);
        }
      },
    };
  },
};
