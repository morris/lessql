<?php

// compatibility for PHP < 5.4.0

if ( !interface_exists( 'JsonSerializable' ) ) {

	interface JsonSerializable {

	}

}
