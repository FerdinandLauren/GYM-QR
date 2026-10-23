import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import MemberHome from '../Member/Home';
import { setTokens } from '../../lib/auth';

type Step = 'email' | 'code' | 'done';

interface ApiEnvelope {
    status: 'success' | 'error';
    message?: string;
    code?: string;
    data?: { expires_in?: number; access_token?: string; refresh_token?: string };
}

async function postJson(path: string, body: Record<string, unknown>): Promise<{ ok: boolean; payload: ApiEnvelope }> {
    const response = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });

    return { ok: response.ok, payload: (await response.json()) as ApiEnvelope };
}

export default function Login() {
    const [step, setStep] = useState<Step>('email');
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [token, setToken] = useState('');

    async function requestOtp(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        setError('');

        try {
            const { ok, payload } = await postJson('/api/v1/auth/request-otp', { email });
            setNotice(payload.message ?? 'Kode OTP dikirim ke email Anda.');
            setStep(ok ? 'code' : 'email');
        } catch {
            setError('Tidak bisa menghubungi server. Coba lagi.');
        } finally {
            setBusy(false);
        }
    }

    async function verifyOtp(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        setError('');

        try {
            const { ok, payload } = await postJson('/api/v1/auth/verify-otp', { email, code });

            if (ok) {
                setTokens(payload.data?.access_token ?? '', payload.data?.refresh_token ?? '');
                setToken(payload.data?.access_token ?? '');
                setStep('done');
            } else {
                setError(payload.message ?? 'Kode tidak cocok. Periksa lagi.');
            }
        } catch {
            setError('Tidak bisa menghubungi server. Coba lagi.');
        } finally {
            setBusy(false);
        }
    }

    if (token !== '') {
        return <MemberHome token={token} />;
    }

    return (
        <>
            <Head title="Masuk" />

            <main className="min-h-screen grid lg:grid-cols-[1.1fr_1fr]">
                <BrandPanel />

                <section className="flex items-center justify-center p-6 sm:p-10">
                    <div className="w-full max-w-[420px]">
                        <p className="font-[var(--font-display)] text-[13px] tracking-[0.18em] text-[var(--ink-muted)] uppercase">
                            GymPass QR
                        </p>
                        <h1 className="mt-2 font-[var(--font-display)] text-[var(--display-md-size)] leading-[var(--display-md-line)] font-bold">
                            {step === 'done' ? 'Kamu sudah masuk' : 'Masuk dengan email'}
                        </h1>
                        <p className="mt-3 text-[var(--body-sm-size)] leading-[var(--body-sm-line)] text-[var(--ink-muted)]">
                            {step === 'email' && 'Kode 6 digit dikirim ke email yang terdaftar di kasir.'}
                            {step === 'code' && `Masukkan kode yang dikirim ke ${email}.`}
                            {step === 'done' && 'Sesi member aktif. Lanjut ke beranda di milestone UI berikutnya.'}
                        </p>

                        {error !== '' && (
                            <p
                                role="alert"
                                aria-live="polite"
                                className="mt-5 rounded-[8px] border border-[var(--stop)] px-4 py-3 text-[14px] text-[var(--stop)]"
                            >
                                {error}
                            </p>
                        )}

                        {notice !== '' && error === '' && (
                            <p
                                aria-live="polite"
                                className="mt-5 rounded-[8px] border border-[var(--go)] px-4 py-3 text-[14px] text-[var(--go)]"
                            >
                                {notice}
                            </p>
                        )}

                        {step === 'email' && (
                            <form onSubmit={requestOtp} className="mt-6 space-y-4">
                                <label className="block">
                                    <span className="mb-2 block text-[14px] font-semibold">Email</span>
                                    <input
                                        type="email"
                                        required
                                        autoComplete="email"
                                        value={email}
                                        onChange={(event) => setEmail(event.target.value)}
                                        placeholder="nama@email.com"
                                        className="h-12 w-full rounded-[8px] border border-[var(--line)] bg-[var(--surface)] px-4 text-[16px] text-[var(--ink)]"
                                    />
                                </label>
                                <PrimaryButton busy={busy}>Kirim kode OTP</PrimaryButton>
                            </form>
                        )}

                        {step === 'code' && (
                            <form onSubmit={verifyOtp} className="mt-6 space-y-4">
                                <label className="block">
                                    <span className="mb-2 block text-[14px] font-semibold">Kode OTP</span>
                                    <input
                                        inputMode="numeric"
                                        pattern="[0-9]{6}"
                                        maxLength={6}
                                        required
                                        autoComplete="one-time-code"
                                        value={code}
                                        onChange={(event) => setCode(event.target.value.replace(/\D/g, ''))}
                                        placeholder="000000"
                                        className="h-12 w-full rounded-[8px] border border-[var(--line)] bg-[var(--surface)] px-4 text-center font-[var(--font-display)] text-[24px] tracking-[0.4em] text-[var(--ink)]"
                                    />
                                </label>
                                <PrimaryButton busy={busy}>Masuk</PrimaryButton>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setStep('email');
                                        setCode('');
                                        setNotice('');
                                        setError('');
                                    }}
                                    className="min-h-11 w-full text-[14px] font-semibold text-[var(--steel)]"
                                >
                                    Ganti email
                                </button>
                            </form>
                        )}

                        <p className="mt-8 text-[12px] leading-5 text-[var(--ink-muted)]">
                            Belum punya akun? Daftar di meja kasir. Bawa kartu identitas untuk verifikasi.
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}

function PrimaryButton({ busy, children }: { busy: boolean; children: string }) {
    return (
        <button
            type="submit"
            disabled={busy}
            className="min-h-12 w-full rounded-[8px] bg-[var(--steel)] text-[16px] font-semibold text-white transition-opacity disabled:opacity-60"
            style={{ transitionDuration: 'var(--motion-fast)' }}
        >
            {busy ? 'Memproses...' : children}
        </button>
    );
}

function BrandPanel() {
    return (
        <aside className="hidden bg-[var(--steel)] p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <p className="font-[var(--font-display)] text-[15px] tracking-[0.22em] uppercase opacity-80">
                GymPass QR
            </p>

            <div>
                <h2 className="font-[var(--font-display)] text-[64px] leading-[0.95] font-bold uppercase">
                    Satu pindai.
                    <br />
                    Langsung masuk.
                </h2>
                <p className="mt-6 max-w-[34ch] text-[16px] leading-7 text-white/80">
                    Absensi gym, status paket, dan nomor loker dalam satu layar. Tanpa buku tamu.
                </p>
            </div>

            <div aria-hidden="true" className="grid grid-cols-6 gap-2">
                {Array.from({ length: 18 }).map((_, index) => (
                    <div
                        key={index}
                        className="flex h-16 items-center justify-center rounded-[4px] border border-white/25 font-[var(--font-display)] text-[15px] font-bold text-white/70"
                    >
                        {String(index + 1).padStart(2, '0')}
                    </div>
                ))}
            </div>
        </aside>
    );
}
