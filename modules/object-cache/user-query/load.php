<?php
/**
 * Module Name: User Query Caching
 * Description: Cache the results of query in WP_User_Query to save SQL queries
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Require main class.
 */
require_once __DIR__ . '/class-wp-user-query-cache.php';

new WP_User_Query_Cache();
