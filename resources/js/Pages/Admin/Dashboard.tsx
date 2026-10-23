import { Head } from '@inertiajs/react';
import { FormEvent, useCallback, useEffect, useState } from 'react';
import { ApiError, sessionGet, sessionPatch, sessionPost } from '../../lib/api';
import { ThemeToggle } from '../../lib/theme';

type Tab = 'packages' | 'lockers' | 'settings' | 'staff' | 'reports' | 'audit';

const TABS: Array<{ key: Tab; label: string }> = [
    { key: 'packages', label: 'Paket' },
    { key: 'lockers', label: 'Loker' },
    { key: 'settings', label: 'Pengaturan' },
    { key: 'staff', label: 'Staf' },
    { key: 'reports', label: 'Laporan' },
    { key: 'audit', label: 'Audit' },
];

export default function AdminDashboard() {
    const [tab, setTab] = useState<Tab>('packages');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    const notify = useCallback((text: string) => {
        setError('');
        setMessage(text);
    }, []);

    const fail = useCallback((caught: unknown) => {
        setMessage('');
        setError((caught as ApiError).message ?? 'Aksi gagal.');
    }, []);

    return (
        <>
            <Head title="Admin" />
            <main className="min-h-screen bg-[var(--concrete)]">
                <header className="flex flex-wrap items-center gap-3 border-b border-[var(--line)] bg-[var(--surface)] px-5 py-3">
                    <p className="font-[var(--font-display)] text-[15px] font-bold tracking-[0.12em] uppercase">GymPass QR</p>
                    <span className="text-[13px] text-[var(--ink-muted)]">Admin</span>
                    <ThemeToggle className="ml-auto" />
                    <a href="/kasir" className="min-h-11 rounded-[8px] border border-[var(--line)] px-4 pt-3 text-[13px] font-semibold">
                        Dasbor kasir
                    </a>
                </header>

                <nav className="flex gap-1 overflow-x-auto border-b border-[var(--line)] bg-[var(--surface)] px-3">
                    {TABS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            aria-current={tab === item.key ? 'page' : undefined}
                            className="min-h-12 whitespace-nowrap border-b-2 px-4 text-[13px] font-semibold"
                            style={{
                                borderColor: tab === item.key ? 'var(--steel)' : 'transparent',
                                color: tab === item.key ? 'var(--steel)' : 'var(--ink-muted)',
                            }}
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>

                {(error !== '' || message !== '') && (
                    <p aria-live="polite" className="px-5 py-3 text-[13px]" style={{ color: error !== '' ? 'var(--stop)' : 'var(--go)' }}>
                        {error !== '' ? error : message}
                    </p>
                )}

                <div className="p-5">
                    {tab === 'packages' && <PackagesSection notify={notify} fail={fail} />}
                    {tab === 'lockers' && <LockersSection notify={notify} fail={fail} />}
                    {tab === 'settings' && <SettingsSection notify={notify} fail={fail} />}
                    {tab === 'staff' && <StaffSection notify={notify} fail={fail} />}
                    {tab === 'reports' && <ReportsSection fail={fail} />}
                    {tab === 'audit' && <AuditSection fail={fail} />}
                </div>
            </main>
        </>
    );
}

type Notice = (text: string) => void;
type Failure = (caught: unknown) => void;

interface PackageRow {
    package_id: number;
    code: string;
    name: string;
    type: string;
    duration_days: number;
    visit_quota: number | null;
    price: number;
    is_active: boolean;
}

function PackagesSection({ notify, fail }: { notify: Notice; fail: Failure }) {
    const [rows, setRows] = useState<PackageRow[]>([]);
    const [form, setForm] = useState({ code: '', name: '', type: 'monthly_unlimited', duration_days: '30', price: '0' });

    const load = useCallback(() => {
        sessionGet<{ packages: PackageRow[] }>('/api/v1/admin/packages')
            .then((data) => setRows(data.packages))
            .catch(fail);
    }, [fail]);

    useEffect(load, [load]);

    async function create(event: FormEvent) {
        event.preventDefault();

        try {
            await sessionPost('/api/v1/admin/packages', {
                ...form,
                duration_days: Number(form.duration_days),
                price: Number(form.price),
            });
            notify('Paket dibuat.');
            setForm({ code: '', name: '', type: 'monthly_unlimited', duration_days: '30', price: '0' });
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    async function toggle(row: PackageRow) {
        try {
            await sessionPatch(`/api/v1/admin/packages/${row.package_id}`, { is_active: !row.is_active });
            notify('Paket diperbarui.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    return (
        <section className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Table headers={['Kode', 'Nama', 'Tipe', 'Harga', 'Status', '']}>
                {rows.map((row) => (
                    <tr key={row.package_id} className="border-t border-[var(--line)]">
                        <Cell>{row.code}</Cell>
                        <Cell>{row.name}</Cell>
                        <Cell>{row.type === 'single_visit' ? 'Day Pass' : 'Bulanan'}</Cell>
                        <Cell>Rp {row.price.toLocaleString('id-ID')}</Cell>
                        <Cell>{row.is_active ? 'Aktif' : 'Nonaktif'}</Cell>
                        <Cell>
                            <SmallButton onClick={() => void toggle(row)}>{row.is_active ? 'Nonaktifkan' : 'Aktifkan'}</SmallButton>
                        </Cell>
                    </tr>
                ))}
            </Table>

            <Form title="Paket baru" onSubmit={create}>
                <Text label="Kode" value={form.code} onChange={(value) => setForm({ ...form, code: value })} />
                <Text label="Nama" value={form.name} onChange={(value) => setForm({ ...form, name: value })} />
                <Select label="Tipe" value={form.type} onChange={(value) => setForm({ ...form, type: value })} options={[['monthly_unlimited', 'Bulanan'], ['single_visit', 'Day Pass']]} />
                <Text label="Durasi (hari)" value={form.duration_days} onChange={(value) => setForm({ ...form, duration_days: value })} numeric />
                <Text label="Harga" value={form.price} onChange={(value) => setForm({ ...form, price: value })} numeric />
                <Submit>Simpan paket</Submit>
            </Form>
        </section>
    );
}

interface LockerRow {
    locker_id: number;
    locker_number: string;
    status: string;
    note: string | null;
}

function LockersSection({ notify, fail }: { notify: Notice; fail: Failure }) {
    const [rows, setRows] = useState<LockerRow[]>([]);
    const [number, setNumber] = useState('');

    const load = useCallback(() => {
        sessionGet<{ lockers: LockerRow[] }>('/api/v1/admin/lockers')
            .then((data) => setRows(data.lockers))
            .catch(fail);
    }, [fail]);

    useEffect(load, [load]);

    async function add(event: FormEvent) {
        event.preventDefault();

        try {
            await sessionPost('/api/v1/admin/lockers', { locker_number: number });
            setNumber('');
            notify('Loker ditambahkan.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    async function setStatus(row: LockerRow, status: string) {
        const reason = status === 'out_of_service' ? window.prompt('Alasan menonaktifkan loker:') ?? '' : '';

        if (status === 'out_of_service' && reason === '') {
            return;
        }

        try {
            await sessionPatch(`/api/v1/admin/lockers/${row.locker_id}`, { status, reason });
            notify('Loker diperbarui.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    return (
        <section className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Table headers={['Nomor', 'Status', 'Catatan', '']}>
                {rows.map((row) => (
                    <tr key={row.locker_id} className="border-t border-[var(--line)]">
                        <Cell>{row.locker_number}</Cell>
                        <Cell>{row.status}</Cell>
                        <Cell>{row.note ?? '-'}</Cell>
                        <Cell>
                            {row.status === 'out_of_service' ? (
                                <SmallButton onClick={() => void setStatus(row, 'available')}>Aktifkan</SmallButton>
                            ) : (
                                <SmallButton onClick={() => void setStatus(row, 'out_of_service')}>Tandai rusak</SmallButton>
                            )}
                        </Cell>
                    </tr>
                ))}
            </Table>

            <Form title="Loker baru" onSubmit={add}>
                <Text label="Nomor loker" value={number} onChange={setNumber} />
                <Submit>Tambah loker</Submit>
            </Form>
        </section>
    );
}

interface GymSettings {
    gym_name: string;
    timezone: string;
    latitude: number;
    longitude: number;
    geofence_radius_m: number;
    geofence_accuracy_max_m: number;
    closing_time: string;
    qr_payload: string;
}

function SettingsSection({ notify, fail }: { notify: Notice; fail: Failure }) {
    const [settings, setSettings] = useState<GymSettings | null>(null);

    const load = useCallback(() => {
        sessionGet<GymSettings>('/api/v1/admin/gym-settings')
            .then(setSettings)
            .catch(fail);
    }, [fail]);

    useEffect(load, [load]);

    if (settings === null) {
        return <p className="text-[13px] text-[var(--ink-muted)]">Memuat pengaturan...</p>;
    }

    async function save(event: FormEvent) {
        event.preventDefault();

        try {
            const current = settings;

            if (current === null) {
                return;
            }

            await sessionPatch('/api/v1/admin/gym-settings', {
                gym_name: current.gym_name,
                latitude: current.latitude,
                longitude: current.longitude,
                geofence_radius_m: current.geofence_radius_m,
                geofence_accuracy_max_m: current.geofence_accuracy_max_m,
                closing_time: current.closing_time,
            });
            notify('Pengaturan disimpan.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    async function regenerate() {
        if (!window.confirm('QR lama langsung tidak berlaku. Cetak ulang QR fisik. Lanjut?')) {
            return;
        }

        try {
            await sessionPost('/api/v1/admin/gym-settings/regenerate-qr');
            notify('QR baru dibuat.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Form title="Pengaturan gym" onSubmit={save}>
                <Text label="Nama gym" value={settings.gym_name} onChange={(value) => setSettings({ ...settings, gym_name: value })} />
                <Text label="Latitude" value={String(settings.latitude)} onChange={(value) => setSettings({ ...settings, latitude: Number(value) })} numeric />
                <Text label="Longitude" value={String(settings.longitude)} onChange={(value) => setSettings({ ...settings, longitude: Number(value) })} numeric />
                <Text label="Radius geofence (m)" value={String(settings.geofence_radius_m)} onChange={(value) => setSettings({ ...settings, geofence_radius_m: Number(value) })} numeric />
                <Text label="Akurasi GPS maks (m)" value={String(settings.geofence_accuracy_max_m)} onChange={(value) => setSettings({ ...settings, geofence_accuracy_max_m: Number(value) })} numeric />
                <Text label="Jam tutup (HH:MM)" value={settings.closing_time.slice(0, 5)} onChange={(value) => setSettings({ ...settings, closing_time: value })} />
                <Submit>Simpan pengaturan</Submit>
            </Form>

            <div className="rounded-[12px] border border-[var(--line)] bg-[var(--surface)] p-5">
                <h3 className="font-[var(--font-display)] text-[22px] font-bold">QR gym</h3>
                <p className="mt-2 break-all rounded-[8px] bg-[var(--concrete)] px-3 py-2 text-[12px]">{settings.qr_payload}</p>
                <p className="mt-3 text-[13px] text-[var(--ink-muted)]">Regenerasi membatalkan QR lama. Cetak ulang setelahnya.</p>
                <button type="button" onClick={() => void regenerate()} className="mt-4 min-h-12 w-full rounded-[8px] bg-[var(--stop)] text-[15px] font-semibold text-white">
                    Regenerasi QR
                </button>
            </div>
        </div>
    );
}

interface StaffRow {
    user_id: number;
    full_name: string;
    phone_number: string;
    role: string;
    is_active: boolean;
}

function StaffSection({ notify, fail }: { notify: Notice; fail: Failure }) {
    const [rows, setRows] = useState<StaffRow[]>([]);
    const [form, setForm] = useState({ full_name: '', phone_number: '', role: 'receptionist', password: '' });

    const load = useCallback(() => {
        sessionGet<{ staff: StaffRow[] }>('/api/v1/admin/staff')
            .then((data) => setRows(data.staff))
            .catch(fail);
    }, [fail]);

    useEffect(load, [load]);

    async function create(event: FormEvent) {
        event.preventDefault();

        try {
            await sessionPost('/api/v1/admin/staff', form);
            setForm({ full_name: '', phone_number: '', role: 'receptionist', password: '' });
            notify('Staf dibuat.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    async function toggle(row: StaffRow) {
        try {
            await sessionPatch(`/api/v1/admin/staff/${row.user_id}`, { is_active: !row.is_active });
            notify('Staf diperbarui.');
            load();
        } catch (caught) {
            fail(caught);
        }
    }

    async function resetPassword(row: StaffRow) {
        const password = window.prompt(`Password baru untuk ${row.full_name} (min. 8 karakter):`);

        if (password === null || password.length < 8) {
            return;
        }

        try {
            await sessionPatch(`/api/v1/admin/staff/${row.user_id}`, { password });
            notify('Password staf direset.');
        } catch (caught) {
            fail(caught);
        }
    }

    return (
        <section className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Table headers={['Nama', 'Nomor HP', 'Role', 'Status', '']}>
                {rows.map((row) => (
                    <tr key={row.user_id} className="border-t border-[var(--line)]">
                        <Cell>{row.full_name}</Cell>
                        <Cell>{row.phone_number}</Cell>
                        <Cell>{row.role}</Cell>
                        <Cell>{row.is_active ? 'Aktif' : 'Nonaktif'}</Cell>
                        <Cell>
                            <div className="flex gap-2">
                                <SmallButton onClick={() => void toggle(row)}>{row.is_active ? 'Nonaktifkan' : 'Aktifkan'}</SmallButton>
                                <SmallButton onClick={() => void resetPassword(row)}>Reset password</SmallButton>
                            </div>
                        </Cell>
                    </tr>
                ))}
            </Table>

            <Form title="Staf baru" onSubmit={create}>
                <Text label="Nama" value={form.full_name} onChange={(value) => setForm({ ...form, full_name: value })} />
                <Text label="Nomor HP" value={form.phone_number} onChange={(value) => setForm({ ...form, phone_number: value })} />
                <Select label="Role" value={form.role} onChange={(value) => setForm({ ...form, role: value })} options={[['receptionist', 'Kasir'], ['admin', 'Admin']]} />
                <Text label="Password" value={form.password} onChange={(value) => setForm({ ...form, password: value })} />
                <Submit>Simpan staf</Submit>
            </Form>
        </section>
    );
}

interface ReportRow {
    period: string;
    sessions: number;
    unique_members: number;
    avg_duration_minutes: number | null;
}

function ReportsSection({ fail }: { fail: Failure }) {
    const today = new Date().toISOString().slice(0, 10);
    const [from, setFrom] = useState(today);
    const [to, setTo] = useState(today);
    const [group, setGroup] = useState('day');
    const [rows, setRows] = useState<ReportRow[]>([]);
    const [peaks, setPeaks] = useState<Array<{ hour: number; sessions: number }>>([]);

    const load = useCallback(() => {
        sessionGet<{ rows: ReportRow[] }>(`/api/v1/admin/reports/attendance?from=${from}&to=${to}&group=${group}`)
            .then((data) => setRows(data.rows))
            .catch(fail);
        sessionGet<{ hours: Array<{ hour: number; sessions: number }> }>(`/api/v1/admin/reports/peak-hours?from=${from}&to=${to}`)
            .then((data) => setPeaks(data.hours))
            .catch(fail);
    }, [fail, from, group, to]);

    const maxPeak = Math.max(1, ...peaks.map((row) => row.sessions));

    return (
        <section className="space-y-6">
            <div className="flex flex-wrap items-end gap-3">
                <Text label="Dari" value={from} onChange={setFrom} />
                <Text label="Sampai" value={to} onChange={setTo} />
                <Select label="Kelompok" value={group} onChange={setGroup} options={[['day', 'Harian'], ['week', 'Mingguan'], ['month', 'Bulanan']]} />
                <button type="button" onClick={load} className="min-h-12 rounded-[8px] bg-[var(--steel)] px-5 text-[14px] font-semibold text-white">
                    Muat
                </button>
                <a
                    href={`/api/v1/admin/reports/attendance?from=${from}&to=${to}&group=${group}&format=csv`}
                    className="min-h-12 rounded-[8px] border border-[var(--line)] px-5 pt-3 text-[14px] font-semibold"
                >
                    Unduh CSV
                </a>
            </div>

            <Table headers={['Periode', 'Sesi', 'Member unik', 'Rata-rata durasi (menit)']}>
                {rows.map((row) => (
                    <tr key={row.period} className="border-t border-[var(--line)]">
                        <Cell>{row.period}</Cell>
                        <Cell>{row.sessions}</Cell>
                        <Cell>{row.unique_members}</Cell>
                        <Cell>{row.avg_duration_minutes ?? '-'}</Cell>
                    </tr>
                ))}
            </Table>

            <div>
                <h3 className="font-[var(--font-display)] text-[22px] font-bold">Peak hours</h3>
                <div className="mt-3 flex h-40 items-end gap-1">
                    {peaks.map((row) => (
                        <div key={row.hour} className="flex flex-1 flex-col items-center gap-1" title={`${row.hour}:00 · ${row.sessions} sesi`}>
                            <div className="w-full rounded-t-[2px] bg-[var(--steel)]" style={{ height: `${(row.sessions / maxPeak) * 100}%`, minHeight: row.sessions > 0 ? 4 : 0 }} />
                            <span className="text-[9px] text-[var(--ink-muted)]">{row.hour}</span>
                        </div>
                    ))}
                </div>
                <table className="mt-3 w-full text-[12px]">
                    <tbody>
                        <tr className="border-t border-[var(--line)]">
                            <td className="py-2 font-semibold">Tabel pendamping</td>
                            {peaks.filter((row) => row.sessions > 0).map((row) => (
                                <td key={row.hour} className="py-2">{row.hour}:00 ({row.sessions})</td>
                            ))}
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    );
}

interface AuditRow {
    audit_id: number;
    actor_name: string | null;
    action: string;
    entity_type: string | null;
    created_at: string;
}

function AuditSection({ fail }: { fail: Failure }) {
    const [action, setAction] = useState('');
    const [rows, setRows] = useState<AuditRow[]>([]);

    const load = useCallback(() => {
        const query = action === '' ? '' : `?action=${encodeURIComponent(action)}`;
        sessionGet<{ logs: AuditRow[] }>(`/api/v1/admin/audit-logs${query}`)
            .then((data) => setRows(data.logs))
            .catch(fail);
    }, [action, fail]);

    useEffect(load, [load]);

    return (
        <section className="space-y-4">
            <div className="flex items-end gap-3">
                <Text label="Filter aksi" value={action} onChange={setAction} />
                <button type="button" onClick={load} className="min-h-12 rounded-[8px] bg-[var(--steel)] px-5 text-[14px] font-semibold text-white">
                    Muat
                </button>
            </div>

            <Table headers={['Waktu', 'Pelaku', 'Aksi', 'Entitas']}>
                {rows.map((row) => (
                    <tr key={row.audit_id} className="border-t border-[var(--line)]">
                        <Cell>{new Date(row.created_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })}</Cell>
                        <Cell>{row.actor_name ?? '-'}</Cell>
                        <Cell>{row.action}</Cell>
                        <Cell>{row.entity_type ?? '-'}</Cell>
                    </tr>
                ))}
            </Table>
        </section>
    );
}

function Table({ headers, children }: { headers: string[]; children: React.ReactNode }) {
    return (
        <div className="overflow-x-auto rounded-[12px] border border-[var(--line)] bg-[var(--surface)]">
            <table className="w-full text-left text-[13px]">
                <thead>
                    <tr className="bg-[var(--concrete)]">
                        {headers.map((header) => (
                            <th key={header} className="px-3 py-2 font-semibold">{header}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

function Cell({ children }: { children: React.ReactNode }) {
    return <td className="px-3 py-2 align-middle">{children}</td>;
}

function Form({ title, onSubmit, children }: { title: string; onSubmit: (event: FormEvent) => void; children: React.ReactNode }) {
    return (
        <form onSubmit={onSubmit} className="h-fit rounded-[12px] border border-[var(--line)] bg-[var(--surface)] p-5">
            <h3 className="font-[var(--font-display)] text-[22px] font-bold">{title}</h3>
            {children}
        </form>
    );
}

function Text({ label, value, onChange, numeric = false }: { label: string; value: string; onChange: (value: string) => void; numeric?: boolean }) {
    return (
        <label className="mt-3 block">
            <span className="mb-1 block text-[13px] font-semibold">{label}</span>
            <input
                value={value}
                inputMode={numeric ? 'decimal' : undefined}
                onChange={(event) => onChange(event.target.value)}
                className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]"
            />
        </label>
    );
}

function Select({ label, value, onChange, options }: { label: string; value: string; onChange: (value: string) => void; options: Array<[string, string]> }) {
    return (
        <label className="mt-3 block">
            <span className="mb-1 block text-[13px] font-semibold">{label}</span>
            <select value={value} onChange={(event) => onChange(event.target.value)} className="h-12 w-full rounded-[8px] border border-[var(--line)] px-3 text-[16px]">
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>{optionLabel}</option>
                ))}
            </select>
        </label>
    );
}

function SmallButton({ children, onClick }: { children: string; onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} className="min-h-11 rounded-[8px] border border-[var(--line)] px-3 text-[13px] font-semibold">
            {children}
        </button>
    );
}

function Submit({ children }: { children: string }) {
    return (
        <button type="submit" className="mt-4 min-h-12 w-full rounded-[8px] bg-[var(--steel)] text-[15px] font-semibold text-white">
            {children}
        </button>
    );
}
