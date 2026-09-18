import { Form, Head, router, usePage } from '@inertiajs/react';
import { ShieldCheck, ShieldOff, Trash2 } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/categories';
import { destroy, index, update } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    two_factor: boolean;
    created_at: string;
};

const fieldClass =
    'border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs dark:bg-input/30';

const roleHelp: Record<string, string> = {
    admin: 'Everything, including users and the audit log',
    analyst: 'Triage alerts, manage incidents, sources and the watchlist',
    viewer: 'Read-only access to the feed, alerts, incidents and briefings',
};

export default function Users({
    users,
    roles,
}: {
    users: UserRow[];
    roles: string[];
}) {
    const { auth, errors } = usePage().props as {
        auth: { user: { id: number } };
        errors: Record<string, string>;
    };

    return (
        <>
            <Head title="Users" />
            <div className="grid flex-1 gap-4 p-4 lg:grid-cols-[1fr_22rem]">
                <div className="min-w-0 space-y-2">
                    <h1 className="text-xl font-semibold">Users</h1>
                    <InputError message={errors.role ?? errors.user} />
                    <ul className="divide-y rounded-xl border">
                        {users.map((user) => (
                            <li
                                key={user.id}
                                className="flex flex-wrap items-center gap-3 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">{user.name}</p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {user.email}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        Added {formatDate(user.created_at)}
                                    </p>
                                </div>
                                <span
                                    className="flex items-center gap-1 text-xs"
                                    title={
                                        user.two_factor
                                            ? 'Two-factor authentication on'
                                            : 'Two-factor authentication not set up yet'
                                    }
                                >
                                    {user.two_factor ? (
                                        <ShieldCheck className="size-4 text-emerald-600" />
                                    ) : (
                                        <ShieldOff className="size-4 text-amber-600" />
                                    )}
                                    {user.two_factor ? '2FA' : 'No 2FA'}
                                </span>
                                <select
                                    aria-label={`Role for ${user.name}`}
                                    className={`${fieldClass} w-32`}
                                    value={user.role}
                                    onChange={(e) =>
                                        router.patch(
                                            update.url(user.id),
                                            { role: e.target.value },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {roles.map((r) => (
                                        <option key={r} value={r}>
                                            {r.charAt(0).toUpperCase() +
                                                r.slice(1)}
                                        </option>
                                    ))}
                                </select>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Delete ${user.name}`}
                                    disabled={user.id === auth.user.id}
                                    onClick={() => {
                                        if (
                                            confirm(
                                                `Delete ${user.email}? This cannot be undone.`,
                                            )
                                        ) {
                                            router.delete(
                                                destroy.url(user.id),
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>

                <Card className="h-fit gap-4">
                    <CardHeader>
                        <CardTitle>Add user</CardTitle>
                        <CardDescription>
                            Share the password in person or through a secure
                            channel. The user must turn on two-factor
                            authentication before using the tool.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...UserController.store.form()}
                            resetOnSuccess
                            className="space-y-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="role">Role</Label>
                                        <select
                                            id="role"
                                            name="role"
                                            className={fieldClass}
                                            defaultValue="viewer"
                                        >
                                            {roles.map((r) => (
                                                <option key={r} value={r}>
                                                    {r.charAt(0).toUpperCase() +
                                                        r.slice(1)}{' '}
                                                    — {roleHelp[r]}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="password">
                                            Initial password
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <Button disabled={processing}>
                                        Create user
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
