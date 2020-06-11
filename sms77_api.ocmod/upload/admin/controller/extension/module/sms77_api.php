<?php

class ControllerExtensionModuleSms77Api extends Controller {
    private $error = [];

    public function __construct($registry) {
        parent::__construct($registry);

        $this->load->language('extension/module/sms77_api');
        $this->load->model('setting/setting');
    }

    public function index() {
        $this->{isset($this->request->get['controllerAction'])
            ? $this->request->get['controllerAction'] : 'settings'}();
    }

    public function settings() {
        $this->document->setTitle($this->language->get('heading_settings'));

        $data = $this->_commonData('settings');

        if ('POST' === $this->request->server['REQUEST_METHOD']) {
            if ($this->user->hasPermission('modify', 'extension/module/sms77_api')) {
                if ($data['settings'] === $this->request->post) {
                    $this->error['unchanged'] = $this->language->get('error_unchanged');
                } else {
                    if (!$this->_postEqualsStored('sms77_api_apiKey', $data['settings'])
                        && !is_float($this->_apiCall('balance'))) {
                        $this->error['apiKey'] = $this->language->get('error_apiKey');
                    }

                    $this->model_setting_setting->editSetting('sms77_api', $this->request->post);

                    $this->session->data['success'] = $this->language->get('text_settings_updated');
                }
            } else {
                $this->error['warning'] = $this->language->get('error_permission');
            }

            $this->response->redirect($this->url->link('marketplace/extension',
                "user_token={$this->session->data['user_token']}&type=module", true));
        }

        $this->_toError(['warning', 'apiKey', 'from', 'unchanged', 'label', 'udh',], $data);

        $this->response->setOutput($this->load->view('extension/module/sms77_api_settings', $data));
    }

    private function _commonData($controllerAction) {
        $userToken = $this->session->data['user_token'];

        $data['action'] = $this->url->link('extension/module/sms77_api',
            "user_token={$userToken}&controllerAction=$controllerAction", true);
        $data['breadcrumbs'] = [
            [
                'href' => $this->url->link('common/dashboard', "user_token={$userToken}", true),
                'text' => $this->language->get('text_home'),
            ],
            [
                'href' => $this->url->link('marketplace/extension',
                    "user_token={$userToken}&type=module", true),
                'text' => $this->language->get('text_extension'),
            ],
            [
                'href' => $this->url->link('extension/module/sms77_api',
                    "user_token={$userToken}&action=$controllerAction", true), //TODO change to controllerAction ?!?!?
                'text' => $this->language->get("heading_$controllerAction"),
            ],
        ];
        $data['cancel'] = $this->url->link('marketplace/extension',
            "user_token={$userToken}&type=module", true);
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['controllerAction'] = $controllerAction;
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['settings'] = $this->model_setting_setting->getSetting('sms77_api');

        return $data;
    }

    private function _postEqualsStored($key, array $settings) {
        return $settings[$key] === $this->request->post[$key];
    }

    private function _apiCall($endpoint, array $data = []) {
        $apiKey = $this->_getSettings()['sms77_api_apiKey'];

        if (!utf8_strlen($apiKey)) {
            return false;
        }

        return json_decode(file_get_contents("https://gateway.sms77.io/api/$endpoint?" .
            http_build_query(array_merge($data, [
                'p' => $apiKey,
                'sendWith' => 'opencart',
            ]))),
            true);
    }

    private function _getSettings() {
        $settings = $this->model_setting_setting->getSetting('sms77_api');

        if (!array_key_exists('sms77_api_from', $settings)) {
            $settings['sms77_api_from'] = $this->config->get('config_name');
        }

        return $settings;
    }

    private function _toError(array $fields, array &$data) {
        foreach ($fields as $field) {
            $data["error_$field"] = isset($this->error[$field]) ? $this->error[$field] : '';
        }

        return $data;
    }

    public function messages() {
        $this->load->model('extension/module/sms77_api_message');
        $this->load->model('customer/customer');
        $this->load->model('customer/customer_group');
        $this->document->setTitle($this->language->get('heading_messages'));
        $this->document->addScript('https://unpkg.com/@sms77.io/counter@1.2.0/dist/index.js');
        $this->document->addScript("//{$_SERVER['HTTP_HOST']}/admin/view/javascript/sms77_api.js");

        if ('POST' === $this->request->server['REQUEST_METHOD']) {
            if ($this->user->hasPermission('modify', 'extension/module/sms77_api')) {
                $this->_sms();
            } else {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }

        $data = array_merge($this->_commonData('messages'), [
            'customerGroups' => $this->model_customer_customer_group->getCustomerGroups(),
            'messages' => $this->model_extension_module_sms77_api_message->getMessages(),
        ]);

        $this->_toError(['warning', 'to', 'from', 'text', 'delay', 'udh', 'label'], $data);

        $this->response->setOutput($this->load->view('extension/module/sms77_api_messages', $data));
    }

    private function _sms() {
        $requests = [];
        $text = $this->_toValue('text');
        $to = $this->_toValue('to');
        $from = $this->_toValue('from');
        $label = $this->_toValue('label');
        $udh = $this->_toValue('udh');
        $delay = $this->_toValue('delay');
        $customerGroup = array_key_exists('customerGroup', $this->request->post)
            ? (int)$this->request->post['customerGroup'] : null;

        if (!utf8_strlen($text)) {
            $this->error['text'] = $this->language->get('error_text');
        }

        if (!$this->error) {
            $placeholderKeys = ['firstname', 'lastname', 'email', 'telephone',
                'fax', 'custom_field', 'ip', 'date_added',];
            $isCustomizedText = preg_match_all(
                '/{{(' . implode('|', $placeholderKeys) . ')}}/m', $text);

            if ($customerGroup) {
                $customers = $this->model_customer_customer->getCustomers([
                    'filter_customer_group_id' => $customerGroup,
                    'filter_status' => 1,
                ]);

                if (count($customers)) {
                    $customerPhones = [];
                    $words = explode(' ', $text);

                    foreach ($customers as $customer) {
                        if (!utf8_strlen($customer['telephone'])) {
                            continue;
                        }

                        if ($isCustomizedText) {
                            $customText = $text;

                            foreach ($words as $word) {
                                if (self::_isValidPlaceholder($word)) {
                                    $prop = str_replace(['{{', '}}'], '', $word);

                                    if (in_array($prop, $placeholderKeys, false)) {
                                        $customText = preg_replace("/$word/m", $customer[$prop], $customText);
                                    }
                                }
                            }

                            $requests[] = ['text' => $customText, 'to' => $customer['telephone'],];
                        } else {
                            $customerPhones[] = $customer['telephone'];
                        }
                    }

                    if (!$isCustomizedText) {
                        $to = implode(',', array_unique($customerPhones));
                    }
                }
            }

            foreach (count($requests) ? $requests : [['text' => $text, 'to' => $to,]] as $request) {
                $this->model_extension_module_sms77_api_message->setMessageResponse(
                    $this->model_extension_module_sms77_api_message->addMessage($this->request->post),
                    $this->_apiCall('sms', array_merge($request, [
                        'delay' => $delay,
                        'from' => $from,
                        'label' => $label,
                        'json' => 1,
                        'udh' => $udh,
                    ])));
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link('extension/module/sms77_api', 'user_token='
                    . $this->session->data['user_token']
                    . '&type=module&controllerAction=messages', true));
        }
    }

    private function _toValue($key) {
        return array_key_exists($key, $this->request->post)
            ? $this->request->post[$key] : null;
    }

    private static function _isValidPlaceholder($word) {
        $startsWith = function ($string, $startString) {
            return 0 === strpos($string, $startString);
        };

        $endsWith = function ($string, $endString) {
            $len = strlen($endString);

            return 0 === $len ? true : (substr($string, -$len) === $endString);
        };

        return $startsWith($word, '{{') && $endsWith($word, '}}');
    }

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "sms77_api_message` (
		  `id` INT(11) NOT NULL AUTO_INCREMENT,
		  `response` TEXT DEFAULT NULL,
		  `config` TEXT NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS`" . DB_PREFIX . "sms77_api_message`");
    }
}