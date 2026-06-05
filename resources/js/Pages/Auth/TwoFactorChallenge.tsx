import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        recovery_code: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/two-factor-challenge');
    };

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title="Two-Factor Authentication" />
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">Two-Factor Authentication</h1>
                    <p className="text-gray-500 mt-2 text-sm">
                        {useRecovery
                            ? 'Enter one of your recovery codes.'
                            : 'Enter the 6-digit code from your authenticator app.'}
                    </p>
                </div>
                <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-8 space-y-4">
                    {useRecovery ? (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Recovery Code</label>
                            <input type="text" value={data.recovery_code} onChange={e => setData('recovery_code', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                autoFocus autoComplete="one-time-code" />
                            {errors.recovery_code && <p className="text-red-600 text-xs mt-1">{errors.recovery_code}</p>}
                        </div>
                    ) : (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Authentication Code</label>
                            <input type="text" inputMode="numeric" pattern="[0-9]*" maxLength={6}
                                value={data.code} onChange={e => setData('code', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-center text-xl tracking-widest"
                                autoFocus autoComplete="one-time-code" />
                            {errors.code && <p className="text-red-600 text-xs mt-1">{errors.code}</p>}
                        </div>
                    )}
                    <button type="submit" disabled={processing}
                        className="w-full py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Verifying...' : 'Confirm'}
                    </button>
                </form>
                <p className="text-center text-sm text-gray-500 mt-4">
                    <button onClick={() => setUseRecovery(!useRecovery)} className="text-blue-600 hover:underline">
                        {useRecovery ? 'Use authentication code instead' : 'Use a recovery code instead'}
                    </button>
                </p>
            </div>
        </div>
    );
}
