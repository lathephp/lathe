// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { __panel, useTranslation } from '../index';
import { createTranslator, english } from '../translation';

describe('english', () => {
  it('fills the longest placeholder first, so :to never eats :total', () => {
    expect(english('Showing :from to :to of :total', { from: 1, to: 25, total: '10,000' })).toBe('Showing 1 to 25 of 10,000');
  });

  it('keeps :name, upper-cases :NAME and capitalises :Name', () => {
    expect(english(':name :NAME :Name', { name: 'ada lovelace' })).toBe('ada lovelace ADA LOVELACE Ada lovelace');
  });

  it('matches :resources before :resource and replaces every occurrence', () => {
    expect(english(':resources of :resource, :resource', { resource: 'order', resources: 'orders' })).toBe('orders of order, order');
  });
});

describe('createTranslator', () => {
  const catalogues = {
    en: { Save: 'Save', Cancel: 'Cancel', 'Hello :name': 'Hello :name' },
    fr: { Save: 'Enregistrer', 'Hello :name': 'Bonjour :name' },
    'fr-CA': { Save: 'Sauvegarder' },
  };

  it('looks up the locale, then the language, then English', () => {
    const t = createTranslator('fr-CA', catalogues);

    expect(t('Save')).toBe('Sauvegarder');
    expect(t('Hello :name', { name: 'Ada' })).toBe('Bonjour Ada');
    expect(t('Cancel')).toBe('Cancel');
  });

  it('returns the key when no catalogue has it', () => {
    expect(createTranslator('fr', catalogues)('Nothing :x', { x: 1 })).toBe('Nothing 1');
  });
});

describe('useTranslation', () => {
  afterEach(() => {
    __panel.sharedProps.set({});
  });

  it('reads the shared catalogue for the shared locale', () => {
    __panel.sharedProps.set({ locale: 'fr-CA', translations: { Save: 'Sauvegarder', 'Hello :name': 'Bonjour :NAME' } });
    const { result } = renderHook(() => useTranslation());

    expect(result.current.__('Save')).toBe('Sauvegarder');
    expect(result.current.__('Hello :name', { name: 'ada' })).toBe('Bonjour ADA');
    expect(result.current.__('Unknown')).toBe('Unknown');
  });

  it('follows a change of the shared data', () => {
    const { result } = renderHook(() => useTranslation());
    expect(result.current.__('Save')).toBe('Save');

    act(() => {
      __panel.sharedProps.set({ locale: 'fr', translations: { Save: 'Enregistrer' } });
    });

    expect(result.current.__('Save')).toBe('Enregistrer');
  });
});
