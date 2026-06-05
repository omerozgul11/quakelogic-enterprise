import { Head, Link, useForm } from '@inertiajs/react';

interface Props {
    status?: string;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title="Forgot Password" />
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">Reset your password</h1>
                    <p className="text-gray-500 mt-2 text-sm">Enter your email and we'll send you a reset link.</p>
                </div>

                {status && (
                    <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                        {status}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-8 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required autoFocus />
                        {errors.email && <p className="text-red-600 text-xs mt-1">{errors.email}</p>}
                    </div>
                    <button type="submit" disabled={processing}
                        className="w-full py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Sending...' : 'Send Reset Link'}
                    </button>
                </form>

                <p className="text-center text-sm text-gray-500 mt-4">
                    <Link href="/login" className="text-blue-600 hover:underline">← Back to login</Link>
                </p>
            </div>
        </div>
    );
}
