import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { ItemList } from '@/components/cyber/item-list';
import { Pagination } from '@/components/cyber/pagination';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/categories';
import { acknowledge, index } from '@/routes/alerts';
import type { IncidentOption, ItemRow, Paginated } from '@/types';

type AlertRow = {
    id: number;
    severity: number;
    reason: string;
    created_at: string;
    acknowledged_at: string | null;
    acknowledged_by: string | null;
    item: ItemRow;
};

type Props = {
    alerts: Paginated<AlertRow>;
    showAll: boolean;
    openIncidents: IncidentOption[];
};

export default function AlertsIndex({ alerts, showAll, openIncidents }: Props) {
    const { auth } = usePage().props;
    const byItem = new Map(alerts.data.map((alert) => [alert.item.id, alert]));

    return (
        <>
            <Head title="Alerts" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">Alerts</h1>
                        <p className="text-muted-foreground text-sm">
                            Cambodia-related items with severity 4 or 5.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={index({ query: showAll ? {} : { all: 1 } })}
                        >
                            {showAll
                                ? 'Show open only'
                                : 'Show acknowledged too'}
                        </Link>
                    </Button>
                </div>

                <ItemList
                    items={alerts.data.map((alert) => alert.item)}
                    openIncidents={openIncidents}
                    empty={showAll ? 'No alerts yet.' : 'No open alerts. 🎉'}
                    actions={(item) => {
                        const alert = byItem.get(item.id)!;

                        return (
                            <div className="text-muted-foreground flex flex-col items-end gap-1 text-right text-xs">
                                <span className="max-w-40">{alert.reason}</span>
                                <span>
                                    {formatDate(alert.created_at, true)}
                                </span>
                                {alert.acknowledged_at ? (
                                    <span>
                                        Acknowledged by{' '}
                                        {alert.acknowledged_by ?? 'someone'}
                                    </span>
                                ) : (
                                    auth.can.analyze && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    acknowledge.url(alert.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Check /> Acknowledge
                                        </Button>
                                    )
                                )}
                            </div>
                        );
                    }}
                />
                <Pagination page={alerts} />
            </div>
        </>
    );
}

AlertsIndex.layout = {
    breadcrumbs: [{ title: 'Alerts', href: index() }],
};
