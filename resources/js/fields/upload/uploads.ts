import { createContext } from 'react';

/** The form a file field uploads into: begin() counts an upload as running and returns its end, which counts once. */
export interface FormUploads {
  begin: () => () => void;
}

/** Set by a form's fields, so the form holds its Save while a file uploads; a field outside a form uploads untracked. */
export const FormUploadsContext = createContext<FormUploads | null>(null);
