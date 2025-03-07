import React from 'react';

export default function TableRow({ 
    children, 
    className = '',
    ...props 
}) {
    return (
        <tr
            className={`hover:bg-gray-50 ${className}`}
            {...props}
        >
            {children}
        </tr>
    );
}

export function TableCell({ 
    children, 
    className = '',
    ...props 
}) {
    return (
        <td
            className={`px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${className}`}
            {...props}
        >
            {children}
        </td>
    );
} 