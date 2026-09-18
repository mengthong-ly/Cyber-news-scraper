import { Badge } from '@/components/ui/badge';
import { categoryStyles, severityStyles } from '@/lib/categories';
import { cn } from '@/lib/utils';

export function SeverityBadge({ severity }: { severity: number }) {
    return (
        <span
            title={`Severity ${severity} of 5`}
            className={cn(
                'inline-flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-semibold tabular-nums',
                severityStyles[severity] ?? severityStyles[1],
            )}
        >
            {severity}
        </span>
    );
}

export function CategoryBadge({ category }: { category: string }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'border-transparent capitalize',
                categoryStyles[category] ?? categoryStyles.general,
            )}
        >
            {category}
        </Badge>
    );
}
