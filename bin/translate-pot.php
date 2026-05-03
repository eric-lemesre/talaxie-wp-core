<?php
/**
 * Translate the .pot template into a .po file using a local Ollama model.
 *
 * Usage:
 *   php bin/translate-pot.php <pot> <locale> <language-name>
 *
 * Batches msgids (25 per request) and calls the local Ollama HTTP API.
 */

declare(strict_types=1);

if ( $argc < 4 ) {
	fwrite( STDERR, "usage: php translate-pot.php <pot> <locale> <language-name>\n" );
	exit( 1 );
}

$pot_path   = $argv[1];
$locale     = $argv[2];
$language   = $argv[3];
$model      = getenv( 'OLLAMA_MODEL' ) ?: 'magistral:latest';
$ollama_url = ( getenv( 'OLLAMA_URL' ) ?: 'http://localhost:11434' ) . '/api/chat';
$batch_size = (int) ( getenv( 'OLLAMA_BATCH' ) ?: 25 );
$po_path    = dirname( $pot_path ) . '/talaxie-core-' . $locale . '.po';

$blocks = parse_pot_blocks( $pot_path );
$msgids = array();
foreach ( $blocks as $i => $block ) {
	if ( '' !== $block['msgid'] ) {
		$msgids[ $i ] = $block['msgid'];
	}
}

fwrite( STDERR, sprintf( "[%s] %d strings, %d per batch\n", $locale, count( $msgids ), $batch_size ) );

$translations = array();
foreach ( array_chunk( $msgids, $batch_size, true ) as $chunk ) {
	$keys   = array_keys( $chunk );
	$inputs = array_values( $chunk );

	$out = call_translate( $ollama_url, $model, $inputs, $language );
	if ( count( $out ) !== count( $inputs ) ) {
		fwrite( STDERR, sprintf( "[%s] length mismatch (in=%d, out=%d), retry\n", $locale, count( $inputs ), count( $out ) ) );
		$out = call_translate( $ollama_url, $model, $inputs, $language );
	}
	// Detect "no-op" batches: if the model echoed the input unchanged for
	// almost every translatable item, retry — this happens occasionally
	// when the first item is a brand/URL and the model decides to "preserve everything".
	if ( count( $out ) === count( $inputs ) && is_suspiciously_identical( $inputs, $out ) ) {
		fwrite( STDERR, "[$locale] suspicious echo (batch identical to input), retry\n" );
		$out = call_translate( $ollama_url, $model, $inputs, $language );
	}
	if ( count( $out ) !== count( $inputs ) ) {
		fwrite( STDERR, "[$locale] giving up on this batch — keeping msgid as msgstr\n" );
		$out = $inputs;
	}

	foreach ( $keys as $idx => $block_index ) {
		$translations[ $block_index ] = $out[ $idx ];
	}
	fwrite( STDERR, sprintf( "[%s] %d/%d done — sample: in=%s out=%s\n", $locale, count( $translations ), count( $msgids ), substr( $inputs[5] ?? $inputs[0], 0, 60 ), substr( $out[5] ?? $out[0], 0, 60 ) ) );
}

$po = pot_header( $locale );
foreach ( $blocks as $i => $block ) {
	if ( '' === $block['msgid'] ) {
		continue;
	}
	if ( $block['comments'] ) {
		$po .= implode( "\n", $block['comments'] ) . "\n";
	}
	if ( null !== ( $block['msgctxt'] ?? null ) ) {
		$po .= 'msgctxt ' . po_quote( $block['msgctxt'] ) . "\n";
	}
	$po .= 'msgid ' . po_quote( $block['msgid'] ) . "\n";
	$po .= 'msgstr ' . po_quote( $translations[ $i ] ?? $block['msgid'] ) . "\n\n";
}

file_put_contents( $po_path, $po );
fwrite( STDERR, sprintf( "[%s] wrote %s\n", $locale, $po_path ) );

// ----------------------------------------------------------------------------

function parse_pot_blocks( string $path ): array {
	$lines   = preg_split( '/\R/', (string) file_get_contents( $path ) );
	$blocks  = array();
	$current = array(
		'comments' => array(),
		'msgctxt'  => null,
		'msgid'    => '',
	);
	$mode    = null;
	$buffer  = '';

	$flush = static function ( ?string &$mode, string &$buffer, array &$current ): void {
		if ( null === $mode || 'drop' === $mode ) {
			return;
		}
		if ( 'msgctxt' === $mode ) {
			$current['msgctxt'] = ( $current['msgctxt'] ?? '' ) . $buffer;
		} elseif ( 'msgid' === $mode ) {
			$current['msgid'] .= $buffer;
		}
	};

	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			$flush( $mode, $buffer, $current );
			$mode   = null;
			$buffer = '';
			if ( '' !== $current['msgid'] || $current['comments'] || null !== $current['msgctxt'] ) {
				$blocks[] = $current;
				$current  = array(
					'comments' => array(),
					'msgctxt'  => null,
					'msgid'    => '',
				);
			}
			continue;
		}
		if ( '#' === $line[0] ) {
			$current['comments'][] = $line;
			continue;
		}
		if ( str_starts_with( $line, 'msgctxt ' ) ) {
			$flush( $mode, $buffer, $current );
			$mode   = 'msgctxt';
			$buffer = unquote_chunk( substr( $line, 8 ) );
			continue;
		}
		if ( str_starts_with( $line, 'msgid ' ) ) {
			$flush( $mode, $buffer, $current );
			$mode   = 'msgid';
			$buffer = unquote_chunk( substr( $line, 6 ) );
			continue;
		}
		if ( str_starts_with( $line, 'msgstr ' ) ) {
			$flush( $mode, $buffer, $current );
			$mode   = 'drop';
			$buffer = '';
			continue;
		}
		if ( '"' === $line[0] && null !== $mode ) {
			$buffer .= unquote_chunk( $line );
		}
	}
	$flush( $mode, $buffer, $current );
	if ( '' !== $current['msgid'] || $current['comments'] || null !== $current['msgctxt'] ) {
		$blocks[] = $current;
	}
	return $blocks;
}

function unquote_chunk( string $line ): string {
	$line = trim( $line );
	if ( '' === $line || '"' !== $line[0] ) {
		return '';
	}
	return stripcslashes( substr( $line, 1, -1 ) );
}

function is_suspiciously_identical( array $inputs, array $out ): bool {
	$translatable = 0;
	$same         = 0;
	foreach ( $inputs as $i => $in ) {
		// Skip strings that are inherently non-translatable (URLs, plain
		// brand names) so they don't drive false positives.
		if ( str_starts_with( $in, 'http://' ) || str_starts_with( $in, 'https://' ) ) {
			continue;
		}
		if ( preg_match( '/^[A-Z][A-Za-z0-9\\s.-]*$/', $in ) && str_word_count( $in ) <= 3 ) {
			// e.g. "Talaxie Core", "Talaxie Community", "AI Bot"
			continue;
		}
		++$translatable;
		if ( ( $out[ $i ] ?? '' ) === $in ) {
			++$same;
		}
	}
	if ( $translatable === 0 ) {
		return false;
	}
	return ( $same / $translatable ) > 0.5;
}

function po_quote( string $value ): string {
	return '"' . addcslashes( $value, "\0..\37\\\"" ) . '"';
}

function pot_header( string $locale ): string {
	$now = gmdate( 'Y-m-d H:i+0000' );
	return <<<HEADER
# Talaxie Core — translation for {$locale}.
msgid ""
msgstr ""
"Project-Id-Version: Talaxie Core 0.1.0\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/talaxie-core\\n"
"PO-Revision-Date: {$now}\\n"
"Language-Team: Talaxie Community\\n"
"Language: {$locale}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: talaxie-core\\n"


HEADER;
}

function call_translate( string $url, string $model, array $inputs, string $language ): array {
	$system = "You are a WordPress plugin translator. You MUST translate every English UI label, description and message into {$language}. Do NOT echo the English back. Output ONLY a JSON ARRAY between <BEGIN_JSON> and <END_JSON>, same length as input.\n\n"
		. "EXAMPLES of what to TRANSLATE (these MUST become {$language}):\n"
		. "  - 'Get site info'\n"
		. "  - 'List MCP audit entries'\n"
		. "  - 'No release matches that id.'\n"
		. "  - 'Comma-separated capability list.'\n\n"
		. "EXCEPTIONS — keep verbatim INSIDE the translated sentence:\n"
		. "  - URLs (http(s)://...)\n"
		. "  - Brand names: Talaxie, WordPress, GitHub, Discord, Discourse, MCP\n"
		. "  - Acronyms: REST, JSON, HTTP, CPT, sudo, MIME, base64\n"
		. "  - File paths: wp-config.php, wp-admin, wp/v2, wp-json\n"
		. "  - snake_case identifiers: manage_options, edit_posts, delete_posts, edit_pages, delete_pages, ai_bot, talaxie_release, talaxie_releases, super_admin, list_users, edit_users, create_users, delete_users, activate_plugins, upload_files, _sudo, talaxie_mcp_audit, current_user_can, wp_register_ability, wp_get_environment_type\n"
		. "  - placeholders: %s, %d, %1\$s, %2\$s\n\n"
		. "Wrap output strictly between <BEGIN_JSON> and <END_JSON>.\n";

	$payload = array(
		'model'    => $model,
		'stream'   => false,
		'messages' => array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
			array(
				'role'    => 'user',
				'content' => "Translate to {$language}. Output JSON array between <BEGIN_JSON>/<END_JSON> only:\n\n"
					. json_encode( array_values( $inputs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			),
		),
		'options'  => array(
			'temperature' => 0.1,
			'num_ctx'     => 16384,
		),
	);

	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
			CURLOPT_POSTFIELDS     => json_encode( $payload ),
			CURLOPT_TIMEOUT        => 600,
		)
	);

	$response = curl_exec( $ch );
	if ( false === $response ) {
		fwrite( STDERR, 'curl error: ' . curl_error( $ch ) . "\n" );
		curl_close( $ch );
		return array();
	}
	curl_close( $ch );

	$decoded = json_decode( (string) $response, true );
	$content = $decoded['message']['content'] ?? '';
	if ( ! is_string( $content ) || '' === $content ) {
		return array();
	}

	if ( preg_match( '/<BEGIN_JSON>\s*(.*?)\s*<END_JSON>/s', $content, $m ) ) {
		$json_payload = $m[1];
	} elseif ( preg_match( '/(\[[\s\S]*\])/', $content, $m2 ) ) {
		$json_payload = $m2[1];
	} else {
		return array();
	}

	$arr = json_decode( $json_payload, true );
	if ( ! is_array( $arr ) ) {
		return array();
	}
	return array_map( static fn( $v ) => is_string( $v ) ? $v : (string) $v, array_values( $arr ) );
}
