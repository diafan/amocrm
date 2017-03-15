<?php

/**
 * feedback
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

    function after_add($obj, $fields) {

        try {
            $mail = array_key_exists('mail', $fields) ? filter_input(INPUT_POST, $fields['mail'], FILTER_SANITIZE_EMAIL) : '';
            $name = array_key_exists('name', $fields) ? filter_input(INPUT_POST, $fields['name'], FILTER_SANITIZE_STRING) : '';
            $text = array_key_exists('text', $fields) ? filter_input(INPUT_POST, $fields['text'], FILTER_SANITIZE_STRING) : '';
            $phone = array_key_exists('phone', $fields) ? filter_input(INPUT_POST, $fields['phone'], FILTER_SANITIZE_NUMBER_INT) : '';
            
            $user_id = $obj->diafan->_amocrm->contact($mail);
            if (!$user_id) {
                $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'leads/set', function($s) use ($obj, $mail, $name, $phone, $text) {
                    $lead = $s['leads']['add'][0];
                    
                    $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'contacts/set', function($response) use ($obj, $lead, $text) {

                        $contact = $response['contacts']['add'][0];
                        $obj->diafan->_amocrm->contactLeads($contact['id'], $lead['id']);
                        
                        $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'tasks/set', function($response) {}, null,  $obj->diafan->_amocrm->tasksSetRequest($contact['id'], Amocrm_inc::TASK_FOLLOW, $text));
                        
                    }, null, $obj->diafan->_amocrm->contactsSetRequest($name, $lead['id'], time(), $mail, $phone)
                    );
                }, null, $obj->diafan->_amocrm->leadsSetRequest(Amocrm_inc::MESSAGE_ADDCONTACT, $obj->diafan->_amocrm->lead())
                );
            } else {
                $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'tasks/set', function($response) {}, null, $obj->diafan->_amocrm->tasksSetRequest($user_id, Amocrm_inc::TASK_FOLLOW, $text));
            }
        } catch (AmoCRMException $e) {
            $obj->result['errors'][0] = $e->getMessage();
        }
    }

}