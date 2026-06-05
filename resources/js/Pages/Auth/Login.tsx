import { useForm, Head } from '@inertiajs/react';
import { TrendingUp } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Sign In" />
            <div className="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 flex items-center justify-center p-4">
                <div className="w-full max-w-md">
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center gap-3 mb-4">
                            <div className="h-12 w-12 bg-white rounded-xl flex items-center justify-center">
                                <TrendingUp className="h-7 w-7 text-blue-600" />
                            </div>
                            <span className="text-3xl font-bold text-white">QuakeLogic</span>
                        </div>
                        <p className="text-blue-200 text-lg">Enterprise Bid Intelligence Platform</p>
                    </div>

                    <div className="bg-white rounded-2xl shadow-2xl p-8">
                        <h1 className="text-2xl font-bold text-gray-900 mb-6">Sign in to your account</h1>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                                    Email address
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="you@quakelogic.net"
                                    required
                                    autoFocus
                                />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                                    Password
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={e => setData('password', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="••••••••"
                                    required
                                />
                                {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={e => setData('remember', e.target.checked)}
                                        className="rounded border-gray-300"
                                    />
                                    Remember me
                                </label>
                                <a href="/forgot-password" className="text-sm text-blue-600 hover:underline">
                                    Forgot password?
                                </a>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors"
                            >
                                {processing ? 'Signing in...' : 'Sign in'}
                            </button>
                        </form>

                        <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                            <p className="text-xs text-gray-500 font-medium mb-2">Demo Credentials:</p>
                            <div className="space-y-1 text-xs text-gray-600">
                                <div><span className="font-medium">CEO:</span> ceo@quakelogic.net / password123!</div>
                                <div><span className="font-medium">BD Manager:</span> bdm@quakelogic.net / password123!</div>
                                <div><span className="font-medium">Proposal Mgr:</span> pm@quakelogic.net / password123!</div>
                                <div><span className="font-medium">Sales:</span> sales@quakelogic.net / password123!</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
