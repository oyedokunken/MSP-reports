/// <reference types="vite/client" />

/**
 * TypeScript definitions for environment variables
 * This provides IntelliSense and type safety for import.meta.env
 */

interface ImportMetaEnv {
    /**
     * Base URL for API requests
     * @example 'http://localhost:8081/api'
     */
    readonly VITE_API_BASE_URL?: string;

    /**
     * Application environment
     * @default 'development'
     */
    readonly MODE: 'development' | 'production' | 'test';

    /**
     * Whether the app is running in development mode
     */
    readonly DEV: boolean;

    /**
     * Whether the app is running in production mode
     */
    readonly PROD: boolean;

    /**
     * Whether server-side rendering is enabled
     */
    readonly SSR: boolean;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
