import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { ApiError, sessionGet, sessionPatch } from '../../lib/api';

interface Session {
    log_id: number;
    member: { user_id: number; full_name: string; photo_url: string | null };
    membership_type: string;
    effective_status: string;
    checkin_time: string;
    locker_state: string;
    locker_number: string | null;
    identity_verified_at: string | null;
}

interface Locker {
    locker_id: number;
    locker_number: string;
    status: string;
    occupant: { log_id: number; full_name: string; checkin_time: string } | null;
}

type Filter = 'all' | 'need_locker' | 'assigned' | 'no_locker' | 'unverified';

const FILTERS: Array<{ key: Filter; label: string }> = [
    { key: 'all', label: 'Semua' },
    { key: 'need_locker', label: 'Butuh loker' },
    { key: 'assigned', label: 'Loker terpasang' },
    { key: 'no_locker', label: 'Tanpa loker' },
    { key: 'unverified', label: 'Belum verifikasi' },
];

export default function CashierDashboard() {
    const [sessions, setSessions] = useState<Session[]>([]);
    const [lockers, setLockers] = useState<Locker[]>([]);
    const [filter, setFilter] = useState<Filter>('all');
    const [selected, setSelected] = useState<number | null>(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [online, setOnline] = useState(true);

    const refresh = useCallback(async () => {
        try {
            const [sessionData, lockerData] = await Promise.all([
                sessionGet<{ sessions: Session[] }>(`/api/v1/receptionist/sessions?filter=${filter}`),
                sessionGet<{ lockers: Locker[] }>('/api/v1/receptionist/lockers'),
            ]);

            setSessions(sessionData.sessions);
            setLockers(lockerData.lockers);
            setOnline(true);
        } catch {
            setOnline(false);
        }
    }, [filter]);

    useEffect(() => {
        void refresh();
        const timer = window.setInterval(() => void refresh(), 10000);

        return () => window.clearInterval(timer);
    }, [refresh]);

    async function act(path: string, body: Record<string, unknown> = {}) {
        setError('');
        setMessage('');

        try {
            await sessionPatch(path, body);
            setSelected(null);
            setMessage('Tersimpan.');
            await refresh();
        } catch (caught) {
            setError((caught as ApiError).message ?? 'Aksi gagal.');
            await refresh();
        }
    }

    return (
        <>
            <Head title="Dasbor kasir" />
            <main className="min-h-screen bg-[var(--concrete)]">
                <header className="flex flex-wrap items-center gap-3 border-b border-[var(--line)] bg-[var(--surface)] px-5 py-3">
                    <p className="font-[var(--font-display)] text-[15px] font-bold tracking-[0.12em] uppercase">GymPass QR</p>
                    <span className="text-[13px] text-[var(--ink-muted)]">Meja depan</span>
                    <span
                        className="ml-auto rounded-full px-3 py-1 text-[12px] font-semibold text-white"
                        style={{ background: online ? 'var(--go)' : 'var(--stop)' }}
                    >
                        {online ? 'Terhubung' : 'Terputus'}
                    </span>
                    <button type="button" onClick={() => void refresh()} className="min-h-11 rounded-[8px] border border-[var(--line)] px-4 text-[13px] font-semibold">
                        Muat ulang
                    </button>
                    <button
                        type="button"
                        onClick={async () => {
                            await fetch('/api/v1/receptionist/auth/logout', { method: 'POST', credentials: 'same-origin' });
                            window.location.assign('/kasir/login');
                        }}
                        className="min-h-11 rounded-[8px] border border-[var(--line)] px-4 text-[13px] font-semibold text-[var(--stop)]"
                    >
                        Keluar
                    </button>
                </header>

                {(error !== '' || message !== '') && (
                    <p
                        aria-live="polite"
                        className="border-b border-[var(--line)] px-5 py-2 text-[13px]"
                        style={{ color: error !== '' ? 'var(--stop)' : 'var(--go)' }}
                    >
                        {error !== '' ? error : message}
                    </p>
                )}

                <div className="grid gap-4 p-4 lg:grid-cols-[1.1fr_1fr]">
                    <section>
                        <div className="flex flex-wrap gap-2">
                            {FILTERS.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setFilter(item.key)}
                                    aria-pressed={filter === item.key}
                                    className="min-h-11 rounded-full border px-4 text-[13px] font-semibold"
                                    style={{
                                        borderColor: filter === item.key ? 'var(--steel)' : 'var(--line)',
                                        color: filter === item.key ? 'var(--steel)' : 'var(--ink-muted)',
                                        background: filter === item.key ? 'var(--surface)' : 'transparent',
                                    }}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>

                        <ul className="mt-4 space-y-3">
                            {sessions.length === 0 && (
                                <li className="rounded-[12px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-4 py-6 text-center text-[13px] text-[var(--ink-muted)]">
                                    Belum ada kedatangan. Data baru muncul otomatis.
                                </li>
                            )}

                            {sessions.map((session) => (
                                <li
                                    key={session.log_id}
                                    className="rounded-[12px] border bg-[var(--surface)] p-4"
                                    style={{ borderColor: selected === session.log_id ? 'var(--plate)' : 'var(--line)' }}
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[8px] bg-[var(--concrete)] text-[13px] font-bold text-[var(--ink-muted)]">
                                            {session.member.photo_url ? (
                                                <img src={session.member.photo_url} alt="" className="h-full w-full object-cover" />
                                            ) : (
                                                initials(session.member.full_name)
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-[16px] font-semibold">{session.member.full_name}</p>
                                            <p className="text-[12px] text-[var(--ink-muted)]">
                                                {session.membership_type === 'single_visit' ? 'Day Pass' : 'Bulanan'} ·{' '}
                                                {session.effective_status} · {timeWib(session.checkin_time)}
                                            </p>
                                            <p className="mt-1 text-[12px] font-semibold">
                                                {session.locker_state === 'assigned'
                                                    ? `Loker #${session.locker_number}`
                                                    : session.locker_state === 'pending'
                                                      ? 'Butuh loker'
                                                      : 'Tanpa loker'}
                                                {session.identity_verified_at === null && ' · Identitas belum dikonfirmasi'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {session.locker_state !== 'assigned' && (
                                            <SmallButton primary onClick={() => setSelected(session.log_id)}>
                                                Pilih loker
                                            </SmallButton>
                                        )}
                                        {session.locker_state === 'assigned' && (
                                            <SmallButton onClick={() => void act(`/api/v1/receptionist/attendance/${session.log_id}/release-locker`)}>
                                                Kembalikan kunci
                                            </SmallButton>
                                        )}
                                        {session.identity_verified_at === null && (
                                            <SmallButton onClick={() => void act(`/api/v1/receptionist/attendance/${session.log_id}/verify-identity`)}>
                                                Identitas cocok
                                            </SmallButton>
                                        )}
                                        {session.locker_state === 'pending' && (
                                            <SmallButton onClick={() => void act(`/api/v1/receptionist/attendance/${session.log_id}/no-locker`)}>
                                                Tanpa loker
                                            </SmallButton>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section>
                        <h2 className="font-[var(--font-display)] text-[22px] font-bold">Dinding loker</h2>
                        <p className="mt-1 text-[12px] text-[var(--ink-muted)]">
                            {selected === null ? 'Pilih sesi dulu, lalu ketuk pintu loker.' : 'Ketuk pintu loker kosong untuk assign.'}
                        </p>

                        <div className="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-6">
                            {lockers.map((locker) => {
                                const available = locker.status === 'available';
                                const usable = selected !== null && available;

                                return (
                                    <button
                                        key={locker.locker_id}
                                        type="button"
                                        disabled={!usable}
                                        onClick={() => selected !== null && void act(`/api/v1/receptionist/attendance/${selected}/assign-locker`, { locker_id: locker.locker_id })}
                                        title={locker.occupant?.full_name ?? locker.status}
                                        className="flex aspect-square flex-col items-center justify-center rounded-[4px] border text-[15px] font-bold"
                                        style={{
                                            borderColor: available ? 'var(--line)' : 'var(--steel)',
                                            background: available ? 'var(--surface)' : locker.status === 'occupied' ? 'var(--steel)' : 'var(--concrete)',
                                            color: available ? 'var(--ink)' : locker.status === 'occupied' ? 'white' : 'var(--ink-muted)',
                                            opacity: usable ? 1 : 0.55,
                                        }}
                                    >
                                        <span className="font-[var(--font-display)]">{locker.locker_number}</span>
                                        {locker.status === 'out_of_service' && <span className="text-[9px]">rusak</span>}
                                    </button>
                                );
                            })}
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}

function SmallButton({ children, onClick, primary = false }: { children: string; onClick: () => void; primary?: boolean }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="min-h-11 rounded-[8px] border px-3 text-[13px] font-semibold"
            style={{
                borderColor: primary ? 'var(--steel)' : 'var(--line)',
                background: primary ? 'var(--steel)' : 'transparent',
                color: primary ? 'white' : 'var(--ink)',
            }}
        >
            {children}
        </button>
    );
}

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function timeWib(iso: string): string {
    return new Date(iso).toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' });
}
