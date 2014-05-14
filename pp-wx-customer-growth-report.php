<?php
/*
Plugin Name: WooCommerce Extension - Customer Growth Report
Plugin URI: http://pootlepress.com/
Description: An extension for WooCommerce that allow you to see customer growth report
Version: 1.0.0
Author: PootlePress
Author URI: http://pootlepress.com/
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( 'pp-wx-customer-growth-report-functions.php' );

if (!class_exists('Wx_Admin_Report')) {
    include_once('classes/class-wx-admin-report.php');
}

require_once( 'classes/class-pp-wx-customer-growth-report.php' );


$GLOBALS['pootlepress_wx_customer_growth_report'] = new Pootlepress_Wx_Customer_Growth_Report( __FILE__ );
$GLOBALS['pootlepress_wx_customer_growth_report']->version = '1.0.0';

?>
