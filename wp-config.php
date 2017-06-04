<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wp-practice');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         ',9UEiXppz5d+ZSQ/sR71O1(f24TMc49&J{a58PAn6%=m%Kip}{]DJk[ODvU!0Yqr');
define('SECURE_AUTH_KEY',  'a.,4RI_F !&>})]K)ALna0o%oB)d`3-ToVJ4b21t**se_$J(D!YTy{=~HK|q(qhN');
define('LOGGED_IN_KEY',    'x6f40MIig4yXEiINj-MYb;cM k<u`o >c De<Dc; I;_(>Te~!cWX_QV6?cs/UCN');
define('NONCE_KEY',        'rm@]5G5;oDsar4UC*)gD0(Y06({%ZdC;9iD1knU:h9bqfNXpXct3mToN[bM)B@!9');
define('AUTH_SALT',        '>W`Ng?ZriQ;*4`U(0ycK!jCy$R9G2Qnq,Qj2hp%8-6{RRH?=~j.)e{U*ha^ES|g^');
define('SECURE_AUTH_SALT', 'Jr@`>m?.<I)y)qt24SlGX]2$0#|yK8NFRyr~H}I l)9ThYDKVr~4f&N*Y*i1N4m[');
define('LOGGED_IN_SALT',   'X;9*/iTN|]jx+=}]r6{4(D80R~5 ;i9dMP%/`kiRgxnn|_3mP0&lyRzHArA6BhHT');
define('NONCE_SALT',       'AGyXBZ3Rl[KLlJzSDpyyQ)P?)we@ }SIlV?*cau=m:{v ,:(Ci2I,D#K,h-c `6a');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
