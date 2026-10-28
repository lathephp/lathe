// Fails a Vitest file where some tests assert the URL or the history and others do not.
// Such a test leaks router and jsdom history state into its neighbours, so it keeps a
// file of its own. A mention inside a template literal (a storage key built from
// window.location.pathname) is not an assertion on the URL, so it is skipped.
const URL_ACCESS = /^(window\.location|location\.|window\.history|history\.length)/;

/** The it/test name a call is written with, through .only, .skip and .each(table). */
function testName(callee) {
  if (callee.type === 'Identifier') {
    return callee.name;
  }

  if (callee.type === 'MemberExpression') {
    return testName(callee.object);
  }

  if (callee.type === 'CallExpression') {
    return testName(callee.callee);
  }

  return null;
}

const isFunction = (node) => node.type === 'ArrowFunctionExpression' || node.type === 'FunctionExpression';

export default {
  meta: {
    type: 'problem',
    schema: [],
    messages: {
      mixed:
        'This file asserts the URL or the history in some tests and not in others. Move the URL/history tests to a file of their own.',
    },
  },
  create(context) {
    const { sourceCode } = context;
    const tests = new Map();

    /** Whether the node reads window.location, location, window.history or history.length, outside a template literal. */
    function readsUrl(node) {
      if (!node || typeof node.type !== 'string' || node.type === 'TemplateLiteral') {
        return false;
      }

      if (node.type === 'MemberExpression' && URL_ACCESS.test(sourceCode.getText(node))) {
        return true;
      }

      const keys = sourceCode.visitorKeys[node.type] ?? [];

      return keys.some((key) => {
        const child = node[key];

        return Array.isArray(child) ? child.some(readsUrl) : readsUrl(child);
      });
    }

    function enclosingTest(node) {
      for (let up = node.parent; up; up = up.parent) {
        if (up.type === 'CallExpression' && ['it', 'test'].includes(testName(up.callee)) && up.arguments.some(isFunction)) {
          return up;
        }
      }

      return null;
    }

    return {
      CallExpression(node) {
        if (['it', 'test'].includes(testName(node.callee)) && node.arguments.some(isFunction) && !tests.has(node)) {
          tests.set(node, false);
        }

        if (node.callee.type === 'Identifier' && node.callee.name === 'expect') {
          const test = enclosingTest(node);

          if (test && node.arguments.some(readsUrl)) {
            tests.set(test, true);
          }
        }
      },
      'Program:exit'(program) {
        const values = [...tests.values()];

        if (values.includes(true) && values.includes(false)) {
          context.report({ node: program, messageId: 'mixed' });
        }
      },
    };
  },
};
