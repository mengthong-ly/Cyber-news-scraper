import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.links.length <= 3) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-2 text-sm"
        >
            <span className="text-muted-foreground">
                {page.from}–{page.to} of {page.total}
            </span>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, i) =>
                    link.url ? (
                        <Button
                            key={i}
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                            asChild
                        >
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        </Button>
                    ) : (
                        <Button
                            key={i}
                            size="sm"
                            variant="outline"
                            disabled
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </nav>
    );
}
