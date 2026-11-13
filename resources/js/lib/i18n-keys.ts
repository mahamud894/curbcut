import { tk } from '@/lib/i18n';

/**
 * Strings that are translated from data (status values, impacts, roles) rather than written
 * in a component, listed here so `php artisan curbcut:translations` finds them.
 */
export const dynamicKeys = [
    // Impacts, as shown in badges and filters
    tk('critical'),
    tk('serious'),
    tk('moderate'),
    tk('minor'),
    tk('Critical'),
    tk('Serious'),
    tk('Moderate'),
    tk('Minor'),
    // Scan and check statuses
    tk('Queued'),
    tk('Running'),
    tk('Completed'),
    tk('Failed'),
    // Issue statuses
    tk('Open'),
    tk('Fixed'),
    tk('Dismissed'),
    // Workspace roles
    tk('Owner'),
    tk('Admin'),
    tk('Member'),
    tk('Guest'),
    // Dismiss reasons and conformance labels (from the server)
    tk('False positive'),
    tk("Won't fix"),
    tk('Not applicable'),
    tk('Fully conformant'),
    tk('Partially conformant'),
    tk('Not conformant'),
    tk('WCAG 2.2 level AA'),
];
