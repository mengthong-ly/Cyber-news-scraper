import { Link, router, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    FolderPlus,
    ImageIcon,
    MoreHorizontal,
    ShieldAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CategoryBadge, SeverityBadge } from '@/components/cyber/badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate } from '@/lib/categories';
import {
    show as showIncident,
    store as storeIncident,
} from '@/routes/incidents';
import { attach } from '@/routes/incidents/items';
import { screenshot, update } from '@/routes/items';
import type { IncidentOption, ItemRow } from '@/types';

type Props = {
    items: ItemRow[];
    openIncidents?: IncidentOption[];
    actions?: (item: ItemRow) => ReactNode;
    empty?: string;
};

export function ItemList({
    items,
    openIncidents = [],
    actions,
    empty = 'Nothing here yet.',
}: Props) {
    const { auth } = usePage().props;

    if (items.length === 0) {
        return (
            <p className="text-muted-foreground rounded-xl border px-4 py-10 text-center text-sm">
                {empty}
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-xl border">
            {items.map((item) => (
                <li key={item.id} className="flex gap-3 p-3 sm:p-4">
                    <SeverityBadge severity={item.severity} />
                    <div className="min-w-0 flex-1 space-y-1.5">
                        <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                            <CategoryBadge category={item.category} />
                            {item.is_cambodia && (
                                <Badge className="bg-blue-700 text-white">
                                    Cambodia
                                </Badge>
                            )}
                            <span className="capitalize">{item.kind}</span>
                            <span aria-hidden>·</span>
                            <span>{item.publisher ?? 'Unknown source'}</span>
                            {item.country && (
                                <>
                                    <span aria-hidden>·</span>
                                    <span>{item.country}</span>
                                </>
                            )}
                            <span aria-hidden>·</span>
                            <time dateTime={item.published_at ?? undefined}>
                                {formatDate(item.published_at)}
                            </time>
                            {item.enrichment_status === 'needs_review' && (
                                <Badge
                                    variant="outline"
                                    className="border-amber-500 text-amber-700 dark:text-amber-300"
                                >
                                    Needs review
                                </Badge>
                            )}
                        </div>

                        <a
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            className="block font-medium break-words hover:underline"
                        >
                            {item.title_en ?? item.title}
                            <ExternalLink className="text-muted-foreground ml-1 inline size-3" />
                        </a>
                        {item.title_en && item.title_en !== item.title && (
                            <p
                                lang={item.language ?? undefined}
                                className="text-muted-foreground text-sm break-words"
                            >
                                {item.title}
                            </p>
                        )}
                        {item.summary && (
                            <p className="line-clamp-3 text-sm break-words">
                                {item.summary}
                            </p>
                        )}
                        {item.notes && (
                            <p className="text-muted-foreground text-sm break-words italic">
                                Note: {item.notes}
                            </p>
                        )}

                        <Chips item={item} />

                        {item.incidents.length > 0 && (
                            <p className="text-xs">
                                In incident:{' '}
                                {item.incidents.map((incident, i) => (
                                    <span key={incident.id}>
                                        {i > 0 && ', '}
                                        <Link
                                            href={showIncident(incident.id)}
                                            className="underline underline-offset-2"
                                        >
                                            {incident.title}
                                        </Link>
                                    </span>
                                ))}
                            </p>
                        )}
                    </div>

                    <div className="flex shrink-0 flex-col items-end gap-2">
                        {actions?.(item)}
                        {auth.can.analyze && (
                            <ItemMenu
                                item={item}
                                openIncidents={openIncidents}
                            />
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

function Chips({ item }: { item: ItemRow }) {
    const chips = [
        ...(item.entities?.threat_actors ?? []).map((v) => ['actor', v]),
        ...(item.entities?.cves ?? []).map((v) => ['cve', v]),
        ...(item.entities?.domains ?? []).map((v) => ['domain', v]),
        ...(item.watch_hits ?? []).map((v) => ['watch', v]),
    ];

    if (chips.length === 0 && !item.has_screenshot) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {chips.slice(0, 10).map(([type, value]) => (
                <span
                    key={`${type}-${value}`}
                    className="bg-muted rounded px-1.5 py-0.5 font-mono text-[11px]"
                    title={type}
                >
                    {type === 'watch' ? '★ ' : ''}
                    {value}
                </span>
            ))}
            {item.has_screenshot && (
                <a
                    href={screenshot.url(item.id)}
                    className="bg-muted inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px]"
                >
                    <ImageIcon className="size-3" /> Screenshot
                </a>
            )}
        </div>
    );
}

function ItemMenu({
    item,
    openIncidents,
}: {
    item: ItemRow;
    openIncidents: IncidentOption[];
}) {
    const patch = (data: Record<string, string | number | boolean | null>) =>
        router.patch(update.url(item.id), data, { preserveScroll: true });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Item actions">
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem
                    onSelect={() =>
                        router.post(storeIncident.url(), { item_id: item.id })
                    }
                >
                    <ShieldAlert /> New incident from this
                </DropdownMenuItem>
                {openIncidents.length > 0 && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <FolderPlus className="mr-2 size-4" /> Add to
                            incident
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent className="max-h-72 w-64 overflow-y-auto">
                            {openIncidents.map((incident) => (
                                <DropdownMenuItem
                                    key={incident.id}
                                    onSelect={() =>
                                        router.post(
                                            attach.url(incident.id),
                                            { item_id: item.id },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <span className="truncate">
                                        {incident.title}
                                    </span>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Severity</DropdownMenuLabel>
                <div className="flex gap-1 px-2 pb-2">
                    {[1, 2, 3, 4, 5].map((level) => (
                        <Button
                            key={level}
                            size="sm"
                            variant={
                                item.severity === level ? 'default' : 'outline'
                            }
                            className="size-8 p-0"
                            onClick={() => patch({ severity: level })}
                        >
                            {level}
                        </Button>
                    ))}
                </div>
                <DropdownMenuItem
                    onSelect={() => patch({ is_cambodia: !item.is_cambodia })}
                >
                    {item.is_cambodia
                        ? 'Not about Cambodia'
                        : 'Mark as about Cambodia'}
                </DropdownMenuItem>
                {item.enrichment_status === 'needs_review' && (
                    <DropdownMenuItem
                        onSelect={() => patch({ reviewed: true })}
                    >
                        Mark labels reviewed
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
