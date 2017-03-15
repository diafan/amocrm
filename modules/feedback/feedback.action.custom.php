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

class Feedback_action extends Action {
    
    after public function add() {
        if(array_key_exists('result', $this->result) && 'success' == $this->result["result"]) {
            
        
            $fields = array();
            
            // Нужно получить название полей - имя телефон емаил сообщение
            $this->check_site_id();
            $params = $this->model->get_params(array("module" => "feedback", "where" => "site_id=".$this->site_id));
         
            
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
                
                if('textarea' == $param['type'] && !array_key_exists('text', $fields)) {
                    $fields['text'] = 'p'.$param['id'];
                    continue;
                }
            }
            
            Custom::inc('modules/amocrm/modules/feedback/feedback.action.php');
            after_add($this, $fields);
        }
    }

}
