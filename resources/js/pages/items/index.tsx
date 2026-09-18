import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterSelect } from '@/components/cyber/filter-select';
import { ItemList } from '@/components/cyber/item-list';
import { Pagination } from '@/components/cyber/pagination';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { create, exportMethod, index } from '@/routes/items';
import type { IncidentOption, ItemRow, Paginated } from '@/types';

type Filters = {
    country: string | null;
    category: string | null;
    kind: string | null;
    from: string | null;
    to: string | null;
    q: string | null;
    cambodia: string | null;
    min_severity: string | null;
};

type Props = {
    items: Paginated<ItemRow>;
    filters: Filters;
    countries: Record<string, string>;
    categories: string[];
    kinds: string[];
    openIncidents: IncidentOption[];
};

function clean(filters: Partial<Filters>) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== null && v !== ''),
    ) as Record<string, string>;
}

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

export default function ItemsIndex({
    items,
    filters,
    countries,
    categories,
    kinds,
    openIncidents,
}: Props) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.q ?? '');

    const apply = (changes: Partial<Filters>) =>
        router.get(index.url(), clean({ ...filters, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    useEffect(() => {
        if (search === (filters.q ?? '')) {
            return;
        }

        const timer = setTimeout(() => apply({ q: search }), 500);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasFilters = Object.keys(clean(filters)).length > 0;

    return (
        <>
            <Head title="Feed" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Feed</h1>
                    <div className="flex gap-2">
                        {auth.can.analyze && (
                            <Button size="sm" asChild>
                                <Link href={create()}>
                                    <Plus /> Add a post
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={exportMethod.url({
                                    query: clean(filters),
                                })}
                            >
                                <Download /> Export CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <Input
                        className="w-full sm:w-64"
                        placeholder="Search titles and summaries…"
                        aria-label="Search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <FilterSelect
                        label="Country"
                        allLabel="All countries"
                        className="w-44"
                        value={filters.country}
                        options={Object.entries(countries).map(
                            ([value, label]) => ({ value, label }),
                        )}
                        onChange={(country) => apply({ country })}
                    />
                    <FilterSelect
                        label="Category"
                        allLabel="All categories"
                        value={filters.category}
                        options={categories.map((c) => ({
                            value: c,
                            label: capitalize(c),
                        }))}
                        onChange={(category) => apply({ category })}
                    />
                    <FilterSelect
                        label="Type"
                        allLabel="All types"
                        className="w-36"
                        value={filters.kind}
                        options={kinds.map((k) => ({
                            value: k,
                            label: capitalize(k),
                        }))}
                        onChange={(kind) => apply({ kind })}
                    />
                    <FilterSelect
                        label="Minimum severity"
                        allLabel="Any severity"
                        className="w-36"
                        value={filters.min_severity}
                        options={[2, 3, 4, 5].map((s) => ({
                            value: String(s),
                            label: `${s} and above`,
                        }))}
                        onChange={(min_severity) => apply({ min_severity })}
                    />
                    <label className="flex h-9 items-center gap-2 rounded-md border px-3 text-sm">
                        <Checkbox
                            checked={filters.cambodia === '1'}
                            onCheckedChange={(checked) =>
                                apply({ cambodia: checked ? '1' : null })
                            }
                        />
                        Cambodia only
                    </label>
                    <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                        From
                        <Input
                            type="date"
                            className="w-40"
                            value={filters.from ?? ''}
                            onChange={(e) => apply({ from: e.target.value })}
                        />
                    </label>
                    <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                        To
                        <Input
                            type="date"
                            className="w-40"
                            value={filters.to ?? ''}
                            onChange={(e) => apply({ to: e.target.value })}
                        />
                    </label>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                router.get(index.url());
                            }}
                        >
                            Clear
                        </Button>
                    )}
                </div>

                <ItemList
                    items={items.data}
                    openIncidents={openIncidents}
                    empty="No items match these filters."
                />
                <Pagination page={items} />
            </div>
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [{ title: 'Feed', href: index() }],
};
