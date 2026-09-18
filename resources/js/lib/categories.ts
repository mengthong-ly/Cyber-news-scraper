export const categoryStyles: Record<string, string> = {
    attack: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    crime: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    vulnerability:
        'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    disinformation:
        'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-300',
    policy: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    innovation:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    general: 'bg-muted text-muted-foreground',
};

export const severityStyles: Record<number, string> = {
    5: 'bg-red-600 text-white',
    4: 'bg-orange-500 text-white',
    3: 'bg-amber-400 text-amber-950',
    2: 'bg-sky-200 text-sky-900 dark:bg-sky-900 dark:text-sky-100',
    1: 'bg-muted text-muted-foreground',
};

export function formatDate(value: string | null, withTime = false): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return withTime ? date.toLocaleString() : date.toLocaleDateString();
}
