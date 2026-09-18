import { Head, router } from '@inertiajs/react';
import { FilterSelect } from '@/components/cyber/filter-select';
import { Pagination } from '@/components/cyber/pagination';
import { formatDate } from '@/lib/categories';
import { index } from '@/routes/admin/audit';
import type { Paginated } from '@/types';

type Log = {
    id: number;
    user: string | null;
    action: string;
    subject: string | null;
    meta: Record<string, unknown> | null;
    ip: string | null;
    created_at: string;
};

type Props = { logs: Paginated<Log>; actions: string[]; action: string | null };

export default function Audit({ logs, actions, action }: Props) {
    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Audit log</h1>
                    <FilterSelect
                        label="Action"
                        allLabel="All actions"
                        className="w-48"
                        value={action}
                        options={actions.map((a) => ({
                            value: a,
                            label: a.replaceAll('_', ' '),
                        }))}
                        onChange={(value) =>
                            router.get(
                                index.url(),
                                value ? { action: value } : {},
                                { preserveState: true },
                            )
                        }
                    />
                </div>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">When</th>
                                <th className="px-3 py-2 font-medium">User</th>
                                <th className="px-3 py-2 font-medium">
                                    Action
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Details
                                </th>
                                <th className="px-3 py-2 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-t align-top">
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {formatDate(log.created_at, true)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {log.user ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {log.action.replaceAll('_', ' ')}
                                    </td>
                                    <td className="max-w-md px-3 py-2 break-words">
                                        {log.subject}
                                        {log.meta && (
                                            <code className="text-muted-foreground mt-1 block text-xs">
                                                {JSON.stringify(log.meta)}
                                            </code>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {log.ip}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={logs} />
            </div>
        </>
    );
}

Audit.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
