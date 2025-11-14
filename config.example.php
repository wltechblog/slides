<?php

/**
 * Slides Configuration Example
 * 
 * Copy this file to config.php and update the values as needed.
 * The config.php file is ignored by git and should NOT be committed.
 */

return [
    /**
     * Admin password for creating, editing, and deleting slideshows.
     * Set to null to disable authentication (not recommended for production).
     * 
     * For better security, consider using environment variables:
     * 'admin_password' => $_ENV['SLIDES_ADMIN_PASSWORD'] ?? null,
     */
    'admin_password' => 'your-secure-password-here',
];
