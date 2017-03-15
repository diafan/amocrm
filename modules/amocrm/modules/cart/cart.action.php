<?php

/**
 * cart
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

    function after_add($obj, $order_id, $summ, $fields) {

        $mail = (array_key_exists('mail', $fields) ? filter_input(INPUT_POST, $fields['mail'], FILTER_SANITIZE_EMAIL) : '');
        $name = (array_key_exists('name', $fields) ? filter_input(INPUT_POST, $fields['name'], FILTER_SANITIZE_STRING) : '');
        $phone = (array_key_exists('phone', $fields) ? filter_input(INPUT_POST, $fields['phone'], FILTER_SANITIZE_NUMBER_INT) : '');

        $user_id = $obj->diafan->_amocrm->contact($mail);

        $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'leads/set', function($s) use ($obj, $user_id, $mail, $name, $phone) {
            $lead = $s['leads']['add'][0];

            if (!$user_id) {
                $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'contacts/set', function($response) {
                    
                }, null, $obj->diafan->_amocrm->contactsSetRequest($name, $lead['id'], time(), $mail, $phone)
                );
            } else {
                $obj->diafan->_amocrm->contactsLeads($user_id, $lead['id']);
                $params = array();
                $params['update'][] = array(
                    'id' => $user_id,
                    'last_modified' => time(),
                    'linked_leads_id' => $obj->diafan->_amocrm->contactsLeads($user_id)
                );

                $obj->diafan->_amocrm->request(Amocrm_inc::HTTP_POST, 'contacts/set', function($response) {
                    
                }, null, $obj->diafan->_amocrm->createRequest('contacts', $params)
                );
            }
        }, null, $obj->diafan->_amocrm->leadsSetRequest(Amocrm_inc::MESSAGE_ADDORDER .' #'.$order_id, $obj->diafan->_amocrm->lead(), time(), $summ)
        );
    }

}