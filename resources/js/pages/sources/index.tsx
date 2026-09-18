import { Form, Head, router } from '@inertiajs/react';
import { Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/categories';
import { cn } from '@/lib/utils';
import { destroy, fetch, index } from '@/routes/sources';

type Source = {
    id: number;
    name: string;
    type: string;
    config: Record<string, unknown>;
    interval_minutes: number;
    enabled: boolean;
    ai_enabled: boolean;
    healthy: boolean;
    last_success_at: string | null;
    last_fetched_at: string | null;
    last_error: string | null;
    last_item_count: number;
    items_count: number;
};

type Props = {
    sources: Source[];
    types: string[];
    requiredSettings: Record<string, string[]>;
};

const fieldClass =
    'border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs dark:bg-input/30';

export default function SourcesIndex({
    sources,
    types,
    requiredSettings,
}: Props) {
    const [editing, setEditing] = useState<Source | 'new' | null>(null);

    return (
        <>
            <Head title="Sources" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">Sources</h1>
                        <p className="text-muted-foreground text-sm">
                            Public feeds and APIs only. A source turns red after
                            3 failed fetches in a row.
                        </p>
                    </div>
                    <Button size="sm" onClick={() => setEditing('new')}>
                        <Plus /> Add source
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Source
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Last success
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Items
                                </th>
                                <th className="px-3 py-2">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {sources.map((source) => (
                                <tr
                                    key={source.id}
                                    className={cn(
                                        'border-t align-top',
                                        !source.enabled &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    <td className="min-w-56 px-3 py-2">
                                        <p className="font-medium">
                                            {source.name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {source.type} · every{' '}
                                            {source.interval_minutes} min
                                            {!source.ai_enabled && ' · AI off'}
                                        </p>
                                    </td>
                                    <td className="px-3 py-2">
                                        {!source.enabled ? (
                                            <Badge variant="outline">
                                                Disabled
                                            </Badge>
                                        ) : source.type === 'manual' ? (
                                            <Badge variant="outline">
                                                Manual
                                            </Badge>
                                        ) : source.healthy ? (
                                            <Badge className="bg-emerald-600 text-white">
                                                Healthy
                                            </Badge>
                                        ) : (
                                            <Badge className="bg-red-600 text-white">
                                                Failing
                                            </Badge>
                                        )}
                                        {source.last_error && (
                                            <p className="mt-1 max-w-72 text-xs break-words text-red-600">
                                                {source.last_error}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {formatDate(
                                            source.last_success_at,
                                            true,
                                        )}
                                        {source.last_success_at && (
                                            <p className="text-muted-foreground text-xs">
                                                {source.last_item_count} new
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {source.items_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            {source.type !== 'manual' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Fetch ${source.name} now`}
                                                    onClick={() =>
                                                        router.post(
                                                            fetch.url(
                                                                source.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Play />
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${source.name}`}
                                                onClick={() =>
                                                    setEditing(source)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete ${source.name}`}
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            `Delete "${source.name}"? Its items stay in the feed.`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            destroy.url(
                                                                source.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    {editing !== null && (
                        <SourceForm
                            key={editing === 'new' ? 'new' : editing.id}
                            source={editing === 'new' ? null : editing}
                            types={types}
                            requiredSettings={requiredSettings}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function SourceForm({
    source,
    types,
    requiredSettings,
    onDone,
}: {
    source: Source | null;
    types: string[];
    requiredSettings: Record<string, string[]>;
    onDone: () => void;
}) {
    const [type, setType] = useState(source?.type ?? 'rss');
    const action = source
        ? SourceController.update.form(source.id)
        : SourceController.store.form();

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    {source ? 'Edit source' : 'Add source'}
                </DialogTitle>
                <DialogDescription>
                    Settings are JSON. Required for this type:{' '}
                    {(requiredSettings[type] ?? []).join(', ') || 'none'}. Only
                    add sources whose terms and robots.txt allow automated
                    access.
                </DialogDescription>
            </DialogHeader>
            <Form
                {...action}
                options={{ preserveScroll: true }}
                onSuccess={onDone}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={source?.name}
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="type">Type</Label>
                                <select
                                    id="type"
                                    name="type"
                                    className={fieldClass}
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                >
                                    {types.map((t) => (
                                        <option key={t}>{t}</option>
                                    ))}
                                </select>
                                <InputError message={errors.type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="interval_minutes">
                                    Fetch every (minutes)
                                </Label>
                                <Input
                                    id="interval_minutes"
                                    name="interval_minutes"
                                    type="number"
                                    min={5}
                                    defaultValue={
                                        source?.interval_minutes ?? 60
                                    }
                                    required
                                />
                                <InputError message={errors.interval_minutes} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="config">Settings (JSON)</Label>
                            <textarea
                                id="config"
                                name="config"
                                spellCheck={false}
                                className="border-input dark:bg-input/30 min-h-40 w-full rounded-md border bg-transparent px-3 py-2 font-mono text-xs shadow-xs"
                                defaultValue={JSON.stringify(
                                    source?.config ?? {
                                        url: '',
                                        language: 'en',
                                        country_code: 'KH',
                                        cyber_only: true,
                                    },
                                    null,
                                    2,
                                )}
                            />
                            <InputError
                                message={errors.config ?? errors['config.url']}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="enabled">Status</Label>
                                <select
                                    id="enabled"
                                    name="enabled"
                                    className={fieldClass}
                                    defaultValue={
                                        source?.enabled === false ? '0' : '1'
                                    }
                                >
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ai_enabled">
                                    AI processing
                                </Label>
                                <select
                                    id="ai_enabled"
                                    name="ai_enabled"
                                    className={fieldClass}
                                    defaultValue={
                                        source?.ai_enabled === false ? '0' : '1'
                                    }
                                >
                                    <option value="1">Allowed</option>
                                    <option value="0">Not allowed</option>
                                </select>
                            </div>
                        </div>
                        <Button disabled={processing}>
                            {source ? 'Save' : 'Add source'}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

SourcesIndex.layout = {
    breadcrumbs: [{ title: 'Sources', href: index() }],
};
