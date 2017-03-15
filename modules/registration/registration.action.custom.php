<?php

/**
 * Обработка запроса при регистрации пользователя
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

class Registration_action extends Action {
    
    after public function add() {
        if(array_key_exists('result', $this->result) && 'success' == $this->result["result"]) {
            Custom::inc('modules/amocrm/modules/registration/registration.action.php');
            after_add($this);
        }
    }

}
