<?php

/**
 * @property object $model_setting_setting
 * @property object $request
 * @property object $session
 * @property object $load
 * @property object $document
 * @property object $language
 * @property object $user
 * @property object $response
 * @property object $model_customer_customer_group
 * @property object $model_customer_customer
 * @property object $model_extension_module_seven_api_message
 * @property object $url
 * @property object $db
 * @property object $model_setting_module
 */
class ControllerExtensionModuleSevenApi extends Controller {
    private $error = [];

    public static $defaultSettings = ['status' => 1, 'name' => 'seven.io API'];

    public static $commonFields = ['warning', 'from', 'label', 'udh',
        'no_reload', 'flash', 'performance_tracking', 'foreign_id',];

    public function index() {
        $this->load->language('extension/module/seven_api');
        $this->load->model('setting/setting');

        $this->{isset($this->request->get['controllerAction'])
            ? $this->request->get['controllerAction'] : 'settings'}();
    }

    public function settings() {
        $this->document->setTitle($this->language->get('heading_settings'));

        $data = $this->_commonData('settings');

        if ('POST' === $this->request->server['REQUEST_METHOD']) {
            if ($this->user->hasPermission('modify', 'extension/module/seven_api')) {
                if ($data['settings'] === $this->request->post) {
                    $this->error['unchanged'] = $this->language->get('error_unchanged');
                } else {
                    if (!$this->_postEqualsStored('seven_api_key', $data['settings'])
                        && !is_float($this->_apiCall('balance',
                            ['p' => $this->request->post['seven_api_key']]))) {
                        $this->error['key'] = $this->language->get('error_key');
                    }

                    $this->model_setting_setting->editSetting('seven_api', $this->request->post);

                    $this->session->data['success'] = $this->language->get('text_settings_updated');
                }
            } else {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }

        $this->_toError(array_merge(self::$commonFields, ['key', 'unchanged',]), $data);

        $data['settings'] = $this->_getSettings();

        $this->response->setOutput($this->load->view('extension/module/seven_api_settings', $data));
    }

    private function _commonData($controllerAction) {
        $userToken = $this->session->data['user_token'];

        $data['action'] = $this->url->link('extension/module/seven_api',
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
                'href' => $this->url->link('extension/module/seven_api',
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
        $data['settings'] = $this->model_setting_setting->getSetting('seven_api');

        return $data;
    }

    private function _postEqualsStored($key, array $settings) {
        return array_key_exists($key, $settings)
            ? $settings[$key] === $this->request->post[$key] : false;
    }

    private function _apiCall($endpoint, array $data = []) {
        $merge = [
            'sendWith' => 'OpenCart',
        ];

        if (!isset($data['p'])) {
            $merge['p'] = $this->_getSettings()['seven_api_key'];
        }

        $config = array_merge($data, $merge);

        return json_decode(file_get_contents("https://gateway.seven.io/api/$endpoint?" .
            http_build_query($config)),
            true);
    }

    private function _getSettings() {
        $settings = $this->model_setting_setting->getSetting('seven_api');

        if (!array_key_exists('seven_api_from', $settings)) {
            $settings['seven_api_from'] = $this->config->get('config_name');
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
        $this->load->model('extension/module/seven_api_message');
        $this->load->model('customer/customer');
        $this->load->model('customer/customer_group');
        $this->document->setTitle($this->language->get('heading_messages'));
        $this->document->addScript('https://unpkg.com/@sms77.io/counter@1.2.0/dist/index.js');
        $this->document->addScript("//{$_SERVER['HTTP_HOST']}/admin/view/javascript/seven_api.js");

        if ('POST' === $this->request->server['REQUEST_METHOD']) {
            if ($this->user->hasPermission('modify', 'extension/module/seven_api')) {
                $this->_sms();
            } else {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }

        $data = array_merge($this->_commonData('messages'), [
            'customerGroups' => $this->model_customer_customer_group->getCustomerGroups(),
            'messages' => $this->model_extension_module_seven_api_message->getMessages(),
        ]);

        $this->_toError(array_merge(self::$commonFields, ['delay', 'text', 'to',]), $data);

        $this->response->setOutput($this->load->view('extension/module/seven_api_messages', $data));
    }

    private function _sms() {
        $requests = [];
        $text = $this->_toValue('text');
        $to = $this->_toValue('to');
        $baseConfig = [
            'delay' => $this->_toValue('delay'),
            'flash' => $this->_toNumericBool('flash'),
            'foreign_id' => $this->_toValue('foreign_id'),
            'from' => $this->_toValue('from'),
            'label' => $this->_toValue('label'),
            'no_reload' => $this->_toNumericBool('no_reload'),
            'json' => 1,
            'performance_tracking' => $this->_toNumericBool('performance_tracking'),
            'udh' => $this->_toValue('udh'),
        ];
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
                $this->model_extension_module_seven_api_message->setMessageResponse(
                    $this->model_extension_module_seven_api_message->addMessage($this->request->post),
                    $this->_apiCall('sms', array_merge($request, $baseConfig)));
            }

            $this->session->data['success'] = $this->language->get('text_success');
        }
    }

    private function _toValue($key) {
        return array_key_exists($key, $this->request->post)
            ? $this->request->post[$key] : null;
    }

    private function _toNumericBool($key) {
        $value = (bool)$this->_toValue($key);

        return $value ? 1 : 0;
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
        $this->load->model('setting/module');
        $this->load->model('setting/setting');
        $this->load->model('extension/module/seven_api_message');

        $this->model_extension_module_seven_api_message->install();

        $this->model_setting_module->addModule('seven_api', self::$defaultSettings);

        $this->model_setting_setting->editSetting('seven_api', ['seven_api_status' => 1]); //TODO? prefix with module_
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->load->model('extension/module/seven_api_message');

        $this->model_setting_setting->deleteSetting('seven_api');

        $this->model_extension_module_seven_api_message->uninstall();
    }
}
