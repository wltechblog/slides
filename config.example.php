<?php

/**
 * Slides Configuration Example
 * 
 * Copy this file to config.php and update the values as needed.
 * The config.php file is ignored by git and should NOT be committed.
 */

return [
    /**
     * Base URL path for the application.
     * 
     * Set this if the application is installed in a subdirectory.
     * Examples:
     *   '/'              - Application at root (default)
     *   '/slides'        - Application at example.com/slides/
     *   '/tools/slides'  - Application at example.com/tools/slides/
     * 
     * Leave empty string to auto-detect (may not work in all setups).
     */
    'base_url' => '/',

    /**
     * Admin password for creating, editing, and deleting slideshows.
     * Set to null to disable authentication (not recommended for production).
     * 
     * For better security, consider using environment variables:
     * 'admin_password' => $_ENV['SLIDES_ADMIN_PASSWORD'] ?? null,
     */
    'admin_password' => 'your-secure-password-here',
];
