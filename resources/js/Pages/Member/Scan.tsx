import { BrowserQRCodeReader, IScannerControls } from '@zxing/browser';
import { useCallback, useEffect, useRef, useState } from 'react';
import { apiPost, ApiError } from '../../lib/api';

type Phase = 'ready' | 'scanning' | 'submitting' | 'success' | 'error';
type Intent = 'no_locker' | 'need_locker';

interface ScanResult {
    log_id: number;
    checkin_time: string;
    locker_state: string;
    locker_number: string | null;
}

export default function Scan({ token, onDone }: { token: string; onDone: () => void }) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const controlsRef = useRef<IScannerControls | null>(null);
    const [phase, setPhase] = useState<Phase>('ready');
    const [intent, setIntent] = useState<Intent>('no_locker');
    const [accuracy, setAccuracy] = useState<number | null>(null);
    const [message, setMessage] = useState('');
    const [result, setResult] = useState<ScanResult | null>(null);

    const stopCamera = useCallback(() => {
        controlsRef.current?.stop();
        controlsRef.current = null;
    }, []);

    useEffect(() => stopCamera, [stopCamera]);

    const submit = useCallback(
        async (payload: string) => {
            setPhase('submitting');
            setMessage('Memeriksa lokasi dan sesi...');

            try {
                const position = await currentPosition();
                const response = await apiPost<ScanResult>(
                    '/api/v1/attendance/scan-qr',
                    {
                        qr_payload: payload,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        locker_intent: intent,
                    },
                    token,
                    { 'Idempotency-Key': crypto.randomUUID() },
                );

                setResult(response);
                setPhase('success');
            } catch (caught) {
                const error = caught as ApiError;
                setMessage(mapError(error));
                setPhase('error');
            }
        },
        [intent, token],
    );

    const startCamera = useCallback(async () => {
        setMessage('');
        setPhase('scanning');

        try {
            const position = await currentPosition();
            setAccuracy(Math.round(position.coords.accuracy));
        } catch {
            setMessage('Izin lokasi ditolak. Aktifkan lokasi lalu coba lagi, atau minta kasir check-in manual.');
            setPhase('error');

            return;
        }

        try {
            const reader = new BrowserQRCodeReader();
            controlsRef.current = await reader.decodeFromVideoDevice(undefined, videoRef.current!, (scan) => {
                if (scan) {
                    stopCamera();
                    void submit(scan.getText());
                }
            });
        } catch {
            setMessage('Kamera tidak bisa dibuka. Beri izin kamera, atau minta kasir check-in manual.');
            setPhase('error');
        }
    }, [stopCamera, submit]);

    if (phase === 'success' && result !== null) {
        return <Result result={result} onDone={onDone} />;
    }

    return (
        <section className="mt-5">
            <h2 className="font-[var(--font-display)] text-[var(--display-md-size)] leading-[var(--display-md-line)] font-bold">
                Pindai QR gym
            </h2>
            <p className="mt-1 text-[13px] text-[var(--ink-muted)]">Arahkan kamera ke QR di meja kasir.</p>

            <div className="mt-4 grid grid-cols-2 gap-2">
                <IntentButton active={intent === 'no_locker'} onClick={() => setIntent('no_locker')}>
                    Tanpa loker
                </IntentButton>
                <IntentButton active={intent === 'need_locker'} onClick={() => setIntent('need_locker')}>
                    Butuh loker
                </IntentButton>
            </div>

            <div className="relative mt-4 overflow-hidden rounded-[12px] border border-[var(--line)] bg-[var(--ink)]">
                <video ref={videoRef} className="aspect-[3/4] w-full object-cover" muted playsInline />
                {phase !== 'scanning' && (
                    <div className="absolute inset-0 flex items-center justify-center p-6 text-center">
                        <p className="max-w-[26ch] text-[14px] leading-6 text-white/80">
                            {phase === 'submitting' ? 'Memproses...' : 'Kamera belum aktif.'}
                        </p>
                    </div>
                )}
            </div>

            <p aria-live="polite" className="mt-3 min-h-10 text-[13px] leading-5 text-[var(--ink-muted)]">
                {accuracy !== null ? `Akurasi GPS ${accuracy} m. ` : ''}
                {message}
            </p>

            {phase === 'ready' || phase === 'error' ? (
                <button
                    type="button"
                    onClick={() => void startCamera()}
                    className="min-h-12 w-full rounded-[8px] bg-[var(--plate)] text-[16px] font-bold text-[var(--ink)]"
                >
                    Aktifkan kamera
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => {
                        stopCamera();
                        setPhase('ready');
                    }}
                    className="min-h-12 w-full rounded-[8px] border border-[var(--line)] text-[16px] font-semibold text-[var(--ink)]"
                >
                    Batalkan
                </button>
            )}
        </section>
    );
}

function Result({ result, onDone }: { result: ScanResult; onDone: () => void }) {
    const assigned = result.locker_state === 'assigned' && result.locker_number !== null;

    return (
        <section className="mt-8 text-center">
            <p className="text-[14px] font-semibold text-[var(--go)]">Check-in berhasil</p>
            <p className="mt-1 text-[13px] text-[var(--ink-muted)]">
                {new Date(result.checkin_time).toLocaleTimeString('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    hour: '2-digit',
                    minute: '2-digit',
                })}{' '}
                WIB
            </p>

            {assigned ? (
                <>
                    <p className="mt-8 font-[var(--font-display)] text-[13px] tracking-[0.18em] text-[var(--ink-muted)] uppercase">
                        Loker kamu
                    </p>
                    <div className="mx-auto mt-3 flex aspect-square w-48 items-center justify-center rounded-[4px] bg-[var(--plate)]">
                        <span className="font-[var(--font-display)] text-[var(--display-xl-size)] leading-[var(--display-xl-line)] font-extrabold text-[var(--ink)]">
                            {result.locker_number}
                        </span>
                    </div>
                    <p className="mx-auto mt-5 max-w-[26ch] text-[14px] leading-6 text-[var(--ink-muted)]">
                        Kembalikan kunci ke kasir saat pulang.
                    </p>
                </>
            ) : result.locker_state === 'pending' ? (
                <p className="mx-auto mt-8 max-w-[28ch] text-[15px] leading-7">
                    Sampaikan ke kasir bahwa kamu butuh loker. Nomor loker akan muncul di sini.
                </p>
            ) : (
                <p className="mx-auto mt-8 max-w-[28ch] text-[15px] leading-7">
                    Kamu check-in tanpa loker. Selamat berlatih.
                </p>
            )}

            <button
                type="button"
                onClick={onDone}
                className="mt-10 min-h-12 w-full rounded-[8px] bg-[var(--steel)] text-[16px] font-semibold text-white"
            >
                Selesai
            </button>
        </section>
    );
}

function IntentButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className="min-h-11 rounded-[8px] border text-[14px] font-semibold"
            style={{
                borderColor: active ? 'var(--steel)' : 'var(--line)',
                background: active ? 'var(--surface)' : 'transparent',
                color: active ? 'var(--steel)' : 'var(--ink-muted)',
            }}
        >
            {children}
        </button>
    );
}

function currentPosition(): Promise<GeolocationPosition> {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error('Geolocation tidak tersedia'));

            return;
        }

        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
        });
    });
}

function mapError(error: ApiError): string {
    switch (error.code) {
        case 'GEO_OUT_OF_RANGE':
            return 'Kamu terlalu jauh dari gym. Dekati meja kasir lalu coba lagi.';
        case 'GPS_ACCURACY_LOW':
            return 'Sinyal GPS lemah. Minta kasir check-in manual.';
        case 'SESSION_ACTIVE':
            return 'Kamu sudah check-in. Lihat status di Beranda.';
        case 'RESCAN_GUARD':
            return 'Baru saja check-in. Coba lagi beberapa menit lagi.';
        case 'MEMBERSHIP_EXPIRED':
            return 'Paket kedaluwarsa. Perpanjang di kasir.';
        case 'INVALID_QR':
            return 'QR ini bukan dari gym. Pindai QR di meja kasir.';
        default:
            return error.message || 'Terjadi kesalahan. Coba lagi.';
    }
}
