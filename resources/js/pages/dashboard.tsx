import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ExternalLink } from 'lucide-react';
import { SeverityBadge } from '@/components/cyber/badges';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { WorldMap } from '@/components/ui/map';
import { formatDate } from '@/lib/categories';
import { countryCoords } from '@/lib/country-coords';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as alertsIndex } from '@/routes/alerts';
import { index as incidentsIndex } from '@/routes/incidents';
import { index as itemsIndex } from '@/routes/items';

type Props = {
    stats: {
        items: number;
        cambodia: number;
        openAlerts: number;
        openIncidents: number;
        unhealthySources: number;
        sources: number;
    };
    byCategory: Record<string, number>;
    trend: { date: string; total: number }[];
    topCountries: { code: string; name: string; total: number }[];
    topActors: { name: string; total: number }[];
    latestAlerts: {
        id: number;
        severity: number;
        reason: string;
        title: string;
        url: string;
        created_at: string;
    }[];
    lastFetchedAt: string | null;
};

const numberFormat = new Intl.NumberFormat(undefined, { notation: 'compact' });
const barColor = 'bg-sky-600 dark:bg-sky-400';
const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });

export default function Dashboard({
    stats,
    byCategory,
    trend,
    topCountries,
    topActors,
    latestAlerts,
    lastFetchedAt,
}: Props) {
    const categories = Object.entries(byCategory)
        .map(([name, total]) => ({
            name,
            total,
            href: itemsIndex({ query: { category: name, cambodia: '1' } }),
        }))
        .sort((a, b) => b.total - a.total);
    const countryTotals = Object.fromEntries(
        topCountries.map((c) => [c.code, c]),
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Last 7 days</h1>
                    <p className="text-muted-foreground text-sm">
                        {lastFetchedAt
                            ? `Sources last updated ${formatDate(lastFetchedAt, true)}`
                            : 'No sources fetched yet.'}
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <StatTile
                        label="Items collected"
                        value={stats.items}
                        href={itemsIndex()}
                    />
                    <StatTile
                        label="About Cambodia"
                        value={stats.cambodia}
                        href={itemsIndex({ query: { cambodia: '1' } })}
                    />
                    <StatTile
                        label="Open alerts"
                        value={stats.openAlerts}
                        href={alertsIndex()}
                        warn={stats.openAlerts > 0}
                    />
                    <StatTile
                        label="Active incidents"
                        value={stats.openIncidents}
                        href={incidentsIndex()}
                    />
                    <StatTile
                        label="Failing sources"
                        value={stats.unhealthySources}
                        suffix={` of ${stats.sources}`}
                        warn={stats.unhealthySources > 0}
                    />
                </div>

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle>Where the news comes from</CardTitle>
                        <CardDescription>
                            Items per country, last 7 days, linked to Cambodia.
                            Faint dots are monitored countries with no items
                            yet.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <WorldMap
                            targetId="KH"
                            pins={Object.entries(countryCoords).map(
                                ([code, [lat, lng]]) => ({
                                    id: code,
                                    lat,
                                    lng,
                                    label:
                                        countryTotals[code]?.name ??
                                        regionNames.of(code) ??
                                        code,
                                    value: countryTotals[code]?.total ?? 0,
                                }),
                            )}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="gap-4 lg:col-span-2">
                        <CardHeader>
                            <CardTitle>
                                Cambodia-related items per day
                            </CardTitle>
                            <CardDescription>Last 14 days</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ColumnChart data={trend} />
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between">
                                Open alerts
                                <Link
                                    href={alertsIndex()}
                                    className="text-muted-foreground text-sm font-normal underline"
                                >
                                    View all
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {latestAlerts.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No open alerts.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {latestAlerts.map((alert) => (
                                        <li
                                            key={alert.id}
                                            className="flex gap-2 text-sm"
                                        >
                                            <SeverityBadge
                                                severity={alert.severity}
                                            />
                                            <div className="min-w-0">
                                                <a
                                                    href={alert.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer nofollow"
                                                    className="line-clamp-2 font-medium hover:underline"
                                                >
                                                    {alert.title}
                                                    <ExternalLink className="text-muted-foreground ml-1 inline size-3" />
                                                </a>
                                                <p className="text-muted-foreground text-xs">
                                                    {alert.reason} ·{' '}
                                                    {formatDate(
                                                        alert.created_at,
                                                        true,
                                                    )}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <RankedCard
                        title="Cambodia items by category"
                        rows={categories}
                    />
                    <RankedCard
                        title="Most active countries"
                        rows={topCountries.slice(0, 8).map((c) => ({
                            ...c,
                            href: itemsIndex({ query: { country: c.code } }),
                        }))}
                    />
                    <RankedCard
                        title="Threat actors named (Cambodia)"
                        rows={topActors.map((a) => ({
                            ...a,
                            href: itemsIndex({ query: { q: a.name } }),
                        }))}
                        empty="No threat actors named yet. They appear once AI labelling or threat feeds are on."
                    />
                </div>
            </div>
        </>
    );
}

function StatTile({
    label,
    value,
    suffix,
    href,
    warn = false,
}: {
    label: string;
    value: number;
    suffix?: string;
    href?: ReturnType<typeof dashboard>;
    warn?: boolean;
}) {
    const body = (
        <Card
            className={cn(
                'h-full gap-1 px-4 py-3',
                href && 'hover:bg-muted/50 transition-colors',
            )}
        >
            <p className="text-muted-foreground flex items-center gap-1 text-sm">
                {warn && (
                    <AlertTriangle
                        className="size-4 text-amber-600"
                        aria-label="Needs attention"
                    />
                )}
                {label}
            </p>
            <p className="text-2xl font-semibold">
                {numberFormat.format(value)}
                {suffix && (
                    <span className="text-muted-foreground text-sm font-normal">
                        {suffix}
                    </span>
                )}
            </p>
        </Card>
    );

    return href ? <Link href={href}>{body}</Link> : body;
}

function ColumnChart({ data }: { data: { date: string; total: number }[] }) {
    const max = Math.max(1, ...data.map((d) => d.total));
    const top = niceCeiling(max);

    return (
        <figure>
            <div className="border-border relative flex h-44 items-end gap-0.5 border-b pl-8">
                <span className="text-muted-foreground absolute top-0 left-0 text-xs tabular-nums">
                    {top}
                </span>
                <span className="text-muted-foreground absolute bottom-0 left-0 text-xs">
                    0
                </span>
                <div
                    className="border-border/60 pointer-events-none absolute inset-x-0 top-2 ml-8 border-t"
                    aria-hidden
                />
                {data.map((d) => (
                    <div
                        key={d.date}
                        className="group relative flex h-full flex-1 items-end justify-center"
                        tabIndex={0}
                        aria-label={`${d.date}: ${d.total}`}
                    >
                        <div
                            className={cn(
                                'w-full max-w-6 rounded-t-[4px] transition-opacity group-hover:opacity-80',
                                barColor,
                            )}
                            style={{
                                height: `${(d.total / top) * 100}%`,
                                minHeight: d.total > 0 ? 2 : 0,
                            }}
                        />
                        <div className="bg-popover text-popover-foreground pointer-events-none absolute bottom-full z-10 mb-1 hidden rounded-md border px-2 py-1 text-xs whitespace-nowrap shadow-sm group-hover:block group-focus:block">
                            {formatDate(d.date)}: <strong>{d.total}</strong>
                        </div>
                    </div>
                ))}
            </div>
            <figcaption className="text-muted-foreground mt-1 flex justify-between pl-8 text-xs">
                <span>{formatDate(data[0]?.date ?? null)}</span>
                <span>{formatDate(data.at(-1)?.date ?? null)}</span>
            </figcaption>
            <details className="text-muted-foreground mt-2 text-xs">
                <summary className="cursor-pointer">Show as table</summary>
                <table className="mt-1">
                    <tbody>
                        {data.map((d) => (
                            <tr key={d.date}>
                                <td className="pr-4">{d.date}</td>
                                <td className="text-right tabular-nums">
                                    {d.total}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </details>
        </figure>
    );
}

function RankedCard({
    title,
    rows,
    empty = 'No data yet.',
}: {
    title: string;
    rows: {
        name: string;
        total: number;
        href?: ReturnType<typeof dashboard>;
    }[];
    empty?: string;
}) {
    const max = Math.max(1, ...rows.map((r) => r.total));

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{empty}</p>
                ) : (
                    <ul className="space-y-2">
                        {rows.map((row) => {
                            const content = (
                                <>
                                    <span className="truncate capitalize">
                                        {row.name}
                                    </span>
                                    <span
                                        className="flex h-3 items-center"
                                        aria-hidden
                                    >
                                        <span
                                            className={cn(
                                                'block h-3 rounded-r-[4px]',
                                                barColor,
                                            )}
                                            style={{
                                                width: `${(row.total / max) * 100}%`,
                                                minWidth: 2,
                                            }}
                                        />
                                    </span>
                                    <span className="text-right tabular-nums">
                                        {row.total}
                                    </span>
                                </>
                            );
                            const className =
                                'grid grid-cols-[7rem_1fr_2.5rem] items-center gap-2 text-sm';

                            return (
                                <li
                                    key={row.name}
                                    title={`${row.name}: ${row.total}`}
                                >
                                    {row.href ? (
                                        <Link
                                            href={row.href}
                                            className={cn(
                                                className,
                                                'hover:bg-muted/50 rounded-sm',
                                            )}
                                        >
                                            {content}
                                        </Link>
                                    ) : (
                                        <div className={className}>
                                            {content}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function niceCeiling(value: number): number {
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 5, 10].find((s) => s * magnitude >= value) ?? 10;

    return step * magnitude;
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
