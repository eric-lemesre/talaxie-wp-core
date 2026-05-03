<?php
/**
 * Helper used by the i18n pipeline: read the POT file and emit a JSON
 * map { "0": "First string", "1": "Second string", ... } so a translator
 * (human or LLM) can return parallel translations addressed by index.
 *
 * Usage: php bin/extract-pot-strings.php languages/talaxie-core.pot
 */

declare(strict_types=1);

if ( $argc < 2 ) {
	fwrite( STDERR, "usage: php extract-pot-strings.php <pot-file>\n" );
	exit( 1 );
}

$pot = (string) file_get_contents( $argv[1] );
if ( '' === $pot ) {
	fwrite( STDERR, "empty pot\n" );
	exit( 1 );
}

$lines   = preg_split( '/\R/', $pot );
$blocks  = array();
$current = array();
foreach ( $lines as $line ) {
	if ( '' === trim( $line ) ) {
		if ( $current ) {
			$blocks[] = $current;
			$current  = array();
		}
		continue;
	}
	$current[] = $line;
}
if ( $current ) {
	$blocks[] = $current;
}

function unquote_msg( array $lines, string $prefix ): ?string {
	$out  = null;
	$busy = false;
	foreach ( $lines as $l ) {
		if ( strncmp( $l, $prefix . ' ', strlen( $prefix ) + 1 ) === 0 ) {
			$busy = true;
			$rest = substr( $l, strlen( $prefix ) + 1 );
			$out  = ( $out ?? '' ) . unquote_chunk( $rest );
			continue;
		}
		if ( $busy && '"' === ( $l[0] ?? '' ) ) {
			$out .= unquote_chunk( $l );
			continue;
		}
		if ( $busy ) {
			break;
		}
	}
	return $out;
}

function unquote_chunk( string $quoted ): string {
	$quoted = trim( $quoted );
	if ( '' === $quoted || '"' !== $quoted[0] ) {
		return '';
	}
	$inner = substr( $quoted, 1, -1 );
	return stripcslashes( $inner );
}

$strings = array();
foreach ( $blocks as $block ) {
	$msgid = unquote_msg( $block, 'msgid' );
	if ( null === $msgid || '' === $msgid ) {
		continue;
	}
	$strings[] = $msgid;
}

echo json_encode( $strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
