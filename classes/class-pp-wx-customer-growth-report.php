<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Pootlepress_Wx_Customer_Growth_Report Class
 *
 * Base class for the Pootlepress Customer Growth Report.
 *
 * @package WordPress
 * @subpackage Pootlepress_Wx_Customer_Growth_Report
 * @category Core
 * @author Pootlepress
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * public $token
 * public $version
 * 
 * - __construct()
 * - add_theme_options()
 * - get_menu_styles()
 * - load_stylesheet()
 * - load_script()
 * - load_localisation()
 * - check_plugin()
 * - load_plugin_textdomain()
 * - activation()
 * - register_plugin_version()
 * - get_header()
 * - woo_nav_custom()
 */
class Pootlepress_Wx_Customer_Growth_Report extends Wx_Admin_Report {
	public $token = 'pp-wx-cgr';
	public $version;
	private $file;

	/**
	 * Constructor.
	 * @param string $file The base file of the plugin.
	 * @access public
	 * @since  1.0.0
	 * @return  void
	 */
	public function __construct ( $file ) {
		$this->file = $file;
		$this->load_plugin_textdomain();
		//add_action( 'init', 'check_main_heading', 0 );
		add_action( 'init', array( &$this, 'load_localisation' ), 0 );

		// Run this on activation.
		register_activation_hook( $file, array( &$this, 'activation' ) );

		// Add the custom theme options.
		//add_filter( 'option_woo_template', array( &$this, 'add_theme_options' ) );

        add_filter('woocommerce_admin_reports', array(&$this, 'add_report'));


//        add_action( 'admin_print_scripts', array( &$this, 'load_admin_script' ) );
//        add_action( 'admin_print_styles', array( &$this, 'load_admin_style' ) );
//
//        add_action( 'wp_enqueue_scripts', array( &$this, 'load_script' ) );
//
//        add_action('wp_head', array(&$this, 'option_css'));

	} // End __construct()

    public function add_report($reports) {
        if (!isset($reports['customers'])) {
            $reports['customers'] = array('title' => 'Customers', 'reports' => array());
        }
        if (!isset($reports['customers']['reports'])) {
            $reports['customers']['reports'] = array();
        }
        if (!isset($reports['customers']['reports']['customer_growth'])) {
            $reports['customers']['reports']['customer_growth'] = array();
        }

        $reports['customers']['reports']['customer_growth'] = array(
            'title' => 'Customer Growth',
            'description' => '',
            'hide_title' => true,
            'callback' => array(&$this, 'get_report')
        );

        return $reports;
    }

    public function get_report() {
        $this->output_report();
    }

    public $chart_colours = array();

    /**
     * Get the legend for the main chart sidebar
     * @return array
     */
    public function get_chart_legend() {
        $legend   = array();

        $legend[] = array(
            'title' => sprintf( __( '%s new unique customers in this period (accounts and guest checkouts', 'pp-wx-cgr' ), '<strong>' . sizeof( $this->customers ) . '</strong>' ),
            'color' => $this->chart_colours['new_customers'],
            'highlight_series' => 0
        );

        return $legend;
    }

    public function output_report() {
        global $woocommerce, $wpdb, $wp_locale;

        $ranges = array(
            'year'         => __( 'Year', 'woocommerce' ),
            'last_month'   => __( 'Last Month', 'woocommerce' ),
            'month'        => __( 'This Month', 'woocommerce' ),
            '7day'         => __( 'Last 7 Days', 'woocommerce' )
        );

        $this->chart_colours = array(
            'new_customers'   => '#3498db'
        );

        $current_range = ! empty( $_GET['range'] ) ? $_GET['range'] : '7day';

        if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) )
            $current_range = '7day';

        $this->calculate_current_range( $current_range );

        $shopOrders = get_posts(array('post_type' => 'shop_order',
            'nopaging' => true,
            'posts_per_page' => -1
//            'date_query' => array(
//                'after' => array(
//                    'year' => $startYear, 'month' => $startMonth, 'day' => $startDay
//                ),
//                'before' => array(
//                    'year' => $endYear, 'month' => $endMonth, 'day' => $endDay
//                ),
//                'inclusive' => true
//            )
        ));

        $customerEmails = array();
        $customers = array();
        foreach ($shopOrders as $order) {
            $postID = $order->ID;
            $email = get_post_meta($postID, '_billing_email', true);
            if (empty($email)) {
                continue;
            }

            if (!in_array($email, $customerEmails)) {
                $customerEmails[] = $email;
                $customer = array('user_registered' => $order->post_date);
                $customer = (object)$customer;
                $customers[] = $customer;
            }
        }

        $this->customers = $customers;

        $endDT = new DateTime();
        $endDT->setTimestamp($this->end_date);
        $endDT->setTime(23, 59, 59);
        $endUnixTime = $endDT->getTimestamp();

//        echo "<pre>";
//        echo "start time: " . $this->start_date . "\n";
//        echo "end time: " . $endUnixTime . "\n";
//        echo "shop order count: " . count($shopOrders) . "\n";
//        echo "customer count: " . count($this->customers) . "\n";

        foreach ( $this->customers as $key => $customer ) {
//            echo "user_registered: " . $customer->user_registered . " unix(" . strtotime($customer->user_registered) . ")";
            if ( strtotime( $customer->user_registered ) < $this->start_date || strtotime( $customer->user_registered ) > $endUnixTime ) {
                unset($this->customers[$key]);
//                echo " Unset";
            }
//            echo "\n";
        }
//        echo "</pre>";

        include( WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php' );
    }

    /**
     * Get the main chart
     * @return string
     */
    public function get_main_chart() {
        global $wp_locale;

        $newUniqueCustomers = $this->prepare_chart_data( $this->customers, 'user_registered', '', $this->chart_interval, $this->start_date, $this->chart_groupby );

        // Encode in json format
        $chart_data = json_encode( array(
            'new_unique_customers'         => array_values( $newUniqueCustomers )
        ) );
        ?>
        <div class="chart-container">
            <div class="chart-placeholder main"></div>
        </div>
        <script type="text/javascript">
            var main_chart;

            jQuery(function(){
                    var chart_data = jQuery.parseJSON( '<?php echo $chart_data; ?>' );

                    var drawGraph = function( highlight ) {
                            var series = [
                {
                    label: "<?php echo esc_js( __( 'New Unique Customers', 'woocommerce' ) ) ?>",
                    data: chart_data.new_unique_customers,
                    color: '<?php echo $this->chart_colours['new_customers']; ?>',
                    points: { show: true, radius: 5, lineWidth: 3, fillColor: '#fff', fill: true },
                    lines: { show: true, lineWidth: 4, fill: false },
                    shadowSize: 0,
                    enable_tooltip: true,
                    append_tooltip: "<?php echo ' ' . __( 'new users', 'woocommerce' ); ?>",
                    stack: false
                }
            ];

            if ( highlight !== 'undefined' && series[ highlight ] ) {
                highlight_series = series[ highlight ];

                highlight_series.color = '#9c5d90';

                if ( highlight_series.bars )
                    highlight_series.bars.fillColor = '#9c5d90';

                if ( highlight_series.lines ) {
                    highlight_series.lines.lineWidth = 5;
                }
            }

            main_chart = jQuery.plot(
                jQuery('.chart-placeholder.main'),
                series,
                {
                    legend: {
                        show: false
                    },
                    grid: {
                        color: '#aaa',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        hoverable: true
                    },
                    xaxes: [ {
                        color: '#aaa',
                        position: "bottom",
                        tickColor: 'transparent',
                        mode: "time",
                        timeformat: "<?php if ( $this->chart_groupby == 'day' ) echo '%d %b'; else echo '%b'; ?>",
                        monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ) ?>,
                        tickLength: 1,
                        minTickSize: [1, "<?php echo $this->chart_groupby; ?>"],
                        tickSize: [1, "<?php echo $this->chart_groupby; ?>"],
                        font: {
                            color: "#aaa"
                        }
                    } ],
                    yaxes: [
                        {
                            min: 0,
                            minTickSize: 1,
                            tickDecimals: 0,
                            color: '#ecf0f1',
                            font: { color: "#aaa" }
                        }
                    ],
                }
            );
            jQuery('.chart-placeholder').resize();
            }

            drawGraph();

            jQuery('.highlight_series').hover(
                function() {
                    drawGraph( jQuery(this).data('series') );
                },
                function() {
                    drawGraph();
                }
            );
            });
        </script>
    <?php
    }



    public function load_admin_script() {
        $screen = get_current_screen();
        if ($screen->base == 'post' && $screen->id == 'slide') {
            $pluginFile = dirname(dirname(__FILE__)) . '/pootlepress-featured-video.php';
            wp_enqueue_script('pootlepress-featured-video-admin', plugin_dir_url($pluginFile) . 'scripts/featured-video-admin.js', array('jquery'));
        }
    }

    public function load_admin_style() {
        $screen = get_current_screen();
        if ($screen->base == 'post' && $screen->id == 'slide') {
            $pluginFile = dirname(dirname(__FILE__)) . '/pootlepress-featured-video.php';
            wp_enqueue_style('pootlepress-featured-video-admin', plugin_dir_url($pluginFile) . 'styles/featured-video-admin.css');
        }
    }

    public function load_script() {
        $pluginFile = dirname(dirname(__FILE__)) . '/pootlepress-masonry-shop.php';
        wp_enqueue_script('pootlepress-featured-video', plugin_dir_url($pluginFile) . 'scripts/featured-video.js', array('jquery'));

        $sliderFullWidthEnabled = get_option('woo_slider_biz_full', 'false');
        $b = ($sliderFullWidthEnabled === 'true');
        wp_localize_script('pootlepress-featured-video', 'FeaturedSliderParam', array('isSliderFullWidth' => $b));
    }

	/**
	 * Add theme options to the WooFramework.
	 * @access public
	 * @since  1.0.0
	 * @param array $o The array of options, as stored in the database.
	 */
	public function add_theme_options ( $o ) {

        return $o;
	} // End add_theme_options()



    public function option_css() {
            $css = '';

            $css .= "#loopedSlider .slide { text-align: center; }\n";
            $css .= "#loopedSlider .slide .wp-video { display: inline-block; }\n";

            echo "<style>".$css."</style>";
    }

	/**
	 * Load stylesheet required for the style, if has any.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function load_stylesheet () {

	} // End load_stylesheet()

	/**
	 * Load the plugin's localisation file.
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function load_localisation () {
		load_plugin_textdomain( $this->token, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation()

	/**
	 * Load the plugin textdomain from the main WordPress "languages" folder.
	 * @access public
	 * @since  1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
	    $domain = $this->token;
	    // The "plugin_locale" filter is also used in load_plugin_textdomain()
	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	 
	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain()

	/**
	 * Run on activation.
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function activation () {
		$this->register_plugin_version();
	} // End activation()

	/**
	 * Register the plugin's version.
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	private function register_plugin_version () {
		if ( $this->version != '' ) {
			update_option( $this->token . '-version', $this->version );
		}
	} // End register_plugin_version()


} // End Class


