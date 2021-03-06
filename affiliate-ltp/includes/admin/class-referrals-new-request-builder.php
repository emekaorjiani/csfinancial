<?php

namespace AffiliateLTP\admin;

use AffiliateLTP\admin\Referrals_New_Request;
use AffiliateLTP\admin\Referrals_Agent_Request;
use AffiliateLTP\Commission_Type;
use \Exception;

/**
 * Description of class-referrals-new-request-builder
 *
 * @author snielson
 */
class Referrals_New_Request_Builder {
    
    
    const SINGLE_AGENT_ROW_NUMBER = 0;
    
    private static function parseClientArgs($data) {
        $clientArgs = array (
            'id'      => ! empty( $data['id'] ) ? sanitize_text_field( $data['id'] ) : null,
            'contract_number' => ! empty( $data['contract_number'] ) ? sanitize_text_field( $data['contract_number'] ) : null,
            'name'    => ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
            'street_address' => ! empty( $data['street_address'] ) ? sanitize_text_field( $data['street_address'] ) : '',
            'city' => ! empty( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
            'state' => ! empty( $data['state'] ) ? sanitize_text_field( $data['state'] ) : '',
            'country' => 'USA', // TODO: stephen extract this to a setting or constant.
            'zip' => ! empty( $data['zip'] ) ? sanitize_text_field( $data['zip'] ) : '',
            'phone'   => ! empty( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
            'email'   => ! empty( $data['email'] ) ? sanitize_text_field( $data['email'] ) : '',
        );
        if (!empty($data['state_of_sale'])) {
            $clientArgs['state_of_sale'] = sanitize_text_field($data['state_of_sale']);
        }
        else {
            $clientArgs['state_of_sale'] = $clientArgs['state'];
        }
        return $clientArgs;
    }
    private static function parseAgent($rowNumber, $agentData) {
        if ( !array_key_exists( 'id', $agentData )) {
            throw new Exception("For agent row: $rowNumber missing id ");
        }
        $userId      = absint( $agentData['id'] );

        $agentId = affiliate_wp()->affiliates->get_column_by( 'affiliate_id', 'user_id', $userId );
        $split = abs($agentData['split']);

        if ( ! empty( $agentId ) ) {
            $agent = new Referrals_Agent_Request();
            $agent->split = $split;
            $agent->id = $agentId;
            return $agent;
        } else {
            throw new Exception("affiliate_id could not be found from user_id");
        }
    }
    
    private static function parseDate($requestData) {
	if ( ! empty( $requestData['date'] ) ) {
		return date_i18n( 'Y-m-d H:i:s', strtotime( $requestData['date'] ) );
	}
        // use the current time if no date is provided.
        else {
            return date_i18n( 'Y-m-d H:i:s', time() );
        }
    }
    
    /**
     * Validates and converts the request data into the right format to be used
     * for creating a new referral.
     * @param array $requestData
     * @return Referrals_New_Request
     * @throws Exception
     */
    public static function build( $requestData ) {
        $request = new Referrals_New_Request();
        if ( empty( $requestData['agents'] )) {
            throw new Exception("No agent information was submitted");
        }
        
        // if there is no other split then we drop all the other agent pieces
        // and force the commission to be 100.
        if (!isset($requestData['split_commission'])) {
            $requestData['agents'] = array(
                self::SINGLE_AGENT_ROW_NUMBER => $requestData['agents'][self::SINGLE_AGENT_ROW_NUMBER]
            );
            // make sure that the split is always 100%
            $requestData['agents'][self::SINGLE_AGENT_ROW_NUMBER]['split'] = 100; 
        }
        
        if (isset($requestData['skip_company_haircut']) && $requestData['skip_company_haircut'] === true) {
            $request->skipCompanyHaircut = true;
            $request->companyHaircutPercent = 0;
        }
        
        if (isset($requestData['company_haircut_all']) && $requestData['company_haircut_all'] === true) {
            $request->companyHaircutAll = true;
            $request->companyHaircutPercent = 100;
        }
        else if (isset($requestData['company_haircut_percent'])) {
            // could be a percentage amount.
            $request->companyHaircutPercent = floatval($requestData['company_haircut_percent']);
        }
        
        foreach ( $requestData['agents'] as $rowNumber => $agent) {
            $request->agents[] = self::parseAgent($rowNumber, $agent);
        }
        
        
        if (isset($requestData['new_business']) && $requestData['new_business'] === false) {
            $request->new_business = false;
        }
        if (isset($requestData['renewal']) && $requestData['renewal'] === true) {
            $request->renewal = true;
        }
        $request->client = isset($requestData['client']) ? self::parseClientArgs($requestData['client']) : null;
        $request->amount = ! empty( $requestData['amount'] ) ? sanitize_text_field( $requestData['amount'] )      : '';
        $request->date = self::parseDate($requestData);
        $request->points = $request->amount;
        
        if (isset($requestData['is_life_commission']) && $requestData['is_life_commission']) {
            $request->type = Commission_Type::TYPE_LIFE;
            // set the points to be whatever was entered for a life commission
            if ($request->new_business) {
                $request->points = !empty( $requestData['points'] ) ? sanitize_text_field( $requestData['points'] ) : $request->amount;
            }
            else {
                // for repeat business the points are allowed to be 0
                $request->points = !empty( $requestData['points'] ) ? sanitize_text_field( $requestData['points'] ) : 0;
            }
        }
        else {
            $request->type = Commission_Type::TYPE_NON_LIFE;
        }
        
        if (isset($requestData['skip_life_licensed_check']) && $requestData['skip_life_licensed_check'] === true) {
            $request->skip_life_licensed_check = true;
        }
        
        return $request;
    }
}
