<?php
namespace AffiliateLTP;

use \AffiliateWP_Multi_Level_Marketing;
use AffiliateLTP\AffiliateWP\Affiliate_WP_Referral_Meta_DB;
use AffiliateLTP\admin\Menu;
use AffiliateLTP\admin\Referrals;
use AffiliateLTP\admin\Settings;
use AffiliateLTP\admin\Tools;
use AffiliateLTP\Progress_Item_DB;
use AffiliateLTP\admin\Agent_DAL;
use AffiliateLTP\admin\Agent_DAL_Affiliate_WP_Adapter;
use AffiliateLTP\admin\Settings_DAL;
use AffiliateLTP\admin\Settings_DAL_Affiliate_WP_Adapter;

use AffiliateLTP\Sugar_CRM_DAL;
use AffiliateLTP\Sugar_CRM_DAL_Localhost;

use AffiliateLTP\admin\GravityForms\Gravity_Forms_Bootstrap;
use AffiliateLTP\Agent_Checklist_AJAX;
use AffiliateLTP\Agent_Partner_Search_AJAX;
use AffiliateLTP\admin\Affiliates;

use AffiliateLTP\dashboard\Agent_Events;

use AffiliateLTP\commands\Command_Registration;

/**
 * Main starting point for the plugin.  Registers all the classes.
 *
 * @author snielson
 */
class Plugin {
        
    const AFFILIATEWP_LTP_VERSION = "0.3.2";
    
    const LOCALHOST_RESTRICTED = true;
    
    /**
     * Whether the errors and ommissions stripe account handling
     * is enabled or not.
     */
    const STRIPE_EO_HANDLING_ENABLED = true;
    
    private $settings;
    
    /**
     * The meta object for interacting with referrals.
     * @var Affiliate_WP_Referral_Meta_DB 
     */
    public $referralMeta;
    
    /**
     * The progress items database for interacting with progress items.
     * @var Progress_Item_DB
     */
    private $progress_items;
    
    /**
     *
     * @var AffiliateLTP
     */
    private static $instance = null;
    
    public function __construct() {
        
        if( is_admin() ) {
            
            
            // setup the settings.
            $this->settings = new Settings();
            
            // setup our admin scripts.
            add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts' ) );
        }
        new Shortcodes(); //setup the shortcodes.
        // setup the gravity forms
        // TODO: stephen we should probably take this out of admin since it
        // also controls non-admin functionality.
        new Gravity_Forms_Bootstrap();
        new Agent_Checklist_AJAX();
        new Agent_Partner_Search_AJAX($this->get_agent_dal(), $this->get_settings_dal());
        new Affiliates(); // setup the affiliate actions.
        
        
        // come in last here.
        add_filter( 'load_textdomain_mofile', array($this, 'load_ltp_en_mofile'), 50, 2 );
        
        // do some cleanup on the plugins
        add_action ('plugins_loaded', array($this, 'remove_affiliate_wp_mlm_tab_hooks'));
        add_action ('plugins_loaded', array($this, 'setup_dependent_objects') );
        
        add_action( 'init', array($this, 'load_ltp_affiliate_ranks_translation' ) );
        
        add_filter( 'affwp_affiliate_area_show_tab', array($this, 'remove_unused_tabs'), 10, 2 );
        
        add_action( 'affwp_affiliate_dashboard_tabs', array( $this, 'add_agent_tabs' ), 10, 2 );
        
        // so we can check to see if its active
        add_filter( 'affwp_affiliate_area_tabs', function( $tabs ) {
            
                $new_tabs = array_merge( $tabs, array( 'organization', 'events', 'signup') );
                return $new_tabs;
        } );
        
        // we need to clear the tracking cookie when an affiliate registers
        add_action( 'affwp_register_user', array( $this, 'clearTrackingCookie' ), 10, 3 );
        
        add_action( 'affwp_affiliate_dashboard_after_graphs', array( $this, 'addPointsToGraphTab' ), 10, 1);
        
        $this->add_plugin_scripts_and_styles();
    }
    
    private function register_cli_commands() {
        if (class_exists('WP_CLI')) {
            $command_registration = new Command_Registration($this->get_settings_dal(), $this->get_agent_dal());
            $command_registration->register();
        }
    }
    
    private function add_plugin_scripts_and_styles() {
        $includePath = AFFILIATE_LTP_PLUGIN_URL;
        
        wp_enqueue_style('fancy-box', $includePath . 'assets/fancybox/source/jquery.fancybox.css');
        wp_enqueue_script('fancy-box', $includePath . 'assets/fancybox/source/jquery.fancybox.js', array('jquery'));
        
        wp_enqueue_style( 'affiliate-ltp', $includePath . 'assets/css/affiliate-ltp.css', array('fancy-box') );
        
        error_log("including affiliate-ltp-core");
        wp_enqueue_script( 'affiliate-ltp-core', $includePath . 'assets/js/affiliate-ltp-core.js', array( 'jquery', 'fancy-box'  ) );
        wp_localize_script( 'affiliate-ltp-core', 'wp_ajax_object',
            array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
        // make sure it's always there...
        $wp_scripts = wp_scripts();
        $theme = 'ui-lightness';
        wp_enqueue_style('affiliate-ltp-jquery-ui-css',
                        'http://ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/' . $theme . '/jquery-ui.css');
        wp_enqueue_script('jquery-ui-autocomplete', '', array('jquery-ui-widget', 'jquery-ui-position'), '1.8.6');
        
        
        
    }
    
    /**
     * 
     * @return \AffiliateLTP\admin\Commission_DAL
     */
    public function get_commission_dal() {
        // TODO: stephen I really don't like this
        return new admin\Commission_Dal_Affiliate_WP_Adapter($this->referralMeta);
    }
    
    /**
     * 
     * @return Agent_DAL
     */
    public function get_agent_dal() {
        return new Agent_DAL_Affiliate_WP_Adapter();
    }
    
    /**
     * 
     * @return Settings_DAL
     */
    public function get_settings_dal() {
        return new Settings_DAL_Affiliate_WP_Adapter();
    }
    
    public function addPointsToGraphTab( $affiliate_id ) {
        
        // TODO: stephen see if there's a way to get around this global function
        $points_retriever = new \AffiliateLTP\Points_Retriever( $this->referralMeta );
        
        $date_range = affwp_get_report_dates();
        $start = $date_range['year'] . '-' . $date_range['m_start'] . '-' . $date_range['day'] . ' 00:00:00';
        $end   = $date_range['year_end'] . '-' . $date_range['m_end'] . '-' . $date_range['day_end'] . ' 23:59:59';
        
//        $is_partner = $this->get_agent_dal()->get_current_user_agent_id();
        $is_partner = $this->get_partner_status_for_current_agent();
        if ($is_partner) {
            // this value is inserted via javascript since the current plugin
            // does not give us a way to extend the search filters.
            $include_super_shop = filter_input(INPUT_GET, 
                'affwp_ltp_include_super_base_shop') == 'Y';
        }
        else {
            $include_super_shop = false;
        }
        $points_date_range = array(
            "start_date" => $start
            ,"end_date" => $end
            ,"range" => $date_range['range']
        );
        
        $agent_downline = $this->get_agent_dal()->get_agent_downline_with_coleaderships($agent_id);
        
        $points_data = $points_retriever->get_points( $affiliate_id, $points_date_range, $include_super_shop );
        
        $graph = new \AffiliateLTP\Points_Graph($points_data, $this->referralMeta, $points_date_range);
//        $graph = new \AffiliateLTP\Points_Graph;
	$graph->set( 'x_mode', 'time' );
	$graph->set( 'affiliate_id', $affiliate_id );
        // hide the date filter since the graph above this one controls all the
        // date filters.
        $graph->set( 'show_controls', false );
        
//        $data = $graph->get_data();
//                        echo "<pre>";
//                var_dump($data);
//                echo "</pre>";
        
        $template_path = affiliate_wp()->templates->get_template_part('dashboard-tab', 'graphs-point', false);
        
        include_once $template_path;
    }
    
    private function get_partner_status_for_current_agent() {
        
        $agent_id = $this->get_agent_dal()->get_current_user_agent_id();
        $partner_rank_id = $this->get_settings_dal()->get_partner_rank_id();
        $agent_rank = $this->get_agent_dal()->get_agent_rank($agent_id);
        
        return $agent_rank == $partner_rank_id;
    }
    /**
     * 
     * @return Sugar_CRM_DAL
     */
    public function getSugarCRM() {
        if (self::LOCALHOST_RESTRICTED) {
            return Sugar_CRM_DAL_Localhost::instance();
        }
        else {
            return Sugar_CRM_DAL::instance();
        }
    }
    
    public function getReferralMetaDb() {
        return $this->referralMeta;
    }
    
    /*
     * Retrieves the progress item database
     * @return Progress_Item_DB
     */
    public function get_progress_items_db() {
        return $this->progress_items;
    }
    
    /**
     * Retrieves the commission request db
     * @return Commission_Request_DB
     */
    public function get_commission_request_db() {
        return $this->commission_request_db;
    }
    
    /**
     * Returns the template loader for loading php template files.
     * @return Template_Loader
     */
    public function get_template_loader() {
        return $this->template_loader;
    }
    
    public function setup_dependent_objects() {
        
        // need to register commands after the other plugins have executed.
        $this->register_cli_commands();
        
        $this->referralMeta = new Affiliate_WP_Referral_Meta_DB();
        $this->progress_items = new Progress_Item_DB();
        $this->commission_request_db = new Commission_Request_DB();
        $this->template_loader = new Template_Loader();
        
        // setup chart hooks and handlers (mem references will be retained by the hooks).
        $agent_org_chart_handler = new charts\Agent_Organization_Chart();
        // construct it so we can have the right hooks and everything.
        $agent_emails = new Agent_Emails($this->get_agent_dal(), $this->get_settings_dal());
        
        if (is_admin()) {
            $this->adminReferrals = new Referrals($this->referralMeta);
            // TODO: stephen look at renaming the AdminMenu to keep with our naming convention
            $adminMenu = new Menu($this->adminReferrals);
            
            $settings_dal = new Settings_DAL_Affiliate_WP_Adapter();
            
            $tools = new Tools($this->get_agent_dal(), $this->getSugarCRM(),
                    $this->get_commission_dal(), $settings_dal);
        }
        $leaderboards = new leaderboards\Leaderboards($this->get_settings_dal(), $this->get_agent_dal());
        
        new Agent_Events($this->get_settings_dal(), $this->get_template_loader());
    }

    /**
     * Since the AffiliateWP_Multi_Level_Marketing class does not use the affiliatewp
     * affwp_affiliate_area_show_tab() function we have to just remove the hook
     * alltogether.
     */
    public function remove_affiliate_wp_mlm_tab_hooks() {
        if (class_exists("AffiliateWP_Multi_Level_Marketing")) {
            $instance = AffiliateWP_Multi_Level_Marketing::instance();
            remove_action( 'affwp_affiliate_dashboard_tabs', array( $instance, 'add_sub_affiliates_tab' ));
        }
    }
    
    /** Add any admin javascript files we need to load **/
    public function admin_scripts() {
        if( ! affwp_is_admin_page() ) {
		return;
	}

        $suffix = "";
//	$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        $plugin_url = AFFILIATE_LTP_PLUGIN_URL;
        $url = $plugin_url . 'assets/js/admin/ltp-admin' . $suffix . '.js';
        wp_enqueue_script( 'angular', $plugin_url . 'assets/js/bower_components/angular/angular.min.js');
	wp_enqueue_script( 'affiliate-ltp-admin', $url, array( 'jquery', 'jquery-ui-autocomplete', 'angular' ));
        
        wp_enqueue_style( 'affwp-admin', $plugin_url . 'assets/css/admin' . $suffix . '.css', array());
        
    }

    /**
     * Our plugin can override other plugin language files.
     * @param string $mofile
     * @param string $domain
     * @return string
     */
    public function load_ltp_en_mofile( $mofile, $domain )
    {
        // remove any slashes from the filename so we can't try to include
        // directories.
        $safe_domain = str_replace('/', '_', $domain);
        
        $include_file = AFFILIATE_LTP_PLUGIN_DIR . "/languages/" . $safe_domain . "-en.mo";
        if (file_exists($include_file)) {
            return $include_file;
        }
        return $mofile;
    }
    
    public function load_ltp_affiliate_ranks_translation() {
        load_plugin_textdomain( 'affiliatewp-ranks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
    
    /**
     * Remove the tabs that are not used for the agent system.
     * @param boolean $currentValue the current value of displaying the tab or not.
     * @param string $tab the tab to check if we need to remove
     */
    public function remove_unused_tabs( $currentValue, $tab ) {
        $shouldDisplay = true;
        
        // if the current value is not true just escape.
        if (!$currentValue) {
            return $currentValue;
        }
        
        switch ($tab) {
            // leaving in sub-affiliates in case the MLM plugin ever fixes
            // itself to use the right functions and can be filtered out...
            case 'sub-affiliates':
//            case 'urls':
            case 'visits':
            case 'creatives': {
                $shouldDisplay = false;
            }
            break;
            default: {
                $shouldDisplay = true;
            }
        }
        
        return $shouldDisplay;
    }
    
    /**
     * Add the organization tab.
     * @param type $affiliate_id
     * @param type $active_tab
     */
    public function add_agent_tabs( $affiliate_id, $active_tab ) {
        
        // make sure we only show the tab if it hasn't been filtered out.
        if (affwp_affiliate_area_show_tab( 'organization' )) {
            ?>
            <li class="affwp-affiliate-dashboard-tab<?php echo $active_tab == 'organization' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'organization' ) ); ?>"><?php _e( 'Organization', 'affiliate-ltp' ); ?></a>
            </li>
                <?php	
        }
        
        if (affwp_affiliate_area_show_tab( 'events' )) {
            ?>
            <li class="affwp-affiliate-dashboard-tab<?php echo $active_tab == 'events' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'events' ) ); ?>"><?php _e( 'Events', 'affiliate-ltp' ); ?></a>
            </li>
                <?php	
        }
        
        if (affwp_affiliate_area_show_tab( 'signup' )) {
            ?>
            <li class="affwp-affiliate-dashboard-tab<?php echo $active_tab == 'signup' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'signup' ) ); ?>"><?php _e( 'Signup', 'affiliate-ltp' ); ?></a>
            </li>
                <?php	
        }
    }
   
   /**
    * 
    * @return Plugin
    */
   public static function instance() {
       if (self::$instance == null) {
           self::$instance = new Plugin();
       }
       return self::$instance;
   }
   
   public function clearTrackingCookie( $affiliateId, $status, $userData ) {
       $trackingAffiliateId = affiliate_wp()->tracking->get_affiliate_id();
       if (!empty($trackingAffiliateId)) {

        // TODO: stephen currently there's no way to clear a cookie or set the cookie to expire anything shorter than 1 day
        // this has to be kept in sync with the plugin code unfortunately so if they change the name or anything else... we have to deal with it.
        // setting the time to 0 leaves the cookie in the session
        // setting it to expire before the current time will clear the cookie on the next
        // page refresh
        $expirationTime = time() - 3600;
        setcookie( 'affwp_ref', null, $expirationTime, 
                COOKIEPATH, COOKIE_DOMAIN );
        
        // unfortunately scripts may still use the $_COOKIE array when they shouldn't
        // be touching it directly so we manually clear it
        unset($_COOKIE['affwp_ref']);
       }
   }
}
