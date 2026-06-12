import { useEffect, useState } from 'react';
import { APP_TZ, pacificLabel } from '@/Lib/utils';

export function HeaderClock() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    // The whole platform runs on Pacific time, so the header clock is pinned to
    // PST/PDT regardless of where the viewer's browser is.
    const time = now.toLocaleTimeString('en-US', { timeZone: APP_TZ, hour: '2-digit', minute: '2-digit' });
    const date = now.toLocaleDateString('en-US', { timeZone: APP_TZ, weekday: 'short', month: 'short', day: 'numeric' });
    const tz = pacificLabel(now);

    return (
        <div className="hidden flex-col items-end leading-tight md:flex" title={`${now.toLocaleString('en-US', { timeZone: APP_TZ })} (${tz})`}>
            <span className="text-sm font-semibold tabular-nums text-foreground">{time} <span className="text-[10px] font-semibold text-muted-foreground">{tz}</span></span>
            <span className="text-[10px] font-medium text-muted-foreground">{date}</span>
        </div>
    );
}
