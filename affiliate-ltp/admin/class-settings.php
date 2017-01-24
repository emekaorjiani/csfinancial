<?php
namespace AffiliateLTP\admin;

/**
 * Description of class-settings
 *
 * @author snielson
 */
class Settings {
    
public function __construct() {

		add_filter( 'affwp_settings_tabs', array( $this, 'settings_tab' ) );
		add_filter( 'affwp_settings', array( $this, 'settings' ), 10, 1 );
	}
	
	/**
	 * Register the MLM Settings Tab
	 *
	 * @since 1.0
	 */
	public function settings_tab( $tabs ) {
		$tabs['ltp'] = __( 'Company', 'affiliate-ltp' );
		return $tabs;
	}
	
	/**
	 * Register MLM Settings
	 *
	 * @since 1.0
	 */
	public function settings( $settings = array() ) {
            
            $ranks = get_ranks();
            $rank_options = array();
            foreach ($ranks as $rank) {
                $rank_options[$rank['id']] = $rank['name'];
            }

		$ltp_settings = array(
			// MLM Settings			
			'ltp' => apply_filters( 'affwp_settings_ltp',
				array(
					'affwp_mlm_general_header' => array(
						'name' => '<strong>' . __( 'General Settings', 'affiliate-ltp' ) . '</strong>',
						'type' => 'header',
					),
					'affwp_ltp_company_agent_id' => array(
						'name' => __( 'Company Agent', 'affiliate-ltp' ),
						'desc' => '<p class="description">' . __( 'Enter an Agent ID that represents the company user.' ) . '</p>',
						'type' => 'number',
						'size' => 'small',
						'std' => ''
					),
                                        'affwp_ltp_company_rate' => array(
						'name' => __( 'Default Affiliate', 'affiliatewp-multi-level-marketing' ),
						'desc' => '<p class="description">' . __( 'Enter an Affiliate ID to assign sub affiliates to a particular affiliate when no referral is found.' ) . '</p>',
						'type' => 'number',
						'size' => 'small',
						'std' => ''
					),
					'affwp_ltp_company_rate' => array(
						'name' => '<strong>' . __( 'Company Percentage Rate', 'affiliate-ltp' ) . '</strong>',
						'desc' => '<p class="description">' . __( 'Enter the Company Percentage Rate that will be taken off every commission ie 5, 10, 20.' ) . '</p>',
                                                'type' => 'number',
						'size' => 'small',
						'std' => ''
					),
                                    'affwp_ltp_generational_override_1_rate' => array(
						'name' => '<strong>' . __( '1st Generation Override Rate', 'affiliate-ltp' ) . '</strong>',
						'desc' => '<p class="description">' . __( 'Enter the 1st Generation Override Percentage Rate ie 4, 9, 17.' ) . '</p>',
                                                'type' => 'number',
						'size' => 'small',
						'std' => ''
					),
                                    'affwp_ltp_generational_override_2_rate' => array(
						'name' => '<strong>' . __( '2nd Generation Override Rate', 'affiliate-ltp' ) . '</strong>',
						'desc' => '<p class="description">' . __( 'Enter the 2nd Generation Override Percentage Rate ie 4, 9, 17.' ) . '</p>',
                                                'type' => 'number',
						'size' => 'small',
						'std' => ''
					),
                                    'affwp_ltp_generational_override_3_rate' => array(
						'name' => '<strong>' . __( '3rd Generation Override Rate', 'affiliate-ltp' ) . '</strong>',
						'desc' => '<p class="description">' . __( 'Enter the 3rd Generation Override Percentage Rate ie 4, 9, 17.' ) . '</p>',
                                                'type' => 'number',
						'size' => 'small',
						'std' => ''
					),
                                    'affwp_ltp_partner_rank_id' => array(
						'name' => '<strong>' . __( 'Partner Rank', 'affiliate-ltp' ) . '</strong>',
						'desc' => '<p class="description">' . __( 'Select the rank for a partner' ) . '</p>',
                                                'type' => 'select',
                                                'options' => $rank_options,
						'size' => 'small',
						'std' => ''
					),
                                    
				)
			)
		);

		$settings = array_merge( $settings, $ltp_settings );
		
		return $settings;
	}
}
