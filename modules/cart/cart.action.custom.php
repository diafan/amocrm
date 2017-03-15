<?php

/**
 * 
 * 
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2016 OOO «Диафан» (http://www.diafan.ru/)
 */
if (!defined('DIAFAN')) {
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

class Cart_action extends Action {
    
    after public function order() {
        if(array_key_exists('result', $this->result) && 'success' == $this->result["result"]) {
            
            
            $fields = array();

            foreach($params as $param) {
                
                if('text' == $param['type']  && !array_key_exists('name', $fields)) {
                    $fields['name'] = 'p'.$param['id'];
                    continue;
                }
                
                if('email' == $param['type'] && !array_key_exists('mail', $fields)) {
                    $fields['mail'] = 'p'.$param['id'];
                    continue;
                }
                
                if('phone' == $param['type'] && !array_key_exists('phone', $fields)) {
                    $fields['phone'] = 'p'.$param['id'];
                    continue;
                }
            }
            
            Custom::inc('modules/amocrm/modules/cart/cart.action.php');
            after_add($this, $order_id, $summ, $fields);
        }
    }

}
