/**
 * The draft id each editor with files uploaded under, keyed by resource and attribute, so the
 * form's submit can send it as `{attribute}DraftId` without the editor owning the form state.
 * The editor sets it on its first upload and forgets it when it unmounts.
 */
const drafts = new Map<string, string>();

function keyOf(resource: string, attribute: string): string {
  return `${resource}:${attribute}`;
}

export function setDraftId(resource: string, attribute: string, id: string): void {
  drafts.set(keyOf(resource, attribute), id);
}

export function draftIdOf(resource: string, attribute: string): string | undefined {
  return drafts.get(keyOf(resource, attribute));
}

export function forgetDraftId(resource: string, attribute: string): void {
  drafts.delete(keyOf(resource, attribute));
}
