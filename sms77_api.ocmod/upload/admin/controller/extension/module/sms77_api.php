<?php

class ControllerExtensionModuleSms77Api extends Controller {
    private $error = [];

    public function index() {
        $this->{isset($this->request->get['controllerAction'])
            ? $this->request->get['controllerAction'] : 'settings'}();
    }

    private static function isValidPlaceholder($word) {
        $startsWith = function ($string, $startString) {
            return 0 === strpos($string, $startString);
        };

        $endsWith = function ($string, $endString) {
            $len = strlen($endString);

            return 0 === $len ? true : (substr($string, -$len) === $endString);
        };

        return $startsWith($word, '{{') && $endsWith($word, '}}');
    }

    public function settings() {
        $hasModuleId = isset($this->request->get['module_id']);
        $this->load->language('extension/module/sms77_api_settings');
        $this->document->setTitle($this->language->get('heading'));
        $this->load->model('setting/setting');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['action'] = $hasModuleId
            ? $this->url->link('extension/module/sms77_api', 'user_token=' //sms77_api_settings
                . $this->session->data['user_token'] . '&module_id='
                . $this->request->get['module_id'] . '&controllerAction=settings', true)
            : $this->url->link('extension/module/sms77_api', 'user_token=' //sms77_api_settings
                . $this->session->data['user_token'] . '&controllerAction=settings', true);
        $data['cancel'] = $this->url->link('marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['settings'] = $this->model_setting_setting->getSetting('sms77_api');
        $data['breadcrumbs'] = [
            [
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
                'text' => $this->language->get('text_home'),
            ],
            [
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
                'text' => $this->language->get('text_extension'),
            ],
            [
                'href' => $hasModuleId
                    ? $this->url->link('extension/module/sms77_api', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
                    : $this->url->link('extension/module/sms77_api', 'user_token=' . $this->session->data['user_token'] . '&action=settings', true),
                'text' => $this->language->get('heading'),
            ],
        ];

        if ('POST' === $this->request->server['REQUEST_METHOD']) {
            if (!$this->user->hasPermission('modify', 'extension/module/sms77_api')) {
                $this->error['warning'] = $this->language->get('error_permission');
            }

            if (utf8_strlen($this->request->post['sms77_api_apiKey'])) { //apiKey
                $this->model_setting_setting->editSetting('sms77_api', $this->request->post);
            } else {
                $this->error['apiKey'] = $this->language->get('error_apiKey');
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link('marketplace/extension', 'user_token='
                    . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['controllerAction'] = 'settings';
        $this->response->setOutput($this->load->view('extension/module/sms77_api_settings', $data));
    }

    public function messages() {
        $userToken = $this->session->data['user_token'];
        $hasModuleId = isset($this->request->get['module_id']);
        $this->load->language('extension/module/sms77_api_messages');
        $this->document->setTitle($this->language->get('heading'));
        $this->load->model('setting/module');
        $this->load->model('setting/setting');
        $this->load->model('extension/module/sms77_api_message');
        $this->load->model('customer/customer_group');
        $this->load->model('customer/customer');

        if ('POST' === $this->request->server['REQUEST_METHOD']) {
            $requests = [];
            $apiKey = $this->model_setting_setting->getSetting('sms77_api')['sms77_api_apiKey'];
            $text = isset($this->request->post['text']) ? $this->request->post['text'] : null;
            $to = isset($this->request->post['to']) ? $this->request->post['to'] : null;
            $from = isset($this->request->post['from']) ? $this->request->post['from'] : null;
            $label = isset($this->request->post['label']) ? $this->request->post['label'] : null;
            $udh = isset($this->request->post['udh']) ? $this->request->post['udh'] : null;
            $delay = isset($this->request->post['delay']) ? $this->request->post['delay'] : null;
            $customerGroup = isset($this->request->post['customerGroup'])
                ? (int)$this->request->post['customerGroup'] : null;

            if (!$this->user->hasPermission('modify', 'extension/module/sms77_api')) {
                $this->error['warning'] = $this->language->get('error_permission');
            }

            if (!utf8_strlen($text)) {
                $this->error['text'] = $this->language->get('error_text');
            }

            if (!$this->error) {
                $placeholderKeys = ['firstname', 'lastname', 'email', 'telephone',
                    'fax', 'custom_field', 'ip', 'date_added',];
                $isCustomizedText = preg_match_all(
                    '/{{(' . implode('|', $placeholderKeys) . ')}}/m', $text);

                if (!utf8_strlen($to)) {
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
                                    if (self::isValidPlaceholder($word)) {
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
                        json_decode(file_get_contents('https://gateway.sms77.io/api/sms?' .
                            http_build_query(array_merge($request, [
                                'delay' => $delay,
                                'from' => $from,
                                'label' => $label,
                                'json' => 1,
                                'p' => $apiKey,
                                'sendWith' => 'opencart',
                                'udh' => $udh,
                            ]))),
                            true));
                }

                $this->session->data['success'] = $this->language->get('text_success');

                $this->response->redirect(
                    $this->url->link('extension/module/sms77_api', 'user_token='
                        . $this->session->data['user_token']
                        . '&type=module&controllerAction=messages', true));
            }
        }

        foreach (['warning', 'to', 'from', 'text', 'delay', 'udh', 'label'] as $field) {
            $data["error_$field"] = isset($this->error[$field]) ? $this->error[$field] : '';
        }

        $data['action'] = $hasModuleId
            ? $this->url->link('extension/module/sms77_api', 'user_token='
                . $userToken . '&module_id=' . $this->request->get['module_id']
                . '&controllerAction=messages', true)
            : $this->url->link('extension/module/sms77_api',
                "user_token=$userToken&controllerAction=messages", true);
        $data['breadcrumbs'] = [
            [
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
                'text' => $this->language->get('text_home'),
            ],
            [
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
                'text' => $this->language->get('text_extension'),
            ],
            [
                'href' => $hasModuleId
                    ? $this->url->link('extension/module/sms77_api', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
                    : $this->url->link('extension/module/sms77_api_messages', 'user_token=' . $this->session->data['user_token'], true),
                'text' => $this->language->get('heading'),
            ],
        ];
        $data['cancel'] =
            $this->url->link("marketplace/extension", "user_token={$userToken}&type=module", true);
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['customerGroups'] = $this->model_customer_customer_group->getCustomerGroups();
        $data['value_delay'] = $this->_toData('delay');
        $data['value_from'] = $this->_toData('from');
        $data['value_label'] = $this->_toData('label');
        $data['value_text'] = $this->_toData('text');
        $data['value_to'] = $this->_toData('to');
        $data['value_udh'] = $this->_toData('udh');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['messages'] = $this->model_extension_module_sms77_api_message->getMessages();

        $this->response->setOutput($this->load->view('extension/module/sms77_api_messages', $data));
    }

    private function _toData($key) {
        if (isset($this->request->get['module_id'])
            && 'POST' !== $this->request->server['REQUEST_METHOD']) {
            $module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
        }

        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }

        if (!empty($module_info)) {
            return $module_info[$key];
        }

        return '';
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