import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck, FileCheck2, GitCompareArrows, ScanSearch } from 'lucide-react';

const features = [
    {
        icon: ScanSearch,
        title: 'Scan every page',
        text: 'Finds pages from your sitemap or by following links, then checks each one with axe-core against WCAG 2.2 AA.',
    },
    {
        icon: GitCompareArrows,
        title: 'See what changed',
        text: 'Every scan is compared with the last one, so you know exactly which problems are new and which got fixed.',
    },
    {
        icon: ClipboardCheck,
        title: 'Turn findings into work',
        text: 'Issues are grouped by problem and page, with the failing element, the WCAG criterion and how to fix it.',
    },
    {
        icon: FileCheck2,
        title: 'Keep the evidence',
        text: 'A full scan history you control, on your own server, for audits and accessibility statements.',
    },
];

export default function Welcome() {
    const { auth, canRegister } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Open-source accessibility compliance" />
            <div className="bg-background text-foreground min-h-screen">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                    <Link href="/" className="flex items-center gap-2 font-semibold">
                        <span className="bg-foreground text-background flex size-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        Curbcut
                    </Link>
                    <nav aria-label="Account" className="flex items-center gap-2 text-sm">
                        {auth.user ? (
                            <Link href="/sites" className="bg-foreground text-background rounded-md px-4 py-2 font-medium">
                                Open dashboard
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="hover:bg-muted rounded-md px-4 py-2 font-medium">
                                    Log in
                                </Link>
                                {canRegister && (
                                    <Link href={route('register')} className="bg-foreground text-background rounded-md px-4 py-2 font-medium">
                                        Create account
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto max-w-4xl px-6 pt-16 pb-12 text-center">
                        <p className="text-muted-foreground mb-4 text-sm font-medium">Free and open source · Self-hosted · WCAG 2.2 AA</p>
                        <h1 className="text-4xl font-bold tracking-tight text-balance sm:text-5xl">Find, fix and prove web accessibility.</h1>
                        <p className="text-muted-foreground mx-auto mt-5 max-w-2xl text-lg text-balance">
                            Curbcut monitors your websites for accessibility barriers, tracks every fix over time and keeps the evidence on your own
                            server.
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <Link
                                href={auth.user ? '/sites' : canRegister ? route('register') : route('login')}
                                className="bg-foreground text-background rounded-md px-5 py-2.5 font-medium"
                            >
                                {auth.user ? 'Go to your sites' : 'Get started'}
                            </Link>
                            <a href="https://github.com/mahamud894/curbcut" className="hover:bg-muted rounded-md border px-5 py-2.5 font-medium">
                                View on GitHub
                            </a>
                        </div>
                    </section>

                    <section aria-labelledby="features-heading" className="mx-auto max-w-6xl px-6 pb-20">
                        <h2 id="features-heading" className="sr-only">
                            Features
                        </h2>
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {features.map(({ icon: Icon, title, text }) => (
                                <li key={title} className="bg-card rounded-xl border p-5">
                                    <Icon className="mb-3 size-6" aria-hidden="true" />
                                    <h3 className="font-semibold">{title}</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">{text}</p>
                                </li>
                            ))}
                        </ul>
                    </section>
                </main>

                <footer className="text-muted-foreground border-t py-6 text-center text-sm">
                    Curbcut is free software under the GNU AGPL-3.0. Named after the curb cut: accessibility that helps everyone.
                </footer>
            </div>
        </>
    );
}
