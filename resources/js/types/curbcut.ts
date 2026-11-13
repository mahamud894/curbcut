export type Impact = 'critical' | 'serious' | 'moderate' | 'minor';
export type ScanStatus = 'queued' | 'running' | 'completed' | 'failed';
export type Frequency = 'manual' | 'daily' | 'weekly';

export interface ScanSummary {
    id: number;
    status: ScanStatus;
    trigger: 'manual' | 'scheduled' | 'api';
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
    shared?: boolean;
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
    status: 'open' | 'fixed' | 'dismissed';
    page: { id: number; url: string; title: string | null };
    first_seen_scan_id: number | null;
    fixed_in_scan_id: number | null;
    assignee?: { id: number; name: string } | null;
    due_on?: string | null;
    overdue?: boolean;
}

export interface TeamMember {
    id: number;
    name: string;
}

export interface ActivityRow {
    id: number;
    action: string;
    properties: Record<string, string | null> | null;
    user: string | null;
    subject_id?: number | null;
    created_at: string | null;
}

export type ChecklistStatus = 'not_tested' | 'pass' | 'fail' | 'not_applicable';

export interface Criterion {
    id: string;
    title: string;
    level: 'A' | 'AA';
    principle: string;
    url: string;
    status: ChecklistStatus;
    notes: string | null;
    tested_by: string | null;
    tested_at: string | null;
    automated_issues: number;
}

export interface Assessment {
    criteria: Criterion[];
    counts: { pass: number; fail: number; not_applicable: number; not_tested: number; total: number };
    suggested: 'full' | 'partial' | 'none';
    complete: boolean;
    failing: { id: string; title: string; notes: string | null; automated: number }[];
    automated_rules: { help: string; total: number; criteria: string[] }[];
    last_tested_at: string | null;
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
