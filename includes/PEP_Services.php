<?php
/**
 * Created by PhpStorm.
 * User: robert
 * Date: 21/05/2018
 * Time: 5:16 PM
 */


class PEP_Services {

	/**
	 * @return array
	 */
	public static function services(): array {
		return self::get_pep_codes();
	}

	/**
	 * pull the codes from the csv file in the plugin directory
	 * will be migrated to sheet or maybe in the PEP hub system
	 *
	 * @return array
	 */
	private static function get_pep_codes(): array {
		$csv = array_map( 'str_getcsv', file( plugin_dir_path( __FILE__ ) . 'pep-service-codes.csv' ) );

		array_walk( $csv, function ( &$a ) use ( $csv ) {
			$a = array_combine( $csv[0], $a );
		} );

		array_shift( $csv ); # remove column header
		$service_code = [];

		foreach ( $csv as $item ) {
			$service_code[ $item['code'] ] = $item;
		}

		return $service_code;
	}

}