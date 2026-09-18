export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

export type Entities = {
    orgs?: string[];
    domains?: string[];
    cves?: string[];
    threat_actors?: string[];
};

export type ItemRow = {
    id: number;
    url: string;
    title: string;
    title_en: string | null;
    summary: string | null;
    publisher: string | null;
    kind: string;
    country: string | null;
    language: string | null;
    category: string;
    severity: number;
    is_cambodia: boolean;
    entities: Entities | null;
    watch_hits: string[] | null;
    enrichment_status: string;
    has_screenshot: boolean;
    notes: string | null;
    incidents: { id: number; title: string }[];
    published_at: string | null;
};

export type IncidentOption = { id: number; title: string };
