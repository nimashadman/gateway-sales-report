<?php
/**
 * Jalali/Gregorian date helpers.
 *
 * @package ParsiGatewaySalesReport
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small dependency-free Jalali conversion utility.
 */
class PGSR_Jalali {
	/**
	 * Converts Persian/Arabic digits to English digits.
	 *
	 * @param string $value Input value.
	 * @return string
	 */
	public static function normalize_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $persian, $latin, (string) $value );
	}

	/**
	 * Converts English digits to Persian digits.
	 *
	 * @param mixed $value Input value.
	 * @return string
	 */
	public static function persian_digits( $value ) {
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return str_replace( $latin, $persian, (string) $value );
	}

	/**
	 * Parses a Jalali date in yyyy/mm/dd format.
	 *
	 * @param string $date Jalali date.
	 * @return array|false
	 */
	public static function parse_jalali_date( $date ) {
		$date = trim( self::normalize_digits( $date ) );
		$date = str_replace( array( '-', '.', ' ' ), '/', $date );

		if ( ! preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches ) ) {
			return false;
		}

		$jy = (int) $matches[1];
		$jm = (int) $matches[2];
		$jd = (int) $matches[3];

		if ( $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 ) {
			return false;
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * Converts Jalali date to Gregorian date.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month.
	 * @param int $jd Jalali day.
	 * @return array
	 */
	public static function jalali_to_gregorian( $jy, $jm, $jd ) {
		$jy += 1595;
		$days = -355668 + ( 365 * $jy ) + (int) ( $jy / 33 ) * 8 + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd;

		if ( $jm < 7 ) {
			$days += ( $jm - 1 ) * 31;
		} else {
			$days += ( ( $jm - 7 ) * 30 ) + 186;
		}

		$gy = 400 * (int) ( $days / 146097 );
		$days %= 146097;

		if ( $days > 36524 ) {
			$gy += 100 * (int) ( --$days / 36524 );
			$days %= 36524;

			if ( $days >= 365 ) {
				$days++;
			}
		}

		$gy += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$gy += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}

		$gd_months = array( 0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$leap      = ( ( $gy % 4 === 0 ) && ( $gy % 100 !== 0 ) ) || ( $gy % 400 === 0 );
		if ( $leap ) {
			$gd_months[2] = 29;
		}

		for ( $gm = 1; $gm <= 12 && $days >= $gd_months[ $gm ]; $gm++ ) {
			$days -= $gd_months[ $gm ];
		}

		return array( $gy, $gm, $days + 1 );
	}

	/**
	 * Converts Gregorian date to Jalali date.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month.
	 * @param int $gd Gregorian day.
	 * @return array
	 */
	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * Converts a Jalali date string to a WordPress local datetime string.
	 *
	 * @param string $date Jalali date.
	 * @param bool   $end_of_day Whether to return 23:59:59.
	 * @return string|false
	 */
	public static function jalali_to_mysql_datetime( $date, $end_of_day = false ) {
		$parts = self::parse_jalali_date( $date );
		if ( ! $parts ) {
			return false;
		}

		list( $gy, $gm, $gd ) = self::jalali_to_gregorian( $parts[0], $parts[1], $parts[2] );
		$time = $end_of_day ? '23:59:59' : '00:00:00';

		return sprintf( '%04d-%02d-%02d %s', $gy, $gm, $gd, $time );
	}

	/**
	 * Formats a WooCommerce datetime as Jalali.
	 *
	 * @param WC_DateTime|null $date Date object.
	 * @return string
	 */
	public static function format_order_date( $date ) {
		if ( ! $date ) {
			return '';
		}

		$timestamp = $date->getTimestamp() + ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		$gy        = (int) gmdate( 'Y', $timestamp );
		$gm        = (int) gmdate( 'n', $timestamp );
		$gd        = (int) gmdate( 'j', $timestamp );
		$time      = gmdate( 'H:i', $timestamp );
		$jalali    = self::gregorian_to_jalali( $gy, $gm, $gd );

		return self::persian_digits( sprintf( '%04d/%02d/%02d %s', $jalali[0], $jalali[1], $jalali[2], $time ) );
	}
}
