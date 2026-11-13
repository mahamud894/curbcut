import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, LayoutGrid, Plus, Settings } from 'lucide-react';

export function WorkspaceSwitcher() {
    const { workspace } = usePage<SharedData>().props;
    if (!workspace) return null;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent" data-testid="workspace-switcher">
                            <span
                                className="bg-sidebar-primary text-sidebar-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-md text-sm font-semibold"
                                aria-hidden="true"
                            >
                                {workspace.current.name.charAt(0).toUpperCase()}
                            </span>
                            <span className="grid min-w-0 flex-1 text-left leading-tight">
                                <span className="text-sidebar-foreground text-xs">{t('Workspace')}</span>
                                <span className="truncate text-sm font-semibold">{workspace.current.name}</span>
                            </span>
                            <ChevronsUpDown className="ml-auto size-4" aria-hidden="true" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-64" align="start">
                        <DropdownMenuLabel>{t('Switch workspace')}</DropdownMenuLabel>
                        {workspace.all.map((w) => (
                            <DropdownMenuItem key={w.id} onSelect={() => w.id !== workspace.current.id && router.post(`/workspaces/${w.id}/switch`)}>
                                <span className="flex-1 truncate">{w.name}</span>
                                {w.id === workspace.current.id && <Check className="size-4" aria-label={t('current')} />}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href="/workspaces">
                                <LayoutGrid aria-hidden="true" /> {t('All workspaces')}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={`/workspaces/${workspace.current.id}/settings`}>
                                <Settings aria-hidden="true" /> {t('Workspace settings')}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href="/workspaces?create=1">
                                <Plus aria-hidden="true" /> {t('New workspace')}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
