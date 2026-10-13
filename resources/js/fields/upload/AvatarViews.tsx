import { useState } from 'react';
import type { DetailFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';

interface AvatarData {
  url: string | null;
  initials: string | null;
  fontSize: number;
  color: string;
  background: string;
  bold: boolean;
}

const HEX = /^(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

function hex(value: unknown, fallback: string): string {
  return typeof value === 'string' && HEX.test(value) ? value : fallback;
}

/** The avatar in a field value, with the style defaults filled in; any other value is not one. */
function asAvatar(value: unknown): AvatarData | null {
  if (typeof value !== 'object' || value === null) {
    return null;
  }

  const avatar = value as Record<string, unknown>;
  const style = typeof avatar.style === 'object' && avatar.style !== null ? (avatar.style as Record<string, unknown>) : {};

  return {
    url: typeof avatar.url === 'string' ? avatar.url : null,
    initials: typeof avatar.initials === 'string' ? avatar.initials : null,
    fontSize: typeof style.fontSize === 'number' ? Math.min(1, Math.max(0.1, style.fontSize)) : 0.5,
    color: hex(style.color, '7F9CF5'),
    background: hex(style.background, 'EBF4FF'),
    bold: style.bold === true,
  };
}

/** Round initials drawn here, so no request leaves the page. */
function Initials({ avatar, size }: { avatar: AvatarData; size: number }) {
  return (
    <svg viewBox="0 0 100 100" width={size} height={size} role="img" aria-label={avatar.initials ?? ''} className="shrink-0">
      <circle cx="50" cy="50" r="50" fill={`#${avatar.background}`} />
      <text x="50" y="50" textAnchor="middle" dominantBaseline="central" fontSize={avatar.fontSize * 100} fontWeight={avatar.bold ? 700 : 400} fill={`#${avatar.color}`}>
        {avatar.initials}
      </text>
    </svg>
  );
}

function Avatar({ avatar, size }: { avatar: AvatarData; size: number }) {
  const [failed, setFailed] = useState(false);

  if (avatar.url !== null && !failed) {
    return (
      <img
        src={avatar.url}
        alt=""
        width={size}
        height={size}
        loading="lazy"
        className="shrink-0 rounded-full object-cover"
        onError={() => {
          setFailed(true);
        }}
      />
    );
  }

  return <Initials avatar={avatar} size={size} />;
}

function size(field: FieldSchema, key: 'indexWidth' | 'detailWidth', fallback: number): number {
  const value = field[key];

  return typeof value === 'number' && value > 0 ? value : fallback;
}

export function AvatarIndex({ field, cell }: IndexFieldProps) {
  const avatar = asAvatar(cell.value);

  return avatar === null ? cell.display : <Avatar avatar={avatar} size={size(field, 'indexWidth', 32)} />;
}

export function AvatarDetail({ field }: DetailFieldProps) {
  const avatar = asAvatar(field.value);

  if (avatar === null) {
    return field.display === '' ? <span className="text-muted-foreground">—</span> : field.display;
  }

  return <Avatar avatar={avatar} size={size(field, 'detailWidth', 128)} />;
}
