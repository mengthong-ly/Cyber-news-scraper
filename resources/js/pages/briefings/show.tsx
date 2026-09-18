import { Head, Link, router, usePage } from '@inertiajs/react';
import { Printer, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate } from '@/lib/categories';
import { cn } from '@/lib/utils';
import { index, show, store } from '@/routes/briefings';

type Briefing = {
    id: number;
    date: string;
    status: string;
    body_en: string | null;
    body_km: string | null;
    error: string | null;
    generated_at: string | null;
};

type Props = {
    briefing: Briefing | null;
    history: { id: number; date: string; status: string }[];
    aiEnabled: boolean;
};

export default function BriefingShow({ briefing, history, aiEnabled }: Props) {
    const { auth } = usePage().props;
    const [language, setLanguage] = useState<'en' | 'km'>('en');
    const [generating, setGenerating] = useState(false);
    const body = language === 'en' ? briefing?.body_en : briefing?.body_km;

    const generate = () =>
        router.post(
            store.url(),
            {},
            {
                onStart: () => setGenerating(true),
                onFinish: () => setGenerating(false),
            },
        );

    return (
        <>
            <Head title="Daily briefing" />
            <div className="grid flex-1 gap-4 p-4 lg:grid-cols-[1fr_14rem]">
                <article className="min-w-0 space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 print:hidden">
                        <div>
                            <h1 className="text-xl font-semibold">
                                Daily briefing{' '}
                                {briefing && `— ${briefing.date}`}
                            </h1>
                            {briefing?.generated_at && (
                                <p className="text-muted-foreground text-sm">
                                    Generated{' '}
                                    {formatDate(briefing.generated_at, true)} by
                                    AI from collected items. Check the cited
                                    items before acting.
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value={language}
                                onValueChange={(v) =>
                                    v && setLanguage(v as 'en' | 'km')
                                }
                            >
                                <ToggleGroupItem value="en">
                                    English
                                </ToggleGroupItem>
                                <ToggleGroupItem value="km">
                                    ខ្មែរ
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => window.print()}
                                disabled={!body}
                            >
                                <Printer /> Print / PDF
                            </Button>
                            {auth.can.analyze && aiEnabled && (
                                <Button
                                    size="sm"
                                    onClick={generate}
                                    disabled={generating}
                                >
                                    <RefreshCw
                                        className={cn(
                                            generating && 'animate-spin',
                                        )}
                                    />
                                    {generating
                                        ? 'Writing…'
                                        : "Write today's briefing"}
                                </Button>
                            )}
                        </div>
                    </div>

                    {!aiEnabled && (
                        <p className="rounded-xl border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100 print:hidden">
                            AI is switched off. Set{' '}
                            <code>ANTHROPIC_API_KEY</code> and{' '}
                            <code>CYBER_AI_ENABLED=true</code> to generate
                            briefings.
                        </p>
                    )}

                    {briefing === null && (
                        <p className="text-muted-foreground text-sm">
                            No briefings yet.
                        </p>
                    )}
                    {briefing?.status === 'failed' && (
                        <p className="text-sm text-red-600">
                            Generation failed: {briefing.error}
                        </p>
                    )}
                    {briefing?.status === 'empty' && (
                        <p className="text-muted-foreground text-sm">
                            No items were collected in the 24 hours before this
                            briefing.
                        </p>
                    )}
                    {body && (
                        <div
                            lang={language}
                            className="rounded-xl border p-4 text-sm leading-relaxed whitespace-pre-wrap sm:p-6 print:border-0 print:p-0"
                        >
                            <p className="mb-4 hidden font-semibold print:block">
                                Cambodia Cyber Watch — {briefing?.date}
                            </p>
                            {body}
                        </div>
                    )}
                </article>

                <aside className="print:hidden">
                    <h2 className="mb-2 text-sm font-medium">
                        Previous briefings
                    </h2>
                    <ul className="space-y-1 text-sm">
                        {history.map((b) => (
                            <li key={b.id}>
                                <Link
                                    href={show(b.id)}
                                    className={cn(
                                        'hover:bg-muted flex items-center justify-between rounded-md px-2 py-1',
                                        b.id === briefing?.id &&
                                            'bg-muted font-medium',
                                    )}
                                >
                                    {b.date}
                                    {b.status !== 'ready' && (
                                        <Badge
                                            variant="outline"
                                            className="text-[10px]"
                                        >
                                            {b.status}
                                        </Badge>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </aside>
            </div>
        </>
    );
}

BriefingShow.layout = {
    breadcrumbs: [{ title: 'Daily briefing', href: index() }],
};
