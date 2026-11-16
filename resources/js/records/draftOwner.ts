import { claimStorage, removeKeys } from '../lib/owner';

// Apart from useFormDraft, so the session code every page loads leaves the form's draft hook out.
export const draftPrefix = 'lathe-draft:';

/** Drops this tab's drafts when the signed-in user is not the one who typed them. */
export function claimDrafts(email: string | null): void {
  claimStorage(() => window.sessionStorage, 'lathe-draft-owner', email, (store) => {
    removeKeys(store, draftPrefix);
  });
}
