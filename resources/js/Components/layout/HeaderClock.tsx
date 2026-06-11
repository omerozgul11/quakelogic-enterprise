import { useEffect, useState } from 'react';

export function HeaderClock() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const date = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });

    return (
        <div className="hidden flex-col items-end leading-tight md:flex" title={now.toString()}>
            <span className="text-sm font-semibold tabular-nums text-foreground">{time}</span>
            <span className="text-[10px] font-medium text-muted-foreground">{date}</span>
        </div>
    );
}
