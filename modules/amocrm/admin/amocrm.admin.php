<?php

/**
 * Настройки модуля
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

/**
 * Amo_admin_config
 */
class Amocrm_admin extends Frame_admin {

    public $variables = array(
        'config' => array(
            'domain' => array(
                'type' => 'text',
                'name' => 'URL адрес аккаунта без http://',
                'help' => 'В этом поле можно использовать только латинские символы и числа, никаких спец. символов и пробелов. Например: new579b8343c7812.amocrm.ru',
            ),
            'login' => array(
                'type' => 'text',
                'name' => 'Логин пользователя',
                'help' => "В качестве логина в системе используется e-mail. Например: mail@gmail.com",
            ),
            'hash' => array(
                'type' => 'text',
                'name' => 'API ключ пользователя',
                'help' => 'API ключ пользователя, можно получить на странице редактирования профиля пользователя на сайте АМОCRM. Например: 6467f749084c6f7f74f4ec4714a38fc7',
            )
        )
    );

    /**
     * @var array настройки модуля
     */
    public $config = array(
        'config', // файл настроек модуля
    );
    
    public function prepare_config()
	{
            if (version_compare(phpversion(), '5.3.0', '<')) 
                echo '<div class="error">'.$this->diafan->_('Для корректной работы модуля необходимо обновить PHP до версии не ниже 5.3.0').'</div>';
	}

}
