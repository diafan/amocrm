<?php

/**
 * Основной класс для работы с Amo.CRM API
 * @link https://developers.amocrm.ru/rest_api/ документация
 * 
 * обратите внимание, на то что у каждого аккаунта свои индивидуальные справочники различных полей (дополнителных полей контактов, сделок и т.д.)
 * в данной реалиации за возвращение этих идентификаторов из справочников служат методы: field, task, lead;
 * которые расчитаны на поиск полей по различным критериям (названиям, коду),
 * вы же можете задать необходимые именно Вам идентификаторы самостоятельно, найдя по коду места вызова этих методов
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
 * Вспомогательный класс для работы с библиотекой cURL
 */
class cURLHelper {

    private $curl;
    private $options;

    function __construct($url, $params = null) {
        $this->curl = curl_init();
        if (!$this->curl)
            throw new cURLHelperException("cURL not init");

        $cookie_file = dirname(__FILE__) . '/amocrm_cookie.txt';


        if ($params) {
            $url = $this->append($url, $params);
        }

        $this->options = array(CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_ENCODING => "",
            CURLOPT_NOPROGRESS => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_COOKIEFILE => $cookie_file,
            CURLOPT_COOKIEJAR => $cookie_file,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_URL => $url
        );



        return $this;
    }

    private function append($url, $params) {
        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }

    function form($data) {
        $this->options[CURLOPT_POST] = true;
        $this->options[CURLOPT_POSTFIELDS] = $data;

        return $this;
    }

    function code() {
        return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    }

    function headers(array $headers) {
        $custom_headers = array();
        foreach ($headers as $name => $value)
            $custom_headers[] = $name . ': ' . $value;

        $this->options[CURLOPT_HTTPHEADER] = $custom_headers;

        return $this;
    }

    function exec() {

        curl_setopt_array($this->curl, $this->options);
        $result = curl_exec($this->curl);
        
        if(curl_errno($this->curl)) {
            throw new cURLHelperException(curl_error($this->curl), curl_errno($this->curl));
        }
        
        return $result;
    }

    function ok() {
        return 200 == $this->code();
    }

    function __destruct() {
        if ($this->curl) {
            curl_close($this->curl);
        }
    }

}

class cURLHelperException extends Exception {
    
}

/**
 * Amocrm_inc
 */
class Amocrm_inc extends Model {

    const REQUEST = 'request';
    const API_VERSION = 'v2';
    const TYPE_RESPONSE = 'json';
    const RESPONSE = 'response';
    const HTTP_USER_AGENT = 'amoCRM-API-diafanCMS-client/1.5';
    const HTTP_NO_CONTENT = 204;
    const HTTP_POST = 'POST';
    const HTTP_GET = 'GET';
    const METHOD_AUTH = 'auth';
    const ERROR_CODE = 'error_code';
    const ERROR_MESSAGE = 'error';
    const FIELD_PHONE = 'PHONE';
    const FIELD_EMAIL = 'EMAIL';
    const MESSAGE_ADDCONTACT = 'Добавлен новый контакт';
    const MESSAGE_ADDORDER = 'Новый заказ';
    const TASK_FOLLOW = "FOLLOW_UP";
    const TASK_CALL = "CALL";

    /** @var bool Cтатус авторизации */
    private $auth;

    /** @var array Содержит содержит дополнительные поля для разных сущностей */
    private $custom_fields;

    /** @var array Список статусов сделок */
    private $leads_statuses;

    /** @var array Типы задач доступных для данного аккаунта */
    private $task_types;

    /** @var array Список ID контактов */
    private $contacts;

    /** @var array Массив ID связанных сделок */
    private $contacts_leads;

    function __construct($diafan) {
        parent::__construct($diafan);

        $this->auth = false;

        $this->custom_fields = array();
        $this->leads_statuses = array();
        $this->task_types = array();
        $this->contacts = array();
        $this->contacts_leads = array();
    }
    
    public function contactsLeads($user_id, $lead_id = null) {
        if(array_key_exists($user_id, $this->contacts_leads)) {
            if(null !== $lead_id) {
                $this->contacts_leads[$user_id][] = $lead_id;
            }
            
            return $this->contacts_leads[$user_id];
        }
        
        return array();
    }

    private function config($name) {
        return $this->diafan->configmodules($name, 'amocrm');
    }

    /**
     * Выполняет API запрос указанный в поле $method к AmoCRM
     * @param type $http_method
     * @param string $method
     * @param callable $onsuccess function(array $response) { }
     * @param string|array $params GET 
     * @param string|array $fields POST FIELDS
     * @throws AmoCRMException возращает массив {'error','error_code'}
     */
    public function request($http_method, $method, callable $onsuccess, $params = null, $fields = null) {

        if (self::METHOD_AUTH != $method && false === $this->auth) {
            $this->auth();
        }

        try {

            $http = new cURLHelper($this->createURL($method), $params);
            $http->headers(array(
                'User-Agent' => self::HTTP_USER_AGENT
            ));

            if (self::HTTP_POST == $http_method && !empty($fields)) {
                $http->form($fields);
            }

            $data = $http->exec();

            if ($data) {
                $json = json_decode($data, true);
            }

            if ($http->ok() || self::HTTP_NO_CONTENT == $http->code()) {
                if (!empty($json) && array_key_exists(self::RESPONSE, $json)) {
                    $onsuccess($json[self::RESPONSE]);
                }
            } else {
                if (!empty($json) && array_key_exists(self::RESPONSE, $json)) {
                    $error = $json[self::RESPONSE];
                    throw new AmoCRMException($error[self::ERROR_MESSAGE], intval($error[self::ERROR_CODE]));
                }
            }

            unset($http);
        } catch (cURLHelperException $e) {
            throw new AmoCRMException($e->getMessage(), $e->getCode());
        }
    }

    public function auth() {

        if (true == $this->auth)
            return;

        $this->auth = false;

        $this->request(self::HTTP_POST, self::METHOD_AUTH, function($response) {
            $this->auth = $response['auth'];
        }, array(
            'type' => self::TYPE_RESPONSE
                ), array(
            'USER_LOGIN' => $this->config("login"),
            'USER_HASH' => $this->config("hash"),
        ));

        return $this->auth;
    }

    /**
     * Важный метод который получает необходимую информацию по аккаунту: 
     * название, оплаченный период, пользователи аккаунта и их права, справочники дополнительных полей контактов и сделок, 
     * справочник статусов сделок, справочник типов событий, справочник типов задач и другие параметры аккаунта.
     */
    private function getInfo() {
        $this->request(self::HTTP_GET, 'accounts/current', function($res) {

            if (!empty($res['account']['custom_fields'])) {
                $this->custom_fields = $res['account']['custom_fields'];
            }
            if (!empty($res['account']['leads_statuses'])) {
                $this->leads_statuses = $res['account']['leads_statuses'];
            }

            if (!empty($res['account']['task_types'])) {
                $this->task_types = $res['account']['task_types'];
            }
        });
    }

    /**
     * 
     * @param string $query Искомый элемент, по текстовому запросу
     * @return int ID контакта
     */
    public function contact($query) {
        if (array_key_exists($query, $this->contacts))
            return $this->contacts[$query];

        $this->request(self::HTTP_GET, 'contacts/list', function($res) use ($query) {
            if (!empty($res['contacts'])) {
                foreach ($res['contacts'] as $contact) {
                    $this->contacts[$query] = $contact['id'];
                    $this->contacts_leads[$contact['id']] = $contact['linked_leads_id'];
                }
            }
        }, array(
            'query' => $query,
            'type' => 'contact'
                )
        );

        return (!empty($this->contacts[$query]) ? $this->contacts[$query] : 0);
    }

    /**
     * 
     * @param int $id ID контакта
     * @param int $lead_id ID сделки
     * @return array Массив ID связанных сделок
     */
    public function contactLeads($id, $lead_id = 0) {

        if (!empty($lead_id)) {
            $this->contacts_leads[$id][] = $lead_id;
        }

        return array_key_exists($id, $this->contacts_leads) ? $this->contacts_leads[$id] : array();
    }

    /**
     * Возвращает идентификатор из справочника типов задач - по коду
     * 
     * @param string $code Код задачи (используется только в стандартных задачах)
     * @return int Уникальный идентификатор задачи
     */
    public function task($code) {
        if (empty($this->task_types)) {
            $this->getInfo();
        }

        $id = 0;

        foreach ($this->task_types as $type) {
            if ($type['code'] == $code) {
                $id = (int) $type['id'];
                break;
            }
        }

        return $id;
    }

    /**
     * Возвращает идентификатор статуса из справочника статусов сделок по её имени
     * 
     * @param string $name Название статуса сделки
     * @return int Уникальный идентификатор статуса
     */
    public function lead($name = null) {
        if (empty($this->leads_statuses)) {
            $this->getInfo();
        }

        if (empty($lead->leads_statuses))
            return 0;

        $id = (int) $this->leads_statuses[0]['id'];

        if (!empty($name)) {
            foreach ($this->leads_statuses as $lead) {
                if ($name == $lead['name']) {
                    $id = (int) $lead['id'];
                    break;
                }
            }
        }

        return $id;
    }

    /**
     * Так как в данном решении нет привязки к конкретным справочникам дополнительных полей
     * этот медот возвращает первое попавшееся поле с заданным значением TYPE
     * 
     * @param string $from Имя сущности
     * @param string $type Тип поля
     * @return array поле
     */
    private function field($from, $type) {

        $return = array();

        if (empty($this->custom_fields)) {
            $this->getInfo();
        }

        if (array_key_exists($from, $this->custom_fields)) {
            foreach ($this->custom_fields[$from] as $field) {
                if (array_key_exists('code', $field) && $type == $field['code']) {

                    $return['id'] = (int) $field['id'];

                    if (array_key_exists('enums', $field)) {
                        foreach ($field['enums'] as $key => $enum) {
                            $return['enum'] = $enum;
                            break;
                        }
                    }

                    break;
                }
            }
        }

        return $return;
    }

    /**
     *
     * @param string $from Имя сущности
     * @param string $type Тип поля
     * @param string|array $values Значение
     * @return array
     */
    private function createRequestField($from, $type, $values) {
        if (!is_array($values)) {
            $values = array($values);
        }

        $field = $this->field($from, $type);

        foreach ($values as $value) {
            $arr = array('value' => $value);
            if (!empty($field['enum'])) {
                $arr['enum'] = $field['enum'];
            }

            $field['values'][] = $arr;
        }

        return $field;
    }

    /**
     * Создание запроса для метода contacts/set
     * @param string $name
     * @param int|array $linked_leads_ids ID сделки
     * @param int $date_create
     * @param string $email
     * @param string $phone
     * @return string запрос в json
     */
    public function contactsSetRequest($name, $linked_leads_ids, $date_create = null, $email = null, $phone = null) {
        $contact = array(
            'name' => $name,
            'linked_leads_id' => (!is_array($linked_leads_ids) ? array($linked_leads_ids) : $linked_leads_ids),
            'custom_fields' => array()
        );

        if (!empty($date_create)) {
            $contact['date_create'] = $date_create;
        }
        if (!empty($email)) {
            // место привязки дополнительного поля к конкретному значению
            $contact['custom_fields'][] = $this->createRequestField('contacts', self::FIELD_EMAIL, $email);
        }
        if (!empty($phone)) {
            // место привязки дополнительного поля к конкретному значению
            $contact['custom_fields'][] = $this->createRequestField('contacts', self::FIELD_PHONE, $phone);
        }

        $params = array();
        $params['add'][] = $contact;

        return $this->createRequest('contacts', $params);
    }

    /**
     * Создание запроса для метода leads/set
     * @param string $name Название сделки
     * @param int $status_id 	Статус сделки {@see Amocrm_inc::lead()}
     * @param int $date_create Дата создания текущей сделки 
     * @param string $price Бюджет сделки
     * @return string запрос в json
     */
    public function leadsSetRequest($name, $status_id, $date_create = 0, $price = 0) {
        $lead = array(
            'name' => $name,
            'statis_id' => $status_id
        );

        if (!empty($date_create)) {
            $lead['date_create'] = $date_create;
        }

        if (!empty($price)) {
            $lead['price'] = $price;
        }

        $params = array();
        $params['add'][] = $lead;

        return $this->createRequest('leads', $params);
    }

    /**
     * 
     * @param int $user_id Уникальный идентификатор контакта
     * @param string $type Тип задачи
     * @param string $text Текст задачи
     * @return string запрос в json
     */
    public function tasksSetRequest($user_id, $type, $text) {
        $task = array(
            'element_id' => $user_id,
            'element_type' => 1,
            'task_type' => $this->task($type),
            'text' => $text
        );

        $params = array();
        $params['add'][] = $task;

        return $this->createRequest('tasks', $params);
    }

    private function task_add_test() {
        // $this->task(self::TASK_FOLLOW);

        $user_id = $this->contact("test@mail.ru");

        if (!$user_id) {
            $this->request(self::HTTP_POST, 'contacts/set', function($response) {
                $contact = $response['contacts']['add'][0];
                $user_id = $contact['id'];
            }, null, $this->contactsSetRequest('test', 0, 123456, 'test@mail.ru', '123')
            );
        }

        $this->request(self::HTTP_POST, 'tasks/set', function($response) {
            print_r($response);
        }, null, $this->tasksSetRequest($user_id, self::TASK_FOLLOW, 'ололо')
        );
    }

    private function leads_add_test() {
        $user_id = $this->contact("lol@mail.ru");

        $this->request(self::HTTP_POST, 'leads/set', function($s) use ($user_id) {
            $lead = $s['leads']['add'][0];



            if (!$user_id) {
                $this->request(self::HTTP_POST, 'contacts/set', function($response) {
                    
                }, null, $this->contactsSetRequest('lol', $lead['id'], 123456, 'lol@mail.ru', '123')
                );
            } else {
                $this->contacts_leads[$user_id][] = $lead['id'];
                $params = array();
                $params['update'][] = array(
                    'id' => $user_id,
                    'last_modified' => time(),
                    'linked_leads_id' => $this->contacts_leads[$user_id],
                        //'name' => $this->contacts_names[$user_id]
                );

                $this->request(self::HTTP_POST, 'contacts/set', function($response) {
                    print_r($response);
                }, null, $this->createRequest('contacts', $params)
                );
            }
        }, null, $this->leadsSetRequest(self::MESSAGE_ADDORDER, $this->lead(), time(), 2000)
        );
    }

    private function contact_add_test() {

        $this->request(self::HTTP_POST, 'leads/set', function($s) {


            $lead = $s['leads']['add'][0];

            $this->request(self::HTTP_POST, 'contacts/set', function($response) {
                $contact = $response['contacts']['add'][0];
            }, null, $this->contactsSetRequest('diafan', $lead['id'], 123456, 'diafan@mail.ru', '123')
            );
        }, null, $this->leadsSetRequest(self::MESSAGE_ADDCONTACT, $this->lead())
        );
    }

    public function test() {



        $this->contact_add_test();
        //$this->task_add_test();
        //$this->leads_add_test();
    }

    /**
     * Создание запроса
     * @param string $name
     * @param string|array $params
     * @return string запрос в json
     */
    public function createRequest($name, $params) {
        return json_encode(array(self::REQUEST => array($name => $params)));
    }

    private function createURL($method) {
        
        $url = 'https://' . preg_replace('/^https?\:\/\//','',$this->config("domain")) . '/private/api/';
        switch ($method) {
            case self::METHOD_AUTH:
                $url.='auth.php';
                break;
            default:
                $url .= self::API_VERSION . '/' . self::TYPE_RESPONSE . '/' . $method;
                break;
        }
        return $url;
    }

}

class AmoCRMException extends Exception {

    function __construct($message, $code = 0, $previous = null) {
        parent::__construct('amoCRM: ' . $message, $code, $previous);
    }

}
