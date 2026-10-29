// Fails a ref assigned in the body of a passive effect: useEffect(() => { ref.current = value; }).
// The browser paints and handles input before a passive effect runs, so a key or a pointer
// event in that gap reads the old value (m16's Escape, m21's Board Shift+Right). Assign it in
// useLayoutEffect instead. A handler or a cleanup the effect only defines runs later, so an
// assignment inside a nested function is left alone.
const isFunction = (node) =>
  node.type === 'ArrowFunctionExpression' || node.type === 'FunctionExpression' || node.type === 'FunctionDeclaration';

function isUseEffect(callee) {
  if (callee.type === 'Identifier') {
    return callee.name === 'useEffect';
  }

  return callee.type === 'MemberExpression' && !callee.computed && callee.property.name === 'useEffect';
}

export default {
  meta: {
    type: 'problem',
    schema: [],
    messages: {
      passive: 'This assigns a ref in a passive effect, which runs after the paint. Assign it in useLayoutEffect.',
    },
  },
  create(context) {
    return {
      AssignmentExpression(node) {
        const target = node.left;

        if (target.type !== 'MemberExpression' || target.computed || target.property.name !== 'current') {
          return;
        }

        // The nearest enclosing function must be the effect's own callback.
        let fn = node.parent;

        while (fn !== null && fn !== undefined && !isFunction(fn)) {
          fn = fn.parent;
        }

        const call = fn?.parent;

        if (call?.type === 'CallExpression' && call.arguments[0] === fn && isUseEffect(call.callee)) {
          context.report({ node, messageId: 'passive' });
        }
      },
    };
  },
};
