<?php

/**
 * registration
 * 
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2016 OOO «Диафан» (http://www.diafan.ru/)
 */
if (!defined("DIAFAN")) {
    $path = __FILE__;
    $i = 0;
    while (!file_exists($path . '/includes/404.php')) {
        if ($i == 10)
            exit;
        $i++;
        $path = dirname($path);
    }
    include $path . '/includes/404.php';
}

if (!function_exists('after_add')) {

    function after_add($obj) {

        try {
            $mail = filter_input(INPUT_POST, 'mail', FILTER_SANITIZE_EMAIL);
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_NUMBER_INT);
            
            $user_id = $obj->diafan->_amocrm->contact($mail);
            if (!$user_id) {
                $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'leads/set', function($s) use ($obj, $mail, $name, $phone) {
                    $lead = $s['leads']['add'][0];
                    
                    $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'contacts/set', function($response) use ($obj, $lead) {

                        $contact = $response['contacts']['add'][0];
                        $obj->diafan->_amocrm->contactLeads($contact['id'], $lead['id']);
                        
                    }, null, $obj->diafan->_amocrm->contactsSetRequest($name, $lead['id'], time(), $mail, $phone)
                    );
                }, null, $obj->diafan->_amocrm->leadsSetRequest(Amocrm_inc::MESSAGE_ADDCONTACT, $obj->diafan->_amocrm->lead())
                );
            }
        } catch (AmoCRMException $e) {
            $obj->result['errors'][0] = $e->getMessage();
        }
    }

}