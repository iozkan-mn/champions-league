import React from 'react';
import { Link } from '@inertiajs/react';

export default function AppLayout({ title, children }) {
    return (
        <div className="min-h-screen bg-gray-100 flex flex-col">
            <nav className="bg-white border-b border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <Link href="/" className="text-xl font-bold text-gray-800">
                                    Premier League
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="flex-1 py-6 pb-16">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {title && (
                        <h1 className="text-3xl font-bold text-gray-900 mb-6">{title}</h1>
                    )}
                    {children}
                </div>
            </main>
        </div>
    );
} 