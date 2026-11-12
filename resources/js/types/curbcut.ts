export type Impact = 'critical' | 'serious' | 'moderate' | 'minor';
export type ScanStatus = 'queued' | 'running' | 'completed' | 'failed';
export type Frequency = 'manual' | 'daily' | 'weekly';

export interface ScanSummary {
    id: number;
    status: ScanStatus;
    trigger: 'manual' | 'scheduled';
    pages_total: number;
    pages_scanned: number;
    pages_failed: number;
    issues_open: number;
    issues_new: number;
    issues_fixed: number;
    critical: number;
    serious: number;
    moderate: number;
    minor: number;
    score: number | null;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
}

export interface SiteSummary {
    id: number;
    name: string;
    url: string;
    sitemap_url: string | null;
    max_pages: number;
    scan_frequency: Frequency;
    last_scanned_at: string | null;
    open_issues_count: number;
    latest_scan: ScanSummary | null;
    latest_completed_scan: ScanSummary | null;
}

export interface RuleGroup {
    rule_id: string;
    help: string;
    impact: Impact;
    total: number;
    pages: number;
}

export interface IssueRow {
    id: number;
    rule_id: string;
    impact: Impact;
    help: string;
    description: string | null;
    help_url: string | null;
    wcag: string[];
    target: string;
    html: string | null;
    failure_summary: string | null;
    status: 'open' | 'fixed';
    page: { id: number; url: string; title: string | null };
    first_seen_scan_id: number | null;
    fixed_in_scan_id: number | null;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}
