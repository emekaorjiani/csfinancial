<?php
namespace AffiliateLTP;

/**
 * Handles all interactions with the Sugar CRM
 *
 * @author snielson
 */
class Sugar_CRM_DAL {
    
    const USER_NAME = "admin"; // not sure why one is lowercase and the other is uppercase
    const PASSWORD = 'ae67ecae664590bdb190c45823030c16';
    const URL = "https://cms.mycommonsensefinancial.com/service/v4_1/rest.php";
    //const USER_NAME = "Admin";
//    const URL = "http://localhost:8001/service/v4_1/rest.php";
    const APP_NAME = "Agents MyCommonSenseFinancial";
    const SESSION_TOKEN_LENGTH = 60; // 60 seconds.
    const SESSION_TRANSIENT_ID = 'affwp-ltp-sugarcrm-id';
    private static $instance = null;
    
    private $sessionId = null;
    
    /**
     * Returns the singleton instance.
     * @return Sugar_CRM_DAL
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new Sugar_CRM_DAL();
        }
        return self::$instance;
    }
    
    public function createAccount($accountData) {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }
        
        $newAccount = $this->setAccount($accountData);
        // return the new id of the account that was created.
        return $newAccount->id;
    }
    
    public function updateAccount($accountData) {
        if (!isset($accountData['id'])) {
            // TODO: stephen handle the error here
            error_log("update account called without a valid id");
            return;
        }
        
        $this->setAccount($accountData);
    }
    
    public function getAccountById($accountId) {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }
        
        // TODO: stephen see how SuiteCRM wants to handle input data parameters.
        $entries = $this->getAccountByQuery("accounts.id = '$accountId'", 1);
        if (!empty($entries)) {
            return $entries[$accountId];
        }
        return null;
    }
    
    public function searchAccounts($searchValue="", $limit = 5) {
        
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }
        
        $searchClause = "";
        if (!empty($searchValue)) {
            // apparently need to use the module name and make it lowercase
            // see http://stackoverflow.com/a/25104583
            $searchClause = "contract_number_c LIKE '%$searchValue%'";
        }
        
        return $this->getAccountByQuery($searchClause, $limit);
    }
    
    public function isAuthenticated() {
        if (empty($this->sessionId)) {
            // check the session
            $id = get_transient(self::SESSION_TRANSIENT_ID);
            if (!empty($id)) {
                $this->sessionId = $id;
                return true;
            }
            
            return false;
        }
        return true;
    }
    
    
    private function getAccountByQuery($query, $limit) {
        $mapping = $this->getAccountMapping();
        
        $selectFields = array_values($mapping);
        
        $parameters = array(
            //session id
            'session' => $this->sessionId,

            //The name of the module from which to retrieve records
            'module_name' => 'Accounts',

            //The SQL WHERE clause without the word "where".
            'query' => $query, 

            //The SQL ORDER BY clause without the phrase "order by".
            'order_by' => "name",

            //The record offset from which to start.
            'offset' => 0,

            //A list of fields to include in the results.
            'select_fields' => $selectFields,  
            //A list of link names and the fields to be returned for each link name.
            'link_name_to_fields_array' => array(
                array(
                    'name' => 'email_addresses',
                    'value' => array(
                        'email_address',
                        'opt_out',
                        'primary_address'
                    ),
                ),
            ),  
            //The maximum number of results to return.
            'max_results' => $limit,

            //If deleted records should be included in results.
            'deleted' => 0,

            //If only records marked as favorites should be returned.
            'favorites' => false,
         );
        
        $getEntryListResult = $this->call('get_entry_list', $parameters, self::URL);
        
        if (!(isset($getEntryListResult) && isset($getEntryListResult->result_count))) {
            return array();
        }

        if ($getEntryListResult->result_count < 1) {
            return array();
        }
        $sugarKeys = array_flip($mapping);
        $results = array();
        foreach ($getEntryListResult->entry_list as $entry) {
            $key = $entry->id;
            unset($sugarKeys["id"]); // make sure we don't have a null key

            $account = array();
            $nameValueList = $entry->name_value_list;
            foreach ($sugarKeys as $entryKey => $accountKey) {
                if (isset($nameValueList->$entryKey)) {
                    $account[$accountKey] = $nameValueList->$entryKey->value;
                }
                else {
                    $account[$accountKey] = null;
                }
            }
            $results[$key] = $account;
        }
        return $results;
    }
    
    private function call($method, $parameters, $url)
    {   
        ob_start();
        $curl_request = curl_init();

        curl_setopt($curl_request, CURLOPT_URL, $url);
        curl_setopt($curl_request, CURLOPT_POST, 1); 
        curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl_request, CURLOPT_HEADER, 1); 
        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0); 
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0); 

        $jsonEncodedData = json_encode($parameters);

        $post = array(
             "method" => $method,
             "input_type" => "JSON",
             "response_type" => "JSON",
             "rest_data" => $jsonEncodedData
        );

        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);
        $result = curl_exec($curl_request);
        curl_close($curl_request);
        
        $result = explode("\r\n\r\n", $result, 2); 
        $response = json_decode($result[1]);
        ob_end_flush();

        return $response;
    }
    
    private function authenticate() {
        $sessionId = null;
        
        $loginParameters = array(
            "user_auth" => array(
                 "user_name" => self::USER_NAME,
                 "password" => self::PASSWORD,
                 "version" => "1"
            ),
            "application_name" => self::APP_NAME,
            "name_value_list" => array(),
        );
        
        
        $loginResult = $this->call("login", $loginParameters, self::URL);
        if (isset($loginResult->id)) {
            $this->sessionId = $loginResult->id;
            // use transients so we don't have to keep re-authenticating
            set_transient(self::SESSION_TRANSIENT_ID, $this->sessionId, self::SESSION_TOKEN_LENGTH);
        }
        else {
            throw new \Exception("Failed to authenticate with SugarCRM check authentication credentials");
        }
    }
    
    private function getAccountMapping() {
        return array(
            "id" => "id"
            ,"contract_number" => "contract_number_c"
            ,"agent_id" => "assigned_user_id"
            ,"name" => "name"
            ,"description" => "description"
            ,"street_address" => "billing_address_street"
            ,"city" => "billing_address_city"
            ,"state" => "billing_address_state"
            ,"state_of_sale" => "state_of_origination_c"
            ,"zip" => "billing_address_postalcode"
            ,"country" => "billing_address_country"
            ,"phone" => "phone_office"
            ,"email" => "email1"
        );
    }
    
    private function setAccount($accountData) {
        $mapping = $this->getAccountMapping();
        
        $nameValues = array();
        foreach ($mapping as $original => $new) {
            if (isset($accountData[$original])) {
                $nameValues[$new] = $accountData[$original];
            }
        }
        
        $setEntryParameters = array(
            //session id
            "session" => $this->sessionId,

            //The name of the module from which to retrieve records.
            "module_name" => "Accounts",

            //Record attributes
            "name_value_list" => $nameValues,
        );
        
        
        
        return $this->call("set_entry", $setEntryParameters, self::URL);
    }
}
