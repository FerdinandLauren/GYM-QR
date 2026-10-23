import { useEffect, useState } from 'react';
import { ApiError, sessionGet, sessionPost } from '../../lib/api';

interface Package {
    package_id: number;
    code: string;
    name: string;
    type: string;
    price: number;
}

interface MemberRow {
    user_id: number;
    full_name: string;
    phone_masked: string;
    memberships: Array<{ membership_id: number; type: string; effective_status: string }>;
}

const REASONS = [
    ['no_phone', 'Tidak membawa HP'],
    ['battery_dead', 'HP mati'],
    ['camera_broken', 'Kamera rusak'],
    ['gps_issue', 'GPS bermasalah'],
    ['other', 'Lainnya'],
] as const;

export function ManualCheckInPanel({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
    const [query, setQuery] = useState('');
    const [members, setMembers] = useState<MemberRow[]>([]);
    const [member, setMember] = useState<MemberRow | null>(null);
    const [membershipId, setMembershipId] = useState('');
    const [reason, setReason] = useState<string>('no_phone');
    const [intent, setIntent] = useState('no_locker');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (query.trim().length < 3) {
            setMembers([]);

            return;
        }

        const timer = window.setTimeout(() => {
            sessionGet<{ members: MemberRow[] }>(`/api/v1/receptionist/members?q=${encodeURIComponent(query)}`)
                .then((data) => setMembers(data.members))
                .catch(() => setMembers([]));
        }, 300);

        return () => window.clearTimeout(timer);
    }, [query]);

    async function submit() {
        if (member === null || membershipId === '') {
            setError('Pilih member dan paket dulu.');

            return;
        }

        setBusy(true);
        setError('');

        try {
            await sessionPost(
                '/api/v1/receptionist/manual-checkin',
                {
                    user_id: member.user_id,
                    membership_id: Number(membershipId),
                    locker_intent: intent,
                    reason,
                },
                { 'Idempotency-Key': crypto.randomUUID() },
            );
            onDone();
        } catch (caught) {
            setError((caught as ApiError).message ?? 'Check-in manual gagal.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Panel title="Check-in manual" onClose={onClose}>
            <input
                autoFocus
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Cari nama atau nomor HP (min. 3 huruf)"
                className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]"
            />

            {members.length > 0 && (
                <ul className="mt-3 space-y-2">
                    {members.map((row) => (
                        <li key={row.user_id}>
                            <button
                                type="button"
                                onClick={() => {
                                    setMember(row);
                                    setMembershipId(row.memberships[0] ? String(row.memberships[0].membership_id) : '');
                                }}
                                className="w-full rounded-[8px] border px-3 py-2 text-left text-[14px]"
                                style={{ borderColor: member?.user_id === row.user_id ? 'var(--steel)' : 'var(--line)' }}
                            >
                                <span className="font-semibold">{row.full_name}</span>
                                <span className="ml-2 text-[12px] text-[var(--ink-muted)]">{row.phone_masked}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {member !== null && (
                <>
                    <label className="mt-4 block">
                        <span className="mb-1 block text-[13px] font-semibold">Paket</span>
                        <select
                            value={membershipId}
                            onChange={(event) => setMembershipId(event.target.value)}
                            className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]"
                        >
                            <option value="">Pilih paket</option>
                            {member.memberships.map((row) => (
                                <option key={row.membership_id} value={row.membership_id}>
                                    {row.type === 'single_visit' ? 'Day Pass' : 'Bulanan'} · {row.effective_status}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="mt-3 block">
                        <span className="mb-1 block text-[13px] font-semibold">Alasan manual</span>
                        <select
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]"
                        >
                            {REASONS.map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                    </label>

                    <div className="mt-3 grid grid-cols-2 gap-2">
                        {(['no_locker', 'need_locker'] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setIntent(value)}
                                className="min-h-11 rounded-[8px] border text-[13px] font-semibold"
                                style={{
                                    borderColor: intent === value ? 'var(--steel)' : 'var(--line)',
                                    color: intent === value ? 'var(--steel)' : 'var(--ink-muted)',
                                }}
                            >
                                {value === 'need_locker' ? 'Butuh loker' : 'Tanpa loker'}
                            </button>
                        ))}
                    </div>
                </>
            )}

            <ErrorText error={error} />
            <SubmitButton busy={busy} onClick={() => void submit()}>Check-in manual</SubmitButton>
        </Panel>
    );
}

export function SellPackagePanel({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
    const [packages, setPackages] = useState<Package[]>([]);
    const [action, setAction] = useState<'new' | 'renew'>('new');
    const [phone, setPhone] = useState('');
    const [fullName, setFullName] = useState('');
    const [photoUrl, setPhotoUrl] = useState('');
    const [packageId, setPackageId] = useState('');
    const [consent, setConsent] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        sessionGet<{ packages: Package[] }>('/api/v1/receptionist/packages')
            .then((data) => setPackages(data.packages))
            .catch(() => setPackages([]));
    }, []);

    async function submit() {
        setBusy(true);
        setError('');

        try {
            await sessionPost('/api/v1/receptionist/memberships', {
                action,
                phone_number: phone,
                package_id: Number(packageId),
                ...(action === 'new' ? { full_name: fullName, photo_url: photoUrl, consent } : {}),
            });
            onDone();
        } catch (caught) {
            setError((caught as ApiError).message ?? 'Gagal menyimpan paket.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Panel title="Jual / perpanjang paket" onClose={onClose}>
            <div className="grid grid-cols-2 gap-2">
                {(['new', 'renew'] as const).map((value) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setAction(value)}
                        className="min-h-11 rounded-[8px] border text-[13px] font-semibold"
                        style={{
                            borderColor: action === value ? 'var(--steel)' : 'var(--line)',
                            color: action === value ? 'var(--steel)' : 'var(--ink-muted)',
                        }}
                    >
                        {value === 'new' ? 'Member baru' : 'Perpanjang'}
                    </button>
                ))}
            </div>

            <Field label="Nomor HP">
                <input value={phone} onChange={(event) => setPhone(event.target.value)} className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]" />
            </Field>

            {action === 'new' && (
                <>
                    <Field label="Nama lengkap">
                        <input value={fullName} onChange={(event) => setFullName(event.target.value)} className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]" />
                    </Field>
                    <Field label="URL foto">
                        <input value={photoUrl} onChange={(event) => setPhotoUrl(event.target.value)} className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]" />
                    </Field>
                </>
            )}

            <Field label="Paket">
                <select value={packageId} onChange={(event) => setPackageId(event.target.value)} className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]">
                    <option value="">Pilih paket</option>
                    {packages.map((row) => (
                        <option key={row.package_id} value={row.package_id}>
                            {row.name} · Rp {row.price.toLocaleString('id-ID')}
                        </option>
                    ))}
                </select>
            </Field>

            {action === 'new' && (
                <label className="mt-3 flex items-start gap-2 text-[13px]">
                    <input type="checkbox" checked={consent} onChange={(event) => setConsent(event.target.checked)} className="mt-1 h-4 w-4" />
                    <span>Member setuju data pribadi diproses untuk absensi.</span>
                </label>
            )}

            <ErrorText error={error} />
            <SubmitButton busy={busy} onClick={() => void submit()}>Simpan paket</SubmitButton>
        </Panel>
    );
}

export function CloseGymPanel({ occupied, onClose, onDone }: { occupied: number; onClose: () => void; onDone: () => void }) {
    const [confirm, setConfirm] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function submit() {
        setBusy(true);
        setError('');

        try {
            await sessionPost('/api/v1/receptionist/lockers/batch-reset', { confirm_count: Number(confirm) });
            onDone();
        } catch (caught) {
            setError((caught as ApiError).message ?? 'Reset gagal.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Panel title="Tutup gym" onClose={onClose}>
            <p className="text-[14px] leading-6 text-[var(--ink-muted)]">
                {occupied} loker masih terisi. Ketik jumlah itu untuk menutup semua sesi.
            </p>
            <Field label={`Jumlah loker terisi (${occupied})`}>
                <input inputMode="numeric" value={confirm} onChange={(event) => setConfirm(event.target.value.replace(/\D/g, ''))} className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]" />
            </Field>
            <ErrorText error={error} />
            <SubmitButton busy={busy} onClick={() => void submit()}>Reset loker massal</SubmitButton>
        </Panel>
    );
}

function Panel({ title, onClose, children }: { title: string; onClose: () => void; children: React.ReactNode }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
            <div className="max-h-[92vh] w-full max-w-[440px] overflow-y-auto rounded-t-[12px] bg-[var(--surface)] p-5 sm:rounded-[12px]">
                <div className="flex items-center justify-between">
                    <h2 className="font-[var(--font-display)] text-[24px] font-bold">{title}</h2>
                    <button type="button" onClick={onClose} className="min-h-11 min-w-11 rounded-[8px] border border-[var(--line)] text-[13px] font-semibold">
                        Tutup
                    </button>
                </div>
                <div className="mt-4">{children}</div>
            </div>
        </div>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="mt-3 block">
            <span className="mb-1 block text-[13px] font-semibold">{label}</span>
            {children}
        </label>
    );
}

function ErrorText({ error }: { error: string }) {
    if (error === '') {
        return null;
    }

    return (
        <p role="alert" className="mt-3 rounded-[8px] border border-[var(--stop)] px-3 py-2 text-[13px] text-[var(--stop)]">
            {error}
        </p>
    );
}

function SubmitButton({ busy, onClick, children }: { busy: boolean; onClick: () => void; children: string }) {
    return (
        <button
            type="button"
            disabled={busy}
            onClick={onClick}
            className="mt-4 min-h-12 w-full rounded-[8px] bg-[var(--steel)] text-[16px] font-semibold text-white disabled:opacity-60"
        >
            {busy ? 'Memproses...' : children}
        </button>
    );
}

interface Attempt {
    audit_id: number;
    full_name: string | null;
    reason_code: string;
    occurred_at: string;
}

export function RejectedPanel({ onClose }: { onClose: () => void }) {
    const [attempts, setAttempts] = useState<Attempt[]>([]);
    const [error, setError] = useState('');

    useEffect(() => {
        sessionGet<{ attempts: Attempt[] }>('/api/v1/receptionist/rejected-attempts')
            .then((data) => setAttempts(data.attempts))
            .catch((caught: ApiError) => setError(caught.message));
    }, []);

    return (
        <Panel title="Scan ditolak" onClose={onClose}>
            {error !== '' && <ErrorText error={error} />}
            {attempts.length === 0 && error === '' && (
                <p className="text-[13px] text-[var(--ink-muted)]">Tidak ada scan ditolak hari ini.</p>
            )}
            <ul className="space-y-2">
                {attempts.map((row) => (
                    <li key={row.audit_id} className="rounded-[8px] border border-[var(--line)] px-3 py-2 text-[13px]">
                        <p className="font-semibold">{row.full_name ?? 'Member tidak dikenal'}</p>
                        <p className="text-[12px] text-[var(--stop)]">{row.reason_code}</p>
                        <p className="text-[11px] text-[var(--ink-muted)]">
                            {new Date(row.occurred_at).toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' })} WIB
                        </p>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}