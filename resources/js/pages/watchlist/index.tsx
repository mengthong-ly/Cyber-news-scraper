import { Form, Head, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import WatchlistTermController from '@/actions/App/Http/Controllers/WatchlistTermController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { destroy, index } from '@/routes/watchlist';

type Term = { id: number; kind: string; term: string; notes: string | null };

const descriptions: Record<string, string> = {
    cambodia:
        'Words that make an item Cambodia-related: names, places, institutions, Khmer terms.',
    domain: 'Cambodian or ministry domains. A mention raises the item to severity 4 (alert).',
    product:
        'Systems the ministry runs. A vulnerability mentioning one becomes Cambodia-relevant and one step more severe.',
    actor: 'Threat actors to track. A mention makes the item one step more severe.',
};

export default function WatchlistIndex({
    terms,
    kinds,
}: {
    terms: Term[];
    kinds: string[];
}) {
    return (
        <>
            <Head title="Watchlist" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Watchlist</h1>
                    <p className="text-muted-foreground text-sm">
                        Applied to new items when they are collected. Matching
                        ignores case.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {kinds.map((kind) => (
                        <Card key={kind} className="gap-4">
                            <CardHeader>
                                <CardTitle className="capitalize">
                                    {kind}
                                </CardTitle>
                                <CardDescription>
                                    {descriptions[kind]}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <ul className="flex flex-wrap gap-1.5">
                                    {terms
                                        .filter((t) => t.kind === kind)
                                        .map((t) => (
                                            <li
                                                key={t.id}
                                                className="bg-muted flex items-center gap-1 rounded-md py-0.5 pr-0.5 pl-2 text-sm"
                                                title={t.notes ?? undefined}
                                            >
                                                {t.term}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6"
                                                    aria-label={`Remove ${t.term}`}
                                                    onClick={() =>
                                                        router.delete(
                                                            destroy.url(t.id),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <X className="size-3" />
                                                </Button>
                                            </li>
                                        ))}
                                </ul>
                                <Form
                                    {...WatchlistTermController.store.form()}
                                    options={{ preserveScroll: true }}
                                    errorBag={kind}
                                    resetOnSuccess
                                    className="flex gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <div className="w-full space-y-1">
                                            <div className="flex gap-2">
                                                <input
                                                    type="hidden"
                                                    name="kind"
                                                    value={kind}
                                                />
                                                <Input
                                                    name="term"
                                                    placeholder={`Add ${kind} term`}
                                                    aria-label={`Add ${kind} term`}
                                                    required
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                    className="h-9"
                                                >
                                                    Add
                                                </Button>
                                            </div>
                                            <InputError message={errors.term} />
                                        </div>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

WatchlistIndex.layout = {
    breadcrumbs: [{ title: 'Watchlist', href: index() }],
};
