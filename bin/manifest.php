<?php
/**
 * Prints update.json for a release: what installed sites read to find updates
 * (see includes/class-gdprcn-updater.php).
 *
 * Usage: php bin/manifest.php <version>
 *
 * @package CSI_Cookie_Consent
 */

$version = isset( $argv[1] ) ? $argv[1] : '';
if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
	fwrite( STDERR, "Usage: php bin/manifest.php <x.y.z>\n" );
	exit( 1 );
}

$root   = dirname( __DIR__ );
$header = file_get_contents( "$root/csi-cookie-consent.php" );
$readme = file_get_contents( "$root/readme.txt" );

/**
 * Read a "Key: value" line from a plugin header or readme.
 *
 * @param string $text Source.
 * @param string $key  Key.
 * @return string
 */
function field( $text, $key ) {
	return preg_match( '/^[ \t\/*#@]*' . preg_quote( $key, '/' ) . ':\s*(.+)$/mi', $text, $m ) ? trim( $m[1] ) : '';
}

/**
 * Turn a readme.txt section ("= 1.2.0 =" headings, "* " bullets) into HTML.
 *
 * @param string $text Section text.
 * @return string
 */
function readme_html( $text ) {
	$html    = '';
	$in_list = false;
	foreach ( preg_split( '/\R/', trim( $text ) ) as $line ) {
		$line = trim( $line );
		$item = 0 === strpos( $line, '* ' );
		if ( $in_list && ! $item ) {
			$html   .= '</ul>';
			$in_list = false;
		}
		if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $m ) ) {
			$html .= '<h4>' . htmlspecialchars( $m[1] ) . '</h4>';
		} elseif ( $item ) {
			$html   .= ( $in_list ? '' : '<ul>' ) . '<li>' . htmlspecialchars( substr( $line, 2 ) ) . '</li>';
			$in_list = true;
		} elseif ( '' !== $line ) {
			$html .= '<p>' . htmlspecialchars( $line ) . '</p>';
		}
	}

	return $html . ( $in_list ? '</ul>' : '' );
}

/**
 * A "== Name ==" section of readme.txt.
 *
 * @param string $readme Readme text.
 * @param string $name   Section name.
 * @return string
 */
function section( $readme, $name ) {
	return preg_match( '/^==\s*' . preg_quote( $name, '/' ) . '\s*==\s*$(.*?)(?=^==\s|\z)/msi', $readme, $m ) ? $m[1] : '';
}

$repo = 'csi-innovation/csi-cookie-consent';

echo json_encode(
	array(
		'name'         => field( $header, 'Plugin Name' ),
		'version'      => $version,
		'package'      => "https://github.com/$repo/releases/download/v$version/csi-cookie-consent.zip",
		'requires'     => field( $header, 'Requires at least' ),
		'requires_php' => field( $header, 'Requires PHP' ),
		'tested'       => field( $readme, 'Tested up to' ),
		'last_updated' => gmdate( 'Y-m-d H:i:s' ) . ' GMT',
		'sections'     => array(
			'description' => readme_html( section( $readme, 'Description' ) ),
			'changelog'   => readme_html( section( $readme, 'Changelog' ) ),
		),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . "\n";
