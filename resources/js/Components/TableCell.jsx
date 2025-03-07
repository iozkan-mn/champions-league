import React from 'react';

export default function TableCell({ children, className = '' }) {
    return (
        <td className={`px-6 py-4 whitespace-nowrap text-sm text-gray-900 ${className}`}>
            {children}
        </td>
    );
} 