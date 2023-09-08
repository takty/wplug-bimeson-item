<?php
/**
 * Utilities for Bimeson Item
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2023-09-08
 */

namespace wplug\bimeson_item;

/**
 * Makes the title of an item.
 *
 * @param string $body The body of an item.
 * @param string $date The date of an item.
 * @return string Title.
 */
function make_title( string $body, string $date ): string {
	$body  = normalize_body( $body );
	$words = explode( ' ', $body );
	$fw    = ( $words[0] !== $body ) ? $words[0] : '';
	$dp    = explode( '-', $date );
	$year  = ( $dp[0] !== $date ) ? $dp[0] : '';
	return $fw . $year;
}

/**
 * Makes the digest of an item.
 *
 * @param string $body The body of an item.
 * @return string Digest.
 */
function make_digest( string $body ): string {
	$body = normalize_body( $body );
	$body = str_replace( ' ', '', $body );
	return hash( 'sha224', $body );
}

/**
 * Normalizes body string.
 *
 * @param string $body The body of an item.
 * @return string Normalized string.
 */
function normalize_body( string $body ): string {
	$body = wp_strip_all_tags( trim( $body ) );
	$body = mb_convert_kana( $body, 'rnasKV' );
	$body = mb_strtolower( $body );
	$body = preg_replace( '/[\s!-\/:-@[-`{-~]|[、。，．・：；？！´｀¨＾￣＿―‐／＼～∥｜…‥‘’“”（）〔〕［］｛｝〈〉《》「」『』【】＊※]/u', ' ', $body ) ?? $body;
	$body = preg_replace( '/\s(?=\s)/', '', $body ) ?? $body;
	$body = trim( $body );
	return $body;
}
