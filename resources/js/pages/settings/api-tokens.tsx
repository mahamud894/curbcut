import { Flash } from '@/components/curbcut/flash';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatDate, relativeTime } from '@/lib/format';
import { t, tk } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Token = { id: number; name: string; abilities: string[]; last_used_at: string | null; created_at: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: tk('API tokens'), href: '/settings/api-tokens' }];

export default function ApiTokens({ tokens, newToken, apiUrl }: { tokens: Token[]; newToken: string | null; apiUrl: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ name: string; can_scan: boolean }>({ name: '', can_scan: false });
    const [copied, setCopied] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('api-tokens.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    const copy = async () => {
        if (!newToken) return;
        await navigator.clipboard.writeText(newToken);
        setCopied(true);
    };

    const revoke = (token: Token) => {
        if (window.confirm(t('Revoke “{name}”? Anything using it will stop working.', { name: token.name }))) {
            router.delete(route('api-tokens.destroy', token.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('API tokens')} />

            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall
                        title={t('API tokens')}
                        description={t('Tokens let scripts, CI pipelines and the Curbcut GitHub Action use the REST API with your access.')}
                    />

                    <Flash />

                    {newToken && (
                        <div className="space-y-2 rounded-lg border p-4">
                            <p className="text-sm font-medium" id="new-token-label">
                                {t("Your new token. Copy it now: you won't be able to see it again.")}
                            </p>
                            <div className="flex gap-2">
                                <Input
                                    readOnly
                                    value={newToken}
                                    aria-labelledby="new-token-label"
                                    className="font-mono text-sm"
                                    onFocus={(e) => e.target.select()}
                                />
                                <Button type="button" variant="outline" onClick={copy}>
                                    {copied ? <Check aria-hidden="true" /> : <Copy aria-hidden="true" />}
                                    {copied ? t('Copied') : t('Copy')}
                                </Button>
                            </div>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <h3 className="text-base font-semibold">{t('Create a token')}</h3>
                        <div className="grid gap-2">
                            <Label htmlFor="token-name">{t('Name')}</Label>
                            <Input
                                id="token-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="GitHub Actions"
                                required
                                aria-describedby="token-name-hint"
                            />
                            <p id="token-name-hint" className="text-muted-foreground text-sm">
                                {t('Something that reminds you where the token is used.')}
                            </p>
                            <InputError message={errors.name} />
                        </div>
                        <div className="flex items-start gap-3">
                            <Checkbox id="can-scan" checked={data.can_scan} onCheckedChange={(v) => setData('can_scan', v === true)} />
                            <div className="grid gap-1">
                                <Label htmlFor="can-scan">{t('Allow starting scans and checks')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('Needed for the GitHub Action. Without it the token can only read sites, scans, issues and reports.')}
                                </p>
                            </div>
                        </div>
                        <Button disabled={processing}>{t('Create token')}</Button>
                    </form>

                    <section aria-labelledby="tokens-heading" className="space-y-3">
                        <h3 id="tokens-heading" className="text-base font-semibold">
                            {t('Your tokens')}
                        </h3>
                        {tokens.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('You have no tokens yet.')}</p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {tokens.map((token) => (
                                    <li key={token.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">{token.name}</p>
                                            <p className="text-muted-foreground text-sm">
                                                {token.abilities.includes('scan') ? t('Read and scan') : t('Read only')} ·{' '}
                                                {t('created {date}', { date: formatDate(token.created_at) })} ·{' '}
                                                {token.last_used_at
                                                    ? t('last used {when}', { when: relativeTime(token.last_used_at) })
                                                    : t('never used')}
                                            </p>
                                        </div>
                                        <Button type="button" variant="outline" size="sm" className="shrink-0" onClick={() => revoke(token)}>
                                            {t('Revoke')}
                                            <span className="sr-only"> {token.name}</span>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section aria-labelledby="usage-heading" className="space-y-2">
                        <h3 id="usage-heading" className="text-base font-semibold">
                            {t('Using the API')}
                        </h3>
                        <p className="text-muted-foreground text-sm">{t('Send the token in the Authorization header:')}</p>
                        <pre className="bg-muted overflow-x-auto rounded-md p-3 text-xs">
                            <code>{`curl -H "Authorization: Bearer YOUR_TOKEN" ${apiUrl}/sites`}</code>
                        </pre>
                        <p className="text-sm">
                            <a
                                className="underline underline-offset-4"
                                href="https://github.com/mahamud894/curbcut/blob/main/docs/api.md"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {t('API reference (opens in a new tab)')}
                            </a>
                        </p>
                    </section>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
