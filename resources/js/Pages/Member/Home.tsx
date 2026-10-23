import { useEffect, useState } from 'react';
import Scan from './Scan';
import { apiGet, ApiError } from '../../lib/api';
import { ThemeToggle } from '../../lib/theme';

interface Membership {
    membership_id: number;
    type: string;
    package_name: string;
    effective_status: string;
    remaining_days: number | null;
    remaining_visits: number | null;
    end_date: string | null;
}

interface ActiveSession {
    log_id: number;
    checkin_time: string;
    locker_state: string;
    locker_number: string | null;
}

interface Profile {
    user: { user_id: number; full_name: string; photo_url: string | null; phone_masked: string };
    membership: Membership | null;
    active_session: ActiveSession | null;
}

interface HistoryRow {
    log_id: number;
    checkin_time: string;
    checkout_time: string | null;
    locker_number: string | null;
}

type Tab = 'home' | 'scan' | 'history';

export default function MemberHome({ token }: { token: string }) {
    const [tab, setTab] = useState<Tab>('home');
    const [profile, setProfile] = useState<Profile | null>(null);
    const [history, setHistory] = useState<HistoryRow[]>([]);
    const [error, setError] = useState('');
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let alive = true;

        apiGet<Profile>('/api/v1/member/profile', token)
            .then((data) => alive && setProfile(data))
            .catch((caught: ApiError) => alive && setError(caught.message));

        return () => {
            alive = false;
        };
    }, [token]);

    useEffect(() => {
        if (tab !== 'history') {
            return;
        }

        apiGet<HistoryRow[]>('/api/v1/member/attendance/history', token)
            .then((data) => setHistory(data))
            .catch((caught: ApiError) => setError(caught.message));
    }, [tab, token]);

    return (
        <main className="mx-auto min-h-screen w-full max-w-[560px] bg-[var(--concrete)] px-5 pt-8 pb-24">
            <div className="flex items-start justify-between gap-3">
                <p className="font-[var(--font-display)] text-[12px] tracking-[0.18em] text-[var(--ink-muted)] uppercase">
                    GymPass QR
                </p>
                <ThemeToggle />
            </div>
            <h1 className="mt-1 font-[var(--font-display)] text-[var(--display-md-size)] leading-[var(--display-md-line)] font-bold">
                Halo, {profile?.user.full_name ?? '...'}
            </h1>

            {error !== '' && (
                <p role="alert" className="mt-4 rounded-[8px] border border-[var(--stop)] px-4 py-3 text-[14px] text-[var(--stop)]">
                    {error}
                </p>
            )}

            {tab === 'home' && profile !== null && (
                <>
                    <MembershipCard membership={profile.membership} />
                    <TodayCard session={profile.active_session} />
                    <p className="mt-6 rounded-[12px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-4 py-4 text-[13px] leading-5 text-[var(--ink-muted)]">
                        Pemindai QR kamera menyusul di milestone UI berikutnya. Sementara itu, minta kasir melakukan
                        check-in manual.
                    </p>
                </>
            )}

            {tab === 'scan' && (
                <Scan
                    token={token}
                    onDone={() => {
                        setTab('home');
                        setRefreshKey((key) => key + 1);
                    }}
                />
            )}

            {tab === 'history' && (
                <ul className="mt-6 space-y-3">
                    {history.length === 0 && (
                        <li className="text-[14px] text-[var(--ink-muted)]">Belum ada kunjungan.</li>
                    )}
                    {history.map((row) => (
                        <li key={row.log_id} className="rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-4 py-3">
                            <p className="text-[14px] font-semibold">{formatWib(row.checkin_time)}</p>
                            <p className="text-[12px] text-[var(--ink-muted)]">
                                {row.checkout_time === null ? 'Masih di dalam' : `Keluar ${formatWib(row.checkout_time)}`}
                                {row.locker_number !== null && ` · Loker #${row.locker_number}`}
                            </p>
                        </li>
                    ))}
                </ul>
            )}

            <nav className="fixed inset-x-0 bottom-0 mx-auto flex w-full max-w-[560px] items-stretch border-t border-[var(--line)] bg-[var(--surface)]">
                <TabButton active={tab === 'home'} onClick={() => setTab('home')}>Beranda</TabButton>
                <TabButton active={tab === 'scan'} onClick={() => setTab('scan')}>Pindai</TabButton>
                <TabButton active={tab === 'history'} onClick={() => setTab('history')}>Riwayat</TabButton>
            </nav>
        </main>
    );
}

function MembershipCard({ membership }: { membership: Membership | null }) {
    if (membership === null) {
        return (
            <div className="mt-6 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-4 py-5">
                <p className="text-[14px] text-[var(--ink-muted)]">Belum ada paket aktif. Hubungi kasir.</p>
            </div>
        );
    }

    const active = membership.effective_status === 'active';

    return (
        <div className="mt-6 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-4 py-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-[var(--font-display)] text-[22px] font-bold">{membership.package_name}</p>
                    <p className="mt-1 text-[13px] text-[var(--ink-muted)]">
                        {membership.remaining_days !== null
                            ? `${membership.remaining_days} hari lagi`
                            : `${membership.remaining_visits ?? 0} kunjungan tersisa`}
                    </p>
                </div>
                <span
                    className="rounded-full px-3 py-1 text-[12px] font-semibold text-white"
                    style={{ background: active ? 'var(--go)' : 'var(--stop)' }}
                >
                    {active ? 'Aktif' : 'Kedaluwarsa'}
                </span>
            </div>
        </div>
    );
}

function TodayCard({ session }: { session: ActiveSession | null }) {
    return (
        <div className="mt-3 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-4 py-5">
            <p className="text-[12px] tracking-[0.14em] text-[var(--ink-muted)] uppercase">Hari ini</p>
            {session === null ? (
                <p className="mt-2 text-[15px] font-semibold">Belum check-in</p>
            ) : (
                <>
                    <p className="mt-2 text-[15px] font-semibold">Masuk {formatWib(session.checkin_time)}</p>
                    <p className="mt-1 text-[13px] text-[var(--ink-muted)]">
                        {session.locker_state === 'assigned'
                            ? `Loker #${session.locker_number}`
                            : session.locker_state === 'pending'
                              ? 'Menunggu loker dari kasir'
                              : 'Tanpa loker'}
                    </p>
                </>
            )}
        </div>
    );
}

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className="min-h-14 flex-1 text-[14px] font-semibold"
            style={{ color: active ? 'var(--steel)' : 'var(--ink-muted)' }}
        >
            {children}
        </button>
    );
}

function formatWib(iso: string): string {
    return new Date(iso).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
