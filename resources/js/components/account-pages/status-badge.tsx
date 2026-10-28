import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';

const done = ['completed'];
const stopped = ['cancelled', 'rejected'];

export function StatusBadge({ status }: { status: string }) {
    const t = useT();
    const variant = done.includes(status)
        ? 'leaf'
        : stopped.includes(status)
          ? 'destructive'
          : 'turmeric';

    return <Badge variant={variant}>{t(`account.status.${status}`)}</Badge>;
}
