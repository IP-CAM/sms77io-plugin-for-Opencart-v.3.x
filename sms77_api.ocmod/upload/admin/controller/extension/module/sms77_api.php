<?php

class ControllerExtensionModuleSms77Api extends Controller {
    private $error = [];

    public function index() {
        $this->{isset($this->request->get['controllerAction'])
            ? $this->request->get['controllerAction'] : 'settings'}();
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
        $this->load->language('extension/module/sms77_api_messages');
        $this->document->setTitle($this->language->get('heading_messages'));
        $this->load->model('setting/module');
        $this->load->model('setting/setting');
        $this->load->model('extension/module/sms77_api_message');

        if (('POST' === $this->request->server['REQUEST_METHOD']) && $this->validate()) {
            if (isset($this->request->post['to']) && isset($this->request->post['text'])) {
                $id = $this->model_extension_module_sms77_api_message->addMessage($this->request->post);
                $apiKey = $this->model_setting_setting->getSetting('sms77_api')['sms77_api_apiKey'];
                $to = $this->request->post['to'];
                $text = $this->request->post['text'];
                $from = $this->request->post['from'];
                $url = "https://gateway.sms77.io/api/sms?p={$apiKey}&to={$to}&from={$from}&text={$text}&json=1";
                $res = json_decode(file_get_contents($url), true);
                $this->model_extension_module_sms77_api_message->setMessageResponse($id, $res);
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link('marketplace/extension', 'user_token='
                    . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['name'] : '';
        $data['error_to'] = isset($this->error['to']) ? $this->error['to'] : '';
        $data['error_from'] = isset($this->error['from']) ? $this->error['from'] : '';
        $data['error_text'] = isset($this->error['text']) ? $this->error['text'] : '';

        $hasModuleId = isset($this->request->get['module_id']);
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
                'text' => $this->language->get('heading_messages'),
            ],
        ];

        $data['action'] = $hasModuleId
            ? $this->url->link('extension/module/sms77_api', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'] . '&controllerAction=messages', true)
            : $this->url->link('extension/module/sms77_api', 'user_token=' . $this->session->data['user_token'] . '&controllerAction=messages', true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        if (isset($this->request->get['module_id']) && ('POST' !== $this->request->server['REQUEST_METHOD'])) {
            $module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
        }

        if (isset($this->request->post['message_id'])) {
            $data['message_id'] = $this->request->post['message_id'];
        } elseif (!empty($module_info)) {
            $data['message_id'] = $module_info['message_id'];
        } else {
            $data['message_id'] = '';
        }

        $data['messages'] = $this->model_extension_module_sms77_api_message->getMessages();

        if (isset($this->request->post['from'])) {
            $data['width'] = $this->request->post['from'];
        } elseif (!empty($module_info)) {
            $data['from'] = $module_info['from'];
        } else {
            $data['from'] = '';
        }

        if (isset($this->request->post['to'])) {
            $data['to'] = $this->request->post['to'];
        } elseif (!empty($module_info)) {
            $data['to'] = $module_info['to'];
        } else {
            $data['to'] = '';
        }

        if (isset($this->request->post['text'])) {
            $data['status'] = $this->request->post['text'];
        } elseif (!empty($module_info)) {
            $data['text'] = $module_info['text'];
        } else {
            $data['text'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/sms77_api_messages', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/sms77_api')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!utf8_strlen($this->request->post['to'])) {
            $this->error['to'] = $this->language->get('error_to');
        }

        if (!utf8_strlen($this->request->post['text'])) {
            $this->error['text'] = $this->language->get('error_text');
        }

        return !$this->error;
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