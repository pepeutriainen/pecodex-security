<?php
/**
 * Encryption class for Pecodex Media Control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_Encryption {

	/**
	 * Get the encryption key.
	 *
	 * @return string
	 */
	private static function get_key() {
		// Use AUTH_KEY from wp-config.php, hash it to ensure a valid 32-byte key for AES-256
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'default_fallback_key';
		return hash( 'sha256', $key, true );
	}

	/**
	 * Encrypt data using AES-256-GCM.
	 *
	 * @param string $data Data to encrypt.
	 * @return string|false Base64 encoded string containing IV, Tag, and Ciphertext, or false on failure.
	 */
	public static function encrypt( $data ) {
		$cipher = 'aes-256-gcm';
		$key    = self::get_key();
		
		$ivlen = openssl_cipher_iv_length( $cipher );
		if ( false === $ivlen ) {
			return false;
		}

		$iv = openssl_random_pseudo_bytes( $ivlen );
		$tag = '';
		
		$encrypted = openssl_encrypt( $data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag );
		
		if ( false === $encrypted ) {
			return false;
		}
		
		// Prepend IV and Tag to the ciphertext and base64 encode
		return base64_encode( $iv . $tag . $encrypted );
	}

	/**
	 * Decrypt data using AES-256-GCM.
	 *
	 * @param string $data Base64 encoded string containing IV, Tag, and Ciphertext.
	 * @return string|false Decrypted string, or false on failure.
	 */
	public static function decrypt( $data ) {
		$cipher = 'aes-256-gcm';
		$key    = self::get_key();
		
		$decoded = base64_decode( $data );
		if ( false === $decoded ) {
			return false;
		}
		
		$ivlen  = openssl_cipher_iv_length( $cipher );
		$taglen = 16; // GCM tag length is typically 16 bytes
		
		if ( false === $ivlen || strlen( $decoded ) < $ivlen + $taglen ) {
			return false;
		}
		
		$iv        = substr( $decoded, 0, $ivlen );
		$tag       = substr( $decoded, $ivlen, $taglen );
		$encrypted = substr( $decoded, $ivlen + $taglen );
		
		return openssl_decrypt( $encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag );
	}
}
