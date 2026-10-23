import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function StaffLogin() {
    const [phone, setPhone] = useState('');
    const [password, setPassword] = useState('');
    const [totp, setTotp] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    async function submit(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        setError('');

        try {
            const response = await fetch('/api/v1/receptionist/auth/login', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    phone_number: phone,
                    password,
                    totp_code: totp.replace(/\s+/g, '') === '' ? undefined : totp.replace(/\s+/g, ''),
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                setError(payload.message ?? 'Login gagal.');
                setBusy(false);

                return;
            }

            window.location.assign('/kasir');
        } catch {
            setError('Tidak bisa menghubungi server.');
            setBusy(false);
        }
    }

    return (
        <>
            <Head title="Masuk kasir" />
            <main className="flex min-h-screen items-center justify-center bg-[var(--concrete)] p-6">
                <form onSubmit={submit} className="w-full max-w-[400px] rounded-[12px] border border-[var(--line)] bg-[var(--surface)] p-6">
                    <p className="font-[var(--font-display)] text-[13px] tracking-[0.18em] text-[var(--ink-muted)] uppercase">
                        GymPass QR
                    </p>
                    <h1 className="mt-1 font-[var(--font-display)] text-[32px] leading-9 font-bold">Masuk kasir</h1>

                    {error !== '' && (
                        <p role="alert" className="mt-4 rounded-[8px] border border-[var(--stop)] px-3 py-2 text-[13px] text-[var(--stop)]">
                            {error}
                        </p>
                    )}

                    <label className="mt-5 block">
                        <span className="mb-1 block text-[13px] font-semibold">Nomor HP</span>
                        <input
                            required
                            inputMode="numeric"
                            value={phone}
                            onChange={(event) => setPhone(event.target.value)}
                            className="h-12 w-full rounded-[8px] border border-[var(--line)] bg-[var(--surface)] px-3 text-[16px]"
                        />
                    </label>

                    <label className="mt-3 block">
                        <span className="mb-1 block text-[13px] font-semibold">Password</span>
                        <input
                            required
                            type="password"
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            className="h-12 w-full rounded-[8px] border border-[var(--line)] bg-[var(--surface)] px-3 text-[16px]"
                        />
                    </label>

                    <label className="mt-3 block">
                        <span className="mb-1 block text-[13px] font-semibold">Kode TOTP (admin)</span>
                        <input
                            type="text"
                            name="totp_code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={totp}
                            onChange={(event) => setTotp(event.target.value)}
                            onPaste={(event) => {
                                event.preventDefault();
                                setTotp(event.clipboardData.getData('text').slice(0, 6));
                            }}
                            placeholder="6 digit untuk admin"
                            className="h-12 w-full rounded-[8px] border border-[var(--line)] bg-[var(--surface)] px-3 text-[16px] tracking-[0.3em]"
                        />
                    </label>

                    <button
                        type="submit"
                        disabled={busy}
                        className="mt-5 min-h-12 w-full rounded-[8px] bg-[var(--steel)] text-[16px] font-semibold text-white disabled:opacity-60"
                    >
                        {busy ? 'Memproses...' : 'Masuk'}
                    </button>
                </form>
            </main>
        </>
    );
}
