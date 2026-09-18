import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ALL = '__all';

type Props = {
    label: string;
    allLabel: string;
    value: string | null;
    options: { value: string; label: string }[];
    onChange: (value: string | null) => void;
    className?: string;
};

export function FilterSelect({
    label,
    allLabel,
    value,
    options,
    onChange,
    className = 'w-40',
}: Props) {
    return (
        <Select
            value={value ?? ALL}
            onValueChange={(v) => onChange(v === ALL ? null : v)}
        >
            <SelectTrigger className={className} aria-label={label}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
