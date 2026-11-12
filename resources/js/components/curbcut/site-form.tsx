import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Frequency, SiteSummary } from '@/types/curbcut';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';

const frequencies: { value: Frequency; label: string }[] = [
    { value: 'weekly', label: 'Weekly' },
    { value: 'daily', label: 'Daily' },
    { value: 'manual', label: 'Only when I start it' },
];

export function SiteForm({ site, onSuccess }: { site?: SiteSummary; onSuccess?: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: site?.name ?? '',
        url: site?.url ?? '',
        sitemap_url: site?.sitemap_url ?? '',
        max_pages: site?.max_pages ?? 50,
        scan_frequency: site?.scan_frequency ?? ('weekly' as Frequency),
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onSuccess?.() };
        if (site) put(`/sites/${site.id}`, options);
        else post('/sites', options);
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <div className="grid gap-2">
                <Label htmlFor="name">Site name</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="Acme online shop"
                    required
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={errors.name ? 'name-error' : undefined}
                />
                <InputError id="name-error" message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="url">Start URL</Label>
                <Input
                    id="url"
                    type="url"
                    inputMode="url"
                    value={data.url}
                    onChange={(e) => setData('url', e.target.value)}
                    placeholder="https://www.example.com"
                    required
                    aria-invalid={Boolean(errors.url)}
                    aria-describedby="url-help"
                />
                <p id="url-help" className="text-muted-foreground text-xs">
                    Curbcut scans this page and the pages linked from it on the same domain.
                </p>
                <InputError message={errors.url} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="sitemap_url">
                    Sitemap URL <span className="text-muted-foreground font-normal">(optional)</span>
                </Label>
                <Input
                    id="sitemap_url"
                    type="url"
                    inputMode="url"
                    value={data.sitemap_url}
                    onChange={(e) => setData('sitemap_url', e.target.value)}
                    placeholder="https://www.example.com/sitemap.xml"
                    aria-describedby="sitemap-help"
                />
                <p id="sitemap-help" className="text-muted-foreground text-xs">
                    Leave empty to use /sitemap.xml when it exists, or to follow links from the start URL.
                </p>
                <InputError message={errors.sitemap_url} />
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="max_pages">Maximum pages per scan</Label>
                    <Input
                        id="max_pages"
                        type="number"
                        min={1}
                        max={500}
                        value={data.max_pages}
                        onChange={(e) => setData('max_pages', Number(e.target.value))}
                    />
                    <InputError message={errors.max_pages} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="scan_frequency">Automatic scans</Label>
                    <select
                        id="scan_frequency"
                        value={data.scan_frequency}
                        onChange={(e) => setData('scan_frequency', e.target.value as Frequency)}
                        className="border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:outline-none"
                    >
                        {frequencies.map((f) => (
                            <option key={f.value} value={f.value}>
                                {f.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.scan_frequency} />
                </div>
            </div>
            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                    {site ? 'Save settings' : 'Add site and start scan'}
                </Button>
            </div>
        </form>
    );
}
