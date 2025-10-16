/**
 * Error Boundary Component
 * 
 * Catches JavaScript errors anywhere in the component tree and
 * displays a fallback UI instead of crashing the app.
 * 
 * @package SureFeedback
 */

import React from 'react';

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
        // Log the error
        console.error('SureFeedback React Error:', error, errorInfo);
        
        this.setState({
            error: error,
            errorInfo: errorInfo
        });

        // You can also log the error to an error reporting service
        if (window.sureFeedbackAdmin?.debug) {
            console.group('SureFeedback Error Details');
            console.error('Error:', error);
            console.error('Error Info:', errorInfo);
            console.error('Component Stack:', errorInfo.componentStack);
            console.groupEnd();
        }
    }

    render() {
        if (this.state.hasError) {
            // Fallback UI
            return (
                <div className="surefeedback-error-boundary">
                    <div className="error-container p-6 bg-red-50 border border-red-200 rounded-lg">
                        <div className="flex items-center">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-red-800">
                                    Something went wrong
                                </h3>
                                <div className="mt-2 text-sm text-red-700">
                                    <p>
                                        There was an error loading the SureFeedback interface. 
                                        Please refresh the page or contact support if the problem persists.
                                    </p>
                                </div>
                                {window.sureFeedbackAdmin?.debug && this.state.error && (
                                    <details className="mt-4">
                                        <summary className="cursor-pointer text-sm font-medium text-red-800">
                                            Show error details
                                        </summary>
                                        <pre className="mt-2 p-2 bg-red-100 text-xs text-red-900 rounded overflow-auto">
                                            {this.state.error.toString()}
                                            {this.state.errorInfo.componentStack}
                                        </pre>
                                    </details>
                                )}
                                <div className="mt-4">
                                    <button 
                                        onClick={() => window.location.reload()}
                                        className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-medium"
                                    >
                                        Refresh Page
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;