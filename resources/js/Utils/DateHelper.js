/**
 * Date Helper Utilities
 * Ensures consistent timezone handling across the application (Asia/Jakarta)
 */

const TIMEZONE = 'Asia/Jakarta';

/**
 * Format a date string to Indonesian locale with Asia/Jakarta timezone
 * @param {string|Date} dateString - The date to format
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
export const formatDateTime = (dateString, options = {}) => {
    if (!dateString) return '-';

    const defaultOptions = {
        timeZone: TIMEZONE,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    };

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '-';

        return date.toLocaleString('id-ID', { ...defaultOptions, ...options });
    } catch (error) {
        console.error('Error formatting date:', error);
        return '-';
    }
};

/**
 * Format a date string to show only the date (no time)
 * @param {string|Date} dateString - The date to format
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
export const formatDate = (dateString, options = {}) => {
    if (!dateString) return '-';

    const defaultOptions = {
        timeZone: TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    };

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '-';

        return date.toLocaleDateString('id-ID', { ...defaultOptions, ...options });
    } catch (error) {
        console.error('Error formatting date:', error);
        return '-';
    }
};

/**
 * Format a date string to short format (e.g., "04 Jan 2026")
 * @param {string|Date} dateString - The date to format
 * @returns {string} Formatted date string
 */
export const formatDateShort = (dateString) => {
    return formatDate(dateString, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

/**
 * Format a date string with full datetime (e.g., "04 Januari 2026 14:30:00")
 * @param {string|Date} dateString - The date to format
 * @returns {string} Formatted datetime string
 */
export const formatDateTimeFull = (dateString) => {
    if (!dateString) return '-';

    const options = {
        timeZone: TIMEZONE,
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    };

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '-';

        return date.toLocaleString('id-ID', options);
    } catch (error) {
        console.error('Error formatting date:', error);
        return '-';
    }
};

/**
 * Format a date string for input[type="date"] (YYYY-MM-DD)
 * @param {string|Date} dateString - The date to format
 * @returns {string} Formatted date string
 */
export const formatDateForInput = (dateString) => {
    if (!dateString) return '';

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';

        // Convert to Asia/Jakarta timezone
        const jakartaDate = new Date(date.toLocaleString('en-US', { timeZone: TIMEZONE }));

        const year = jakartaDate.getFullYear();
        const month = String(jakartaDate.getMonth() + 1).padStart(2, '0');
        const day = String(jakartaDate.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    } catch (error) {
        console.error('Error formatting date for input:', error);
        return '';
    }
};

/**
 * Format a date string for input[type="datetime-local"]
 * @param {string|Date} dateString - The date to format
 * @returns {string} Formatted datetime string
 */
export const formatDateTimeForInput = (dateString) => {
    if (!dateString) return '';

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';

        // Convert to Asia/Jakarta timezone
        const jakartaDate = new Date(date.toLocaleString('en-US', { timeZone: TIMEZONE }));

        const year = jakartaDate.getFullYear();
        const month = String(jakartaDate.getMonth() + 1).padStart(2, '0');
        const day = String(jakartaDate.getDate()).padStart(2, '0');
        const hours = String(jakartaDate.getHours()).padStart(2, '0');
        const minutes = String(jakartaDate.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    } catch (error) {
        console.error('Error formatting datetime for input:', error);
        return '';
    }
};

/**
 * Get relative time (e.g., "2 jam yang lalu", "kemarin")
 * @param {string|Date} dateString - The date to compare
 * @returns {string} Relative time string
 */
export const getRelativeTime = (dateString) => {
    if (!dateString) return '-';

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '-';

        const now = new Date();
        const diffMs = now - date;
        const diffSecs = Math.floor(diffMs / 1000);
        const diffMins = Math.floor(diffSecs / 60);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffSecs < 60) return 'Baru saja';
        if (diffMins < 60) return `${diffMins} menit yang lalu`;
        if (diffHours < 24) return `${diffHours} jam yang lalu`;
        if (diffDays === 1) return 'Kemarin';
        if (diffDays < 7) return `${diffDays} hari yang lalu`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} minggu yang lalu`;
        if (diffDays < 365) return `${Math.floor(diffDays / 30)} bulan yang lalu`;

        return `${Math.floor(diffDays / 365)} tahun yang lalu`;
    } catch (error) {
        console.error('Error getting relative time:', error);
        return '-';
    }
};

export default {
    formatDateTime,
    formatDate,
    formatDateShort,
    formatDateTimeFull,
    formatDateForInput,
    formatDateTimeForInput,
    getRelativeTime,
    TIMEZONE,
};
