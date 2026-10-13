import { registerField } from '../registry';
import { AvatarDetail, AvatarIndex } from '../upload/AvatarViews';
import { AudioDetail, AudioForm, FileDetail, FileForm, FileIndex } from '../upload/FileViews';
import { ImageDetail, ImageForm, ImageIndex } from '../upload/ImageViews';

/** Register the renderers of the file, image, audio, avatar, gravatar and uiAvatar fields. */
export function registerUploadFields(): void {
  registerField('file-field', { index: FileIndex, detail: FileDetail, form: FileForm });
  registerField('image-field', { index: ImageIndex, detail: ImageDetail, form: ImageForm });
  registerField('audio-field', { index: FileIndex, detail: AudioDetail, form: AudioForm });
  registerField('ui-avatar-field', { index: AvatarIndex, detail: AvatarDetail });
}
