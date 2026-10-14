import { Component, Suspense, type ReactNode } from 'react';
import type { RenderHookEntry, RenderHookName, RenderHookProps } from './registry';

interface BoundaryProps {
  position: RenderHookName;
  children: ReactNode;
}

/** A hook that throws renders nothing, so one broken plugin never takes the page down. */
class HookBoundary extends Component<BoundaryProps, { failed: boolean }> {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidCatch(error: unknown) {
    console.error(`[lathe] a render hook at "${this.props.position}" failed`, error);
  }

  render() {
    return this.state.failed ? null : this.props.children;
  }
}

export default function HookList({ position, hooks, context }: { position: RenderHookName; hooks: RenderHookEntry[]; context: RenderHookProps }) {
  return hooks.map(({ component: Hook }, index) => (
    <HookBoundary key={index} position={position}>
      <Suspense fallback={null}>
        <Hook {...context} />
      </Suspense>
    </HookBoundary>
  ));
}
