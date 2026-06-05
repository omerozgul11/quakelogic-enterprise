import { Head, Link } from '@inertiajs/react';

interface Props {
    status: number;
}

const ERROR_MESSAGES: Record<number, { title: string; description: string }> = {
    401: { title: 'Unauthorized', description: 'You are not authorized to view this page.' },
    403: { title: 'Forbidden', description: 'You do not have permission to access this resource.' },
    404: { title: 'Page Not Found', description: 'Sorry, the page you are looking for could not be found.' },
    419: { title: 'Session Expired', description: 'Your session has expired. Please refresh the page and try again.' },
    429: { title: 'Too Many Requests', description: 'You have made too many requests. Please wait a moment and try again.' },
    500: { title: 'Server Error', description: 'Something went wrong on our end. Please try again later.' },
    503: { title: 'Service Unavailable', description: 'We are down for maintenance. Please check back soon.' },
};

export default function Error({ status }: Props) {
    const error = ERROR_MESSAGES[status] ?? { title: 'Error', description: 'An unexpected error occurred.' };

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title={`${status} — ${error.title}`} />
            <div className="text-center max-w-md">
                <p className="text-8xl font-bold text-gray-200">{status}</p>
                <h1 className="text-2xl font-bold text-gray-900 mt-4">{error.title}</h1>
                <p className="text-gray-500 mt-2">{error.description}</p>
                <div className="flex gap-3 justify-center mt-6">
                    <button onClick={() => window.history.back()}
                        className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Go Back
                    </button>
                    <Link href="/" className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Go to Dashboard
                    </Link>
                </div>
            </div>
        </div>
    );
}
