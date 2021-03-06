<?php 
namespace AffiliateLTP\admin\commissions;

// we'd use our autoloader but the affiliate-wp doesn't follow any kind
// of convention
// TODO: stephen is there a way to centralize some of this junk?
if (!class_exists('AffWP\Admin\List_Table')) {
    require_once dirname(AFFILIATEWP_PLUGIN_DIR) 
    . "/affiliate-wp/includes/abstracts/class-affwp-list-table.php";
}

use AffiliateLTP\Commission_Type;
use AffiliateLTP\admin\Commission_DAL;
use AffWP\Admin\List_Table;

/**
 * This class due to some wierdness with the wordpress list table is tightly
 * coupled to the Commissions_Table class.  It adds some functionality through
 * hooks and filters that are then implemented in the Commissions_Table
 */
class Commissions_Table_Extensions extends List_Table implements \AffiliateLTP\I_Register_Hooks_And_Actions {

    /**
     *
     * @var Commission_DAL
     */
    private $commission_dal;
    
    /**
     * The id of the main agent representing the company user.
     * @var int
     */
    private $company_agent_id;

    /**
	 * @param  Commission_DAL $commission_dal The database for commissions
	 */
	public function __construct(Commission_DAL $commission_dal, $company_agent_id) {
            $this->commission_dal = $commission_dal;
            $this->company_agent_id = $company_agent_id;
	}
        
        public function register_hooks_and_actions() {
            add_filter( 'affwp_referral_table_columns', array($this, 'add_table_columns' ) );
            add_filter( 'affwp_referral_row_actions', array($this, 'update_commission_actions'), 10, 2);
            add_filter( 'affwp_referrals_bulk_actions', array($this, 'update_bulk_actions'), 10, 1);
        }

        /**
         * Normally this would be in the commissions table, however there's some really wierd VUDU
         * going on that I spent a day investigating and couldn't figure out why I can override
         * the columns with this hook, but not with a direct method override.
         * @param array $columns The current columns
         * @return array The new columns to display.
         */
	public function add_table_columns($columns) {
		$new = array(
			'cb' => $columns['cb']
			,'amount' => $columns['amount']
			,'affiliate' => $columns['affiliate']
                        ,'client' => __('Client', 'affiliate-ltp')
                        ,'reference' => __('Contract Number', 'affiliate-ltp')	
                        ,'type' => __('Type', 'affiliate-ltp')
			,'description' => $columns['description']
			,'date' => $columns['date']
			,'status' => $columns['status']
			,'points' => __('Points', 'affiliate-ltp')
			,'actions' => $columns['actions']
		);
		return $new;
	}
        
        public function update_bulk_actions($bulk_actions) {
            /**
             * 
             'accept'         => __( 'Accept', 'affiliate-wp' ),
			'reject'         => __( 'Reject', 'affiliate-wp' ),
			'mark_as_paid'   => __( 'Mark as Paid', 'affiliate-wp' ),
			'mark_as_unpaid' => __( 'Mark as Unpaid', 'affiliate-wp' ),
			'delete'         => __( 'Delete', 'affiliate-wp' ),
             */
            unset($bulk_actions['accept']);
            unset($bulk_actions['reject']);
            unset($bulk_actions['delete']);
            return $bulk_actions;
        }
        
        public function update_commission_actions($row_actions, $commission) {
            unset($row_actions['reject']);
            unset($row_actions['accept']);
            unset($row_actions['delete']);
            
            $request = $this->get_commission_request_for_referral($commission);
            if (!$this->is_main_commission_for_request($commission, $request)) {
                $row_actions = $this->retain_actions($row_actions, 
                        ['mark-as-paid', 'mark-as-unpaid']);
            }
            
            if ($this->is_company_commission($commission)) {
                unset($row_actions['mark-as-paid']);
                unset($row_actions['mark-as-unpaid']);
            }
            
            return $row_actions;
        }
        
        private function is_company_commission($commission) {
            return $this->company_agent_id == $commission->affiliate_id;
        }
        
        private function is_main_commission_for_request($commission, $request) {
            if (empty($request)) {
                return false;
            }
            if ($commission->affiliate_id == $request['writing_agent_id']) {
                return true;
            }
            if ($this->is_company_commission($commission)
                    && !empty($request['request'])) {
                $json_request = json_decode($request['request'], true);
                if ($json_request["companyHaircutAll"] == true) {
                    return true;
                }
            }
            return false;
        }
        
        private function get_delete_link($commission_request_id) {
            return $this->get_row_action_link(
                    __( 'Delete', 'affiliate-wp' )
                    ,[
                        'commission_request_id' => $commission_request_id
                        ,'affwp_action' => 'process_delete_commission'
                    ]
                    ,[
                        'nonce' => 'affwp_delete_commission_nonce',
                        'class' => 'delete'
                    ]
		);
        }
        
        private function get_chargeback_link($commission_request_id, $commission_id) {
            return $this->get_row_action_link(
                            __( 'Chargeback', 'affiliate-ltp' ),
                            array(
                                    'referral_id' => $commission_id
                                    ,'commission_request_id' => $commission_request_id
                                    ,'affwp_action' => 'process_chargeback_commission'
                            )
                            ,array(
                                    'nonce' => 'affwp_chargeback_commission_nonce',
                                    'class' => 'chargeback'
                            ))
                    ;
        }
        
        private function retain_actions(array $actions, array $actions_to_retain) {
            $new_actions = [];
            foreach ($actions_to_retain as $retain) {
                if (isset($actions[$retain])) {
                    $new_actions[$retain] = $actions[$retain];
                }
            }
            return $new_actions;
        }
        
        public function get_commission_request_for_referral($commission) {
            
            $request_id = $this->commission_dal->get_commission_request_id_from_commission($commission->referral_id);
            if (!empty($request_id)) {
                return $this->commission_dal->get_commission_request($request_id);
            }
            return null;
        }

	public function column_type($value, $commission) {
		$value = $commission->context;
		if ($value == Commission_Type::TYPE_LIFE) {
			return __('Life Insurance', 'affiliate-ltp');
		}
		else {
			return __('Non-Life Insurance', 'affiliate-ltp');
		}
	}

	public function column_points($value, $commission) {
                $value = $this->commission_dal->get_commission_agent_points($commission->referral_id);
        if (empty($value)) {
			return '';
		}
		return $value;
	}
}