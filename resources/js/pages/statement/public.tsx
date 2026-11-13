import { StatementDocument, type PublicStatement } from '@/components/curbcut/statement-document';
import { Head } from '@inertiajs/react';

export default function PublicStatementPage({ statement }: { statement: PublicStatement }) {
    return (
        <>
            <Head title={`Accessibility statement · ${statement.site_name}`} />
            <div className="bg-background text-foreground min-h-screen">
                <a
                    href="#statement"
                    className="focus:bg-background sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:rounded focus:px-3 focus:py-2"
                >
                    Skip to statement
                </a>
                <main id="statement" className="mx-auto max-w-3xl px-6 py-12">
                    <StatementDocument s={statement} />
                </main>
            </div>
        </>
    );
}
