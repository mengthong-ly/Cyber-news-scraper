import { Head, Link } from '@inertiajs/react';
import { SeverityBadge } from '@/components/cyber/badges';
import { Pagination } from '@/components/cyber/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/categories';
import { index, show } from '@/routes/incidents';
import type { Paginated } from '@/types';

type IncidentRow = {
    id: number;
    title: string;
    status: string;
    severity: number;
    assignee: string | null;
    items_count: number;
    updated_at: string;
};

type Props = {
    incidents: Paginated<IncidentRow>;
    status: string | null;
    statuses: string[];
};

export default function IncidentsIndex({ incidents, status, statuses }: Props) {
    return (
        <>
            <Head title="Incidents" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">Incidents</h1>
                        <p className="text-muted-foreground text-sm">
                            Create an incident from any item in the feed or
                            alerts.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-1">
                        <Button
                            size="sm"
                            variant={status === null ? 'default' : 'outline'}
                            asChild
                        >
                            <Link href={index()}>Active</Link>
                        </Button>
                        {statuses.map((s) => (
                            <Button
                                key={s}
                                size="sm"
                                variant={status === s ? 'default' : 'outline'}
                                className="capitalize"
                                asChild
                            >
                                <Link href={index({ query: { status: s } })}>
                                    {s}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                {incidents.data.length === 0 ? (
                    <p className="text-muted-foreground rounded-xl border px-4 py-10 text-center text-sm">
                        No incidents.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {incidents.data.map((incident) => (
                            <li key={incident.id}>
                                <Link
                                    href={show(incident.id)}
                                    className="hover:bg-muted/50 flex items-center gap-3 p-3 sm:p-4"
                                >
                                    <SeverityBadge
                                        severity={incident.severity}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {incident.title}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {incident.items_count} item
                                            {incident.items_count === 1
                                                ? ''
                                                : 's'}{' '}
                                            ·{' '}
                                            {incident.assignee ?? 'Unassigned'}{' '}
                                            · updated{' '}
                                            {formatDate(
                                                incident.updated_at,
                                                true,
                                            )}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {incident.status}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination page={incidents} />
            </div>
        </>
    );
}

IncidentsIndex.layout = {
    breadcrumbs: [{ title: 'Incidents', href: index() }],
};
