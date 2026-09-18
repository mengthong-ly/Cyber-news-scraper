import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import IncidentNoteController from '@/actions/App/Http/Controllers/IncidentNoteController';
import { ItemList } from '@/components/cyber/item-list';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/categories';
import { index, show, update } from '@/routes/incidents';
import { detach } from '@/routes/incidents/items';
import type { ItemRow } from '@/types';

type Incident = {
    id: number;
    title: string;
    status: string;
    severity: number;
    summary: string | null;
    assignee_id: number | null;
    created_at: string;
    closed_at: string | null;
    items: ItemRow[];
    notes: {
        id: number;
        body: string;
        user: string | null;
        created_at: string;
    }[];
};

type Props = {
    incident: Incident;
    statuses: string[];
    analysts: { id: number; name: string }[];
};

const fieldClass =
    'border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs dark:bg-input/30';
const textareaClass =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] dark:bg-input/30';

export default function IncidentShow({ incident, statuses, analysts }: Props) {
    const { auth } = usePage().props;
    const canEdit = auth.can.analyze;
    const [summary, setSummary] = useState(incident.summary ?? '');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Incidents', href: index() },
            { title: incident.title, href: show(incident.id) },
        ],
    });

    const patch = (data: Record<string, string | number | boolean | null>) =>
        router.patch(update.url(incident.id), data, { preserveScroll: true });

    return (
        <>
            <Head title={incident.title} />
            <div className="grid flex-1 gap-4 p-4 lg:grid-cols-[1fr_20rem]">
                <div className="min-w-0 space-y-4">
                    <div>
                        {canEdit ? (
                            <Input
                                aria-label="Incident title"
                                className="hover:border-input h-auto border-transparent px-0 text-xl font-semibold shadow-none focus-visible:px-2"
                                defaultValue={incident.title}
                                onBlur={(e) =>
                                    e.target.value !== incident.title &&
                                    patch({ title: e.target.value })
                                }
                            />
                        ) : (
                            <h1 className="text-xl font-semibold">
                                {incident.title}
                            </h1>
                        )}
                        <p className="text-muted-foreground text-sm">
                            Opened {formatDate(incident.created_at, true)}
                            {incident.closed_at &&
                                ` · closed ${formatDate(incident.closed_at, true)}`}
                        </p>
                    </div>

                    <section className="space-y-2">
                        <h2 className="font-medium">Summary</h2>
                        {canEdit ? (
                            <>
                                <textarea
                                    aria-label="Summary"
                                    className={textareaClass}
                                    value={summary}
                                    onChange={(e) => setSummary(e.target.value)}
                                    placeholder="What happened, what is affected, what was done."
                                />
                                {summary !== (incident.summary ?? '') && (
                                    <Button
                                        size="sm"
                                        onClick={() => patch({ summary })}
                                    >
                                        Save summary
                                    </Button>
                                )}
                            </>
                        ) : (
                            <p className="text-sm whitespace-pre-wrap">
                                {incident.summary ?? 'No summary yet.'}
                            </p>
                        )}
                    </section>

                    <section className="space-y-2">
                        <h2 className="font-medium">
                            Items ({incident.items.length})
                        </h2>
                        <ItemList
                            items={incident.items}
                            actions={(item) =>
                                canEdit && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove from incident"
                                        onClick={() =>
                                            router.delete(
                                                detach.url({
                                                    incident: incident.id,
                                                    item: item.id,
                                                }),
                                                {
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                )
                            }
                        />
                    </section>
                </div>

                <aside className="space-y-4">
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                <select
                                    id="status"
                                    className={fieldClass}
                                    disabled={!canEdit}
                                    value={incident.status}
                                    onChange={(e) =>
                                        patch({ status: e.target.value })
                                    }
                                >
                                    {statuses.map((s) => (
                                        <option key={s} value={s}>
                                            {s.charAt(0).toUpperCase() +
                                                s.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="severity">Severity</Label>
                                <select
                                    id="severity"
                                    className={fieldClass}
                                    disabled={!canEdit}
                                    value={incident.severity}
                                    onChange={(e) =>
                                        patch({
                                            severity: Number(e.target.value),
                                        })
                                    }
                                >
                                    {[5, 4, 3, 2, 1].map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="assignee">Assignee</Label>
                                <select
                                    id="assignee"
                                    className={fieldClass}
                                    disabled={!canEdit}
                                    value={incident.assignee_id ?? ''}
                                    onChange={(e) =>
                                        patch({
                                            assignee_id: e.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Unassigned</option>
                                    {analysts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {canEdit && (
                                <Form
                                    {...IncidentNoteController.store.form(
                                        incident.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <textarea
                                                name="body"
                                                aria-label="New note"
                                                required
                                                className={textareaClass}
                                                placeholder="Add a note…"
                                            />
                                            <InputError message={errors.body} />
                                            <Button
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Add note
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                            {incident.notes.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No notes yet.
                                </p>
                            )}
                            <ol className="space-y-3">
                                {incident.notes.map((note) => (
                                    <li key={note.id} className="text-sm">
                                        <p className="text-muted-foreground text-xs">
                                            {note.user ?? 'Deleted user'} ·{' '}
                                            {formatDate(note.created_at, true)}
                                        </p>
                                        <p className="whitespace-pre-wrap">
                                            {note.body}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </>
    );
}

IncidentShow.layout = {
    breadcrumbs: [{ title: 'Incidents', href: index() }],
};
