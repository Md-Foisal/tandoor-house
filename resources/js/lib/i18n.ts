import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { TranslationTree } from '@/types';

export type Replacements = Record<string, string | number>;

function lookup(tree: TranslationTree, key: string): string | undefined {
    let node: string | TranslationTree | undefined = tree;

    for (const part of key.split('.')) {
        if (node === undefined || typeof node === 'string') {
            return undefined;
        }

        node = node[part];
    }

    return typeof node === 'string' ? node : undefined;
}

/**
 * Resolves a "group.key" string from lang/en_GB/<group>.php, replacing
 * Laravel-style :placeholders. Falls back to the key so gaps are visible.
 */
export function translate(
    tree: TranslationTree,
    key: string,
    replacements: Replacements = {},
): string {
    const line = lookup(tree, key) ?? key;

    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        line,
    );
}

/**
 * Picks the singular or plural form of a "one|many" line.
 */
export function choice(line: string, count: number): string {
    const [one, many = one] = line.split('|');

    return count === 1 ? one : many;
}

export function useT() {
    const { translations } = usePage().props;

    return useCallback(
        (key: string, replacements?: Replacements) =>
            translate(translations ?? {}, key, replacements),
        [translations],
    );
}
