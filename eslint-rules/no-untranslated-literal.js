// Fails on a user-visible literal that is not passed through __(): JSX text and the
// title, aria-label, placeholder, alt and label props. Text with no letter (a dot,
// a slash, a number) is not a word, so it passes.
const PROPS = new Set(['title', 'aria-label', 'placeholder', 'alt', 'label']);

const hasLetter = (text) => /\p{L}/u.test(text);

/** The static text of a literal or an expression-free template, else null. */
function staticText(node) {
  if (node?.type === 'Literal' && typeof node.value === 'string') {
    return node.value;
  }

  if (node?.type === 'TemplateLiteral' && node.expressions.length === 0) {
    return node.quasis[0].value.cooked;
  }

  return null;
}

export default {
  meta: {
    type: 'problem',
    schema: [],
    messages: {
      text: 'Wrap the text "{{text}}" in __().',
      prop: 'Wrap the {{prop}} text "{{text}}" in __().',
    },
  },
  create(context) {
    return {
      JSXText(node) {
        const text = node.value.trim();

        if (hasLetter(text)) {
          context.report({ node, messageId: 'text', data: { text } });
        }
      },
      JSXExpressionContainer(node) {
        const text = node.parent.type === 'JSXElement' || node.parent.type === 'JSXFragment' ? staticText(node.expression) : null;

        if (text !== null && hasLetter(text)) {
          context.report({ node, messageId: 'text', data: { text } });
        }
      },
      JSXAttribute(node) {
        if (node.name.type !== 'JSXIdentifier' || !PROPS.has(node.name.name) || !node.value) {
          return;
        }

        const text = staticText(node.value.type === 'JSXExpressionContainer' ? node.value.expression : node.value);

        if (text !== null && hasLetter(text)) {
          context.report({ node, messageId: 'prop', data: { prop: node.name.name, text } });
        }
      },
    };
  },
};
