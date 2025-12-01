/**
 * Error Boundary Component
 *
 * Catches JavaScript errors anywhere in the component tree and
 * displays a fallback UI instead of crashing the app.
 *
 * @package SureFeedback
 */

import React from 'react';
import { XCircle } from 'lucide-react';

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError(error) {
        // Update state so the next render will show the fallback UI
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({
            error: error,
            errorInfo: errorInfo
        });
    }

    render() {
        if (this.state.hasError) {
            // Fallback UI
            return (
                <div className="flex justify-center items-center min-h-screen bg-gray-50 p-4">
                    <div className="bg-white shadow-sm text-center max-w-md w-full rounded-lg border border-gray-200 p-6 space-y-4">
                        <XCircle className="mx-auto text-red-600 h-8 w-8" />
                        <h2 className="text-xl font-semibold text-gray-900">
                            Something went wrong
                        </h2>
                        <p className="text-sm text-gray-600">
                            There was an error loading the SureFeedback interface.
                            Please refresh the page or contact support if the problem persists.
                        </p>
                        {this.state.error && (
                            <details className="mt-4 text-left">
                                <summary className="cursor-pointer text-sm font-medium text-gray-700">
                                    Show error details
                                </summary>
                                <pre className="mt-2 p-3 bg-gray-100 text-xs text-gray-800 rounded-md overflow-auto">
                                    {this.state.error.toString()}
                                    {this.state.errorInfo?.componentStack}
                                </pre>
                            </details>
                        )}
                        <button
                            onClick={() => window.location.reload()}
                            className="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Refresh Page
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;